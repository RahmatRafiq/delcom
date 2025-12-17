/* eslint-env webextensions */
/**
 * Delcom Extension - Instagram Content Script
 *
 * Runs on Instagram pages to:
 * - Extract comments from posts
 * - Highlight spam comments
 * - Execute comment deletions
 */

(() => {
  'use strict';

  // ========================================
  // Configuration
  // ========================================

  const CONFIG = {
    selectors: {
      // Post page selectors
      postContainer: 'article[role="presentation"]',
      commentSection: 'ul._a9ym',
      commentItem: 'div._a9zr',
      commentText: 'span._aacl',
      commentUsername: 'a._a9zc, span._aap6',
      commentTime: 'time._a9ze',
      moreComments: 'button._abl-',

      // Modal selectors (for post modals)
      modalContainer: 'div[role="dialog"]',

      // Delete button selectors
      moreOptionsButton: 'button[aria-label="More options"], svg[aria-label="More options"]',
      deleteOption: 'button:has-text("Delete")',
    },
    maxRetries: 3,
    retryDelay: 1000,
    scrollDelay: 500,

    // Anti-detection settings
    safety: {
      maxPostsPerScan: 15,           // Max posts per scan session
      maxScansPerHour: 6,            // Max scan sessions per hour
      maxPostsPerDay: 150,           // Max posts per day
      delayBetweenPosts: [2000, 4000], // Random delay range (ms)
      delayAfterModal: [1000, 2500],   // Delay after closing modal
      delayBetweenScrolls: [500, 1500], // Scroll delay
      cooldownAfterScan: 300000,      // 5 min cooldown between scans
    }
  };

  // Rate limiting storage
  const rateLimiter = {
    lastScanTime: 0,
    scansThisHour: 0,
    postsToday: 0,
    hourStartTime: Date.now(),
    dayStartTime: Date.now(),

    canScan() {
      const now = Date.now();

      // Reset hourly counter
      if (now - this.hourStartTime > 3600000) {
        this.scansThisHour = 0;
        this.hourStartTime = now;
      }

      // Reset daily counter
      if (now - this.dayStartTime > 86400000) {
        this.postsToday = 0;
        this.dayStartTime = now;
      }

      // Check cooldown
      if (now - this.lastScanTime < CONFIG.safety.cooldownAfterScan) {
        const remaining = Math.ceil((CONFIG.safety.cooldownAfterScan - (now - this.lastScanTime)) / 1000);
        return { allowed: false, reason: `Cooldown: tunggu ${remaining} detik lagi` };
      }

      // Check hourly limit
      if (this.scansThisHour >= CONFIG.safety.maxScansPerHour) {
        return { allowed: false, reason: `Limit: max ${CONFIG.safety.maxScansPerHour} scan per jam` };
      }

      // Check daily limit
      if (this.postsToday >= CONFIG.safety.maxPostsPerDay) {
        return { allowed: false, reason: `Limit harian tercapai (${CONFIG.safety.maxPostsPerDay} posts)` };
      }

      return { allowed: true };
    },

    recordScan(postCount) {
      this.lastScanTime = Date.now();
      this.scansThisHour++;
      this.postsToday += postCount;
    },

    getStatus() {
      return {
        scansThisHour: this.scansThisHour,
        postsToday: this.postsToday,
        maxScansPerHour: CONFIG.safety.maxScansPerHour,
        maxPostsPerDay: CONFIG.safety.maxPostsPerDay,
      };
    }
  };

  // ========================================
  // State
  // ========================================

  let isScanning = false;
  let spamCommentIds = new Set();

  // ========================================
  // Message Listener
  // ========================================

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    switch (message.action) {
      case 'scan':
        handleScan().then(sendResponse);
        return true;

      case 'scanProfile':
        handleScanProfile(message.options || {}).then(sendResponse);
        return true;

      case 'deleteComments':
        handleDeleteComments(message.comments).then(sendResponse);
        return true;

      case 'highlightComments':
        highlightComments(message.commentIds).then(sendResponse);
        return true;

      case 'getPageInfo':
        sendResponse(getPageInfo());
        break;

      case 'getRateLimitStatus':
        sendResponse({
          canScan: rateLimiter.canScan(),
          status: rateLimiter.getStatus()
        });
        break;
    }
  });

  // ========================================
  // Page Detection
  // ========================================

  function getPageInfo() {
    const url = window.location.href;
    const isPostPage = /instagram\.com\/p\/[\w-]+/.test(url);
    const isReelPage = /instagram\.com\/reel\/[\w-]+/.test(url);
    // Profile page: instagram.com/username (not /p/, /reel/, /stories/, etc.)
    const isProfilePage = /instagram\.com\/[\w.]+\/?$/.test(url) &&
                          !url.includes('/p/') &&
                          !url.includes('/reel/') &&
                          !url.includes('/stories/') &&
                          !url.includes('/explore/');

    let postId = null;
    const postMatch = url.match(/\/(?:p|reel)\/([\w-]+)/);
    if (postMatch) {
      postId = postMatch[1];
    }

    console.log('Delcom: Page info', { url, isPostPage, isReelPage, isProfilePage, postId });

    return {
      url,
      isPostPage,
      isReelPage,
      isProfilePage,
      postId,
      hasComments: !!document.querySelector(CONFIG.selectors.commentSection),
    };
  }

  // ========================================
  // Comment Extraction
  // ========================================

  async function handleScan() {
    if (isScanning) {
      return { success: false, error: 'Already scanning' };
    }

    isScanning = true;

    try {
      const pageInfo = getPageInfo();

      if (!pageInfo.isPostPage && !pageInfo.isReelPage) {
        // Check if there's a modal open with a post
        const modal = document.querySelector(CONFIG.selectors.modalContainer);
        if (!modal) {
          return { success: false, error: 'Please open a post to scan comments' };
        }
      }

      // Wait for comments to load
      await waitForElement(CONFIG.selectors.commentSection, 3000);

      // Load more comments if available
      await loadAllComments();

      // Extract comments
      const comments = extractComments();

      return {
        success: true,
        commentsCount: comments.length,
        comments,
        contentInfo: {
          postId: pageInfo.postId,
          url: pageInfo.url,
          caption: getPostCaption(),
        },
      };
    } catch (error) {
      console.error('Scan error:', error);
      return { success: false, error: error.message };
    } finally {
      isScanning = false;
    }
  }

  async function loadAllComments() {
    let loadMoreBtn;
    let attempts = 0;
    const maxAttempts = 10;

    while (attempts < maxAttempts) {
      loadMoreBtn = document.querySelector(CONFIG.selectors.moreComments);
      if (!loadMoreBtn) break;

      loadMoreBtn.click();
      await sleep(CONFIG.scrollDelay);
      attempts++;
    }
  }

  function extractComments() {
    const comments = [];
    const container = document.querySelector(CONFIG.selectors.modalContainer)
      || document.querySelector(CONFIG.selectors.postContainer)
      || document;

    // Find all comment elements
    const commentElements = container.querySelectorAll('ul._a9ym > div._a9zr');

    commentElements.forEach((element, index) => {
      try {
        const comment = parseCommentElement(element, index);
        if (comment && comment.text) {
          comments.push(comment);
        }
      } catch (err) {
        console.warn('Error parsing comment:', err);
      }
    });

    // Also look for nested comments (replies)
    const replyElements = container.querySelectorAll('ul._a9yo li div._a9zr');
    replyElements.forEach((element, index) => {
      try {
        const comment = parseCommentElement(element, comments.length + index);
        if (comment && comment.text) {
          comment.isReply = true;
          comments.push(comment);
        }
      } catch (err) {
        console.warn('Error parsing reply:', err);
      }
    });

    return comments;
  }

  function parseCommentElement(element, index) {
    // Get username
    const usernameEl = element.querySelector('a._a9zc span, span._aap6 a');
    const username = usernameEl?.textContent?.trim() || `user_${index}`;

    // Get comment text
    const textEl = element.querySelector('span._aacl._aaco');
    const text = textEl?.textContent?.trim() || '';

    // Get timestamp
    const timeEl = element.querySelector('time._a9ze');
    const timestamp = timeEl?.getAttribute('datetime') || null;

    // Get profile URL
    const profileLink = element.querySelector('a._a9zc, a[href*="/"]');
    const profileUrl = profileLink?.href || null;

    // Generate unique ID
    const id = `ig_${username}_${index}_${Date.now()}`;

    // Store reference to element for later highlighting/deletion
    element.dataset.delcomId = id;

    return {
      id,
      text,
      username,
      timestamp,
      profileUrl,
      element: null, // Don't send DOM element
    };
  }

  function getPostCaption() {
    // Try to get caption from post
    const captionEl = document.querySelector('h1._a9zs, div._a9zs span');
    return captionEl?.textContent?.trim() || '';
  }

  // ========================================
  // Profile Scanning (All Posts)
  // ========================================

  async function handleScanProfile(options = {}) {
    if (isScanning) {
      return { success: false, error: 'Already scanning' };
    }

    // Check rate limits
    const canScan = rateLimiter.canScan();
    if (!canScan.allowed) {
      console.log(`Delcom: Rate limit - ${canScan.reason}`);
      return { success: false, error: canScan.reason, rateLimited: true };
    }

    const pageInfo = getPageInfo();
    if (!pageInfo.isProfilePage) {
      return { success: false, error: 'Please navigate to a profile page first' };
    }

    isScanning = true;
    // Use safety limit
    const maxPosts = Math.min(options.maxPosts || CONFIG.safety.maxPostsPerScan, CONFIG.safety.maxPostsPerScan);
    const allComments = [];
    const postsScanned = [];

    try {
      // Notify start
      updateScanStatus('Finding posts...');
      console.log(`Delcom: Starting safe scan (max ${maxPosts} posts, with random delays)`);

      // Scroll to load more posts (with human-like behavior)
      await loadMorePostsSafely(maxPosts);

      // Find all post links
      const postLinks = findPostLinks();
      console.log(`Delcom: Found ${postLinks.length} posts`);

      if (postLinks.length === 0) {
        return { success: false, error: 'No posts found on this profile' };
      }

      // Shuffle posts array untuk random order (lebih natural)
      const shuffledPosts = shuffleArray([...postLinks]).slice(0, maxPosts);

      // Process each post with random delays
      for (let i = 0; i < shuffledPosts.length; i++) {
        const postLink = shuffledPosts[i];
        updateScanStatus(`Scanning post ${i + 1}/${shuffledPosts.length}...`);

        try {
          // Random delay before clicking (human-like)
          await randomDelay(CONFIG.safety.delayBetweenPosts);

          // Click to open post modal
          postLink.click();

          // Random wait for modal
          await randomDelay([1000, 2000]);

          // Wait for modal to open
          const modal = await waitForElement('div[role="dialog"] article', 3000);
          if (!modal) {
            console.warn(`Delcom: Modal not found for post ${i + 1}`);
            closeModal();
            await randomDelay([500, 1000]);
            continue;
          }

          // Get post ID from URL
          const postUrl = window.location.href;
          const postMatch = postUrl.match(/\/(?:p|reel)\/([\w-]+)/);
          const postId = postMatch ? postMatch[1] : `post_${i}`;

          // Load all comments in this post (with delays)
          await loadAllCommentsSafely();

          // Extract comments
          const comments = extractCommentsFromModal();

          if (comments.length > 0) {
            // Add post info to each comment
            comments.forEach(c => {
              c.postId = postId;
              c.postUrl = postUrl;
            });

            allComments.push(...comments);
            postsScanned.push({
              postId,
              postUrl,
              commentsCount: comments.length
            });
          }

          console.log(`Delcom: Post ${i + 1} - ${comments.length} comments`);

          // Close modal
          closeModal();

          // Random delay after closing (human-like)
          await randomDelay(CONFIG.safety.delayAfterModal);

        } catch (err) {
          console.error(`Delcom: Error scanning post ${i + 1}:`, err);
          closeModal();
          await randomDelay([500, 1500]);
        }
      }

      // Record this scan for rate limiting
      rateLimiter.recordScan(postsScanned.length);

      updateScanStatus('');

      return {
        success: true,
        postsScanned: postsScanned.length,
        commentsCount: allComments.length,
        comments: allComments,
        posts: postsScanned,
        contentInfo: {
          profileUrl: pageInfo.url,
          username: getProfileUsername(),
        },
        rateLimit: rateLimiter.getStatus(),
      };

    } catch (error) {
      console.error('Profile scan error:', error);
      return { success: false, error: error.message };
    } finally {
      isScanning = false;
      updateScanStatus('');
    }
  }

  // Shuffle array untuk random order
  function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
  }

  // Safe version of loadMorePosts with random delays
  async function loadMorePostsSafely(targetCount) {
    let lastCount = 0;
    let attempts = 0;
    const maxAttempts = 10;

    while (attempts < maxAttempts) {
      const currentCount = document.querySelectorAll('article a[href*="/p/"], article a[href*="/reel/"]').length;

      if (currentCount >= targetCount || currentCount === lastCount) {
        break;
      }

      lastCount = currentCount;

      // Human-like scroll
      const scrollAmount = randomBetween(300, 700);
      window.scrollBy({ top: scrollAmount, behavior: 'smooth' });

      await randomDelay(CONFIG.safety.delayBetweenScrolls);
      attempts++;
    }

    // Scroll back to top smoothly
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await sleep(500);
  }

  // Safe version of loadAllComments with delays
  async function loadAllCommentsSafely() {
    let loadMoreBtn;
    let attempts = 0;
    const maxAttempts = 5; // Limit comment loading

    while (attempts < maxAttempts) {
      loadMoreBtn = document.querySelector(CONFIG.selectors.moreComments) ||
                    document.querySelector('button[type="button"]');

      // Check if it's a "load more comments" button
      if (!loadMoreBtn || !loadMoreBtn.textContent?.toLowerCase().includes('view') &&
          !loadMoreBtn.textContent?.toLowerCase().includes('load')) {
        break;
      }

      loadMoreBtn.click();
      await randomDelay([400, 800]);
      attempts++;
    }
  }

  async function loadMorePosts(targetCount) {
    let lastCount = 0;
    let attempts = 0;
    const maxAttempts = 20;

    while (attempts < maxAttempts) {
      const currentCount = document.querySelectorAll('article a[href*="/p/"], article a[href*="/reel/"]').length;

      if (currentCount >= targetCount || currentCount === lastCount) {
        break;
      }

      lastCount = currentCount;
      window.scrollTo(0, document.body.scrollHeight);
      await sleep(1000);
      attempts++;
    }

    // Scroll back to top
    window.scrollTo(0, 0);
    await sleep(500);
  }

  function findPostLinks() {
    // Find all post thumbnail links on profile grid
    // Try multiple selector patterns since Instagram changes often
    const selectors = [
      'article a[href*="/p/"]',
      'article a[href*="/reel/"]',
      'main a[href*="/p/"]',
      'main a[href*="/reel/"]',
      'div[style*="flex"] a[href*="/p/"]',
      'a[href*="/p/"][role="link"]',
      'a[href*="/reel/"][role="link"]',
    ];

    const allLinks = [];
    selectors.forEach(sel => {
      const found = document.querySelectorAll(sel);
      console.log(`Delcom: Selector "${sel}" found ${found.length} elements`);
      allLinks.push(...found);
    });

    // Filter to unique posts and get the clickable element
    const uniquePosts = new Map();
    allLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && !uniquePosts.has(href)) {
        uniquePosts.set(href, link);
      }
    });

    console.log(`Delcom: Total unique posts found: ${uniquePosts.size}`);
    return Array.from(uniquePosts.values());
  }

  function extractCommentsFromModal() {
    const comments = [];
    const modal = document.querySelector('div[role="dialog"]');
    if (!modal) {
      console.log('Delcom: No modal found for comment extraction');
      return comments;
    }

    console.log('Delcom: Modal found, searching for comments...');

    // Strategy 1: Find comments by looking for comment containers with username links + text
    // Instagram comments typically have: [avatar] [username link] [comment text] [time] [actions]
    const allLinks = modal.querySelectorAll('a[href^="/"]');
    console.log(`Delcom: Found ${allLinks.length} links in modal`);

    // Find comment sections - look for ul elements that contain comments
    const ulElements = modal.querySelectorAll('ul');
    console.log(`Delcom: Found ${ulElements.length} ul elements in modal`);

    // Strategy 2: Find spans that look like usernames (links to profiles) followed by text
    const commentContainers = [];

    // Look for the main comment list structure
    ulElements.forEach((ul, ulIndex) => {
      // Skip very small lists (likely navigation)
      const items = ul.querySelectorAll(':scope > li, :scope > div');
      if (items.length > 0) {
        console.log(`Delcom: UL[${ulIndex}] has ${items.length} items`);

        items.forEach((item, itemIndex) => {
          // Check if this item looks like a comment (has a profile link and text)
          const profileLink = item.querySelector('a[href^="/"]:not([href*="/p/"]):not([href*="/reel/"])');
          const spans = item.querySelectorAll('span');

          if (profileLink && spans.length > 0) {
            // Find the text content - usually in a span after the username
            let commentText = '';
            let username = '';

            // Get username from link
            const href = profileLink.getAttribute('href');
            if (href && href.startsWith('/') && href.length > 1) {
              username = href.replace(/\//g, '');
            }

            // Find comment text - look for span with actual content
            spans.forEach(span => {
              const text = span.textContent?.trim();
              // Skip if it's the username, a time, or action buttons
              if (text &&
                  text !== username &&
                  text.length > 1 &&
                  !text.match(/^\d+[smhdw]$/) && // Skip time like "2h", "3d"
                  !text.match(/^(Reply|Like|Likes|replies|Reply to|View replies)/) &&
                  !span.querySelector('a')) { // Skip spans containing links
                // Take the longest text as likely comment
                if (text.length > commentText.length) {
                  commentText = text;
                }
              }
            });

            if (username && commentText && commentText.length > 2) {
              commentContainers.push({
                element: item,
                username,
                text: commentText
              });
            }
          }
        });
      }
    });

    console.log(`Delcom: Found ${commentContainers.length} potential comments`);

    // Strategy 3: If no comments found, try finding by h3/span structure
    if (commentContainers.length === 0) {
      // Look for comments in article or section elements
      const articles = modal.querySelectorAll('article, section, div[role="article"]');
      articles.forEach(article => {
        const items = article.querySelectorAll('div');
        items.forEach(item => {
          // Look for username + text pattern
          const links = item.querySelectorAll('a[href^="/"]');
          links.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.match(/^\/[\w.]+\/?$/)) {
              const username = href.replace(/\//g, '');
              // Find sibling or child span with text
              const parent = link.closest('div');
              if (parent) {
                const textSpans = parent.querySelectorAll('span:not(:has(a))');
                textSpans.forEach(span => {
                  const text = span.textContent?.trim();
                  if (text && text.length > 5 && text !== username) {
                    commentContainers.push({
                      element: parent,
                      username,
                      text
                    });
                  }
                });
              }
            }
          });
        });
      });
      console.log(`Delcom: Strategy 3 found ${commentContainers.length} comments`);
    }

    // Process found comments
    const seenTexts = new Set();
    commentContainers.forEach((container, index) => {
      // Avoid duplicates
      const key = `${container.username}:${container.text.substring(0, 50)}`;
      if (seenTexts.has(key)) return;
      seenTexts.add(key);

      const id = `ig_${container.username}_${index}_${Date.now()}`;
      container.element.dataset.delcomId = id;

      // Try to find timestamp
      const timeEl = container.element.querySelector('time');
      const timestamp = timeEl?.getAttribute('datetime') || null;

      // Get profile URL
      const profileUrl = `https://www.instagram.com/${container.username}/`;

      comments.push({
        id,
        text: container.text,
        username: container.username,
        timestamp,
        profileUrl,
        element: null,
      });
    });

    console.log(`Delcom: Extracted ${comments.length} unique comments from modal`);
    return comments;
  }

  function closeModal() {
    // Try clicking close button
    const closeBtn = document.querySelector('div[role="dialog"] button svg[aria-label="Close"], div[role="dialog"] [aria-label="Close"]');
    if (closeBtn) {
      const btn = closeBtn.closest('button') || closeBtn;
      btn.click();
      return;
    }

    // Try pressing Escape
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27 }));

    // Try clicking outside modal
    const backdrop = document.querySelector('div[role="dialog"]')?.parentElement;
    if (backdrop) {
      backdrop.click();
    }
  }

  function getProfileUsername() {
    // Try to get username from URL
    const match = window.location.pathname.match(/^\/([^\/]+)\/?$/);
    if (match) return match[1];

    // Try to get from page
    const usernameEl = document.querySelector('header h2, header span[dir="auto"]');
    return usernameEl?.textContent?.trim() || 'unknown';
  }

  function updateScanStatus(message) {
    // Send status update to popup
    chrome.runtime.sendMessage({ action: 'scanStatus', message });
  }

  // ========================================
  // Comment Highlighting
  // ========================================

  async function highlightComments(commentIds) {
    spamCommentIds = new Set(commentIds);

    // Remove existing highlights
    document.querySelectorAll('.delcom-spam-highlight').forEach(el => {
      el.classList.remove('delcom-spam-highlight');
    });

    // Add highlights
    commentIds.forEach(id => {
      const el = document.querySelector(`[data-delcom-id="${id}"]`);
      if (el) {
        el.classList.add('delcom-spam-highlight');
      }
    });

    return { success: true, highlightedCount: commentIds.length };
  }

  // ========================================
  // Comment Deletion
  // ========================================

  async function handleDeleteComments(comments) {
    const results = [];
    let deletedCount = 0;

    for (const comment of comments) {
      try {
        const success = await deleteComment(comment.id);
        results.push({
          id: comment.id,
          success,
          error: success ? null : 'Failed to delete',
        });
        if (success) deletedCount++;

        // Wait between deletions to avoid rate limiting
        await sleep(500);
      } catch (error) {
        results.push({
          id: comment.id,
          success: false,
          error: error.message,
        });
      }
    }

    return {
      success: true,
      deletedCount,
      results,
    };
  }

  async function deleteComment(commentId) {
    const element = document.querySelector(`[data-delcom-id="${commentId}"]`);
    if (!element) {
      console.warn('Comment element not found:', commentId);
      return false;
    }

    try {
      // Find the three-dot menu button
      const moreBtn = element.querySelector('button svg[aria-label="Comment Options"], button._abl-, button[aria-label="More options"]');
      if (!moreBtn) {
        console.warn('More options button not found for:', commentId);
        return false;
      }

      // Click to open menu
      const clickTarget = moreBtn.closest('button') || moreBtn;
      clickTarget.click();
      await sleep(300);

      // Find and click delete option
      const deleteBtn = await waitForElement('button:contains("Delete"), div[role="dialog"] button', 1000);
      if (!deleteBtn) {
        // Close menu if delete not found
        document.body.click();
        console.warn('Delete button not found for:', commentId);
        return false;
      }

      // Find the actual delete button in the dialog
      const buttons = document.querySelectorAll('div[role="dialog"] button, div[role="menu"] button');
      let foundDelete = false;

      for (const btn of buttons) {
        if (btn.textContent.toLowerCase().includes('delete')) {
          btn.click();
          foundDelete = true;
          break;
        }
      }

      if (!foundDelete) {
        document.body.click();
        return false;
      }

      await sleep(300);

      // Confirm deletion if there's a confirmation dialog
      const confirmBtn = document.querySelector('button:contains("Delete"), div[role="dialog"] button._a9--');
      if (confirmBtn && confirmBtn.textContent.toLowerCase().includes('delete')) {
        confirmBtn.click();
        await sleep(300);
      }

      // Remove highlight
      element.classList.remove('delcom-spam-highlight');
      element.classList.add('delcom-deleted');

      return true;
    } catch (error) {
      console.error('Error deleting comment:', commentId, error);
      return false;
    }
  }

  // ========================================
  // Utility Functions
  // ========================================

  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // Random delay untuk human-like behavior
  function randomDelay(range) {
    const [min, max] = range;
    const delay = Math.floor(Math.random() * (max - min + 1)) + min;
    return sleep(delay);
  }

  // Random integer between min and max
  function randomBetween(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  async function waitForElement(selector, timeout = 5000) {
    const startTime = Date.now();

    while (Date.now() - startTime < timeout) {
      const element = document.querySelector(selector);
      if (element) return element;
      await sleep(100);
    }

    return null;
  }

  // ========================================
  // Initialize
  // ========================================

  function init() {
    console.log('Delcom: Instagram content script loaded');

    // Add custom styles for highlighting
    const style = document.createElement('style');
    style.textContent = `
      .delcom-spam-highlight {
        background: rgba(239, 68, 68, 0.15) !important;
        border-left: 3px solid #ef4444 !important;
        position: relative;
      }

      .delcom-spam-highlight::before {
        content: '🚫 SPAM';
        position: absolute;
        top: 4px;
        right: 8px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: bold;
        z-index: 100;
      }

      .delcom-deleted {
        opacity: 0.3;
        text-decoration: line-through;
      }

      .delcom-deleted::before {
        content: '✓ Deleted';
        background: #22c55e;
      }
    `;
    document.head.appendChild(style);

    // Watch for navigation changes (Instagram is SPA)
    let lastUrl = location.href;
    new MutationObserver(() => {
      if (location.href !== lastUrl) {
        lastUrl = location.href;
        spamCommentIds.clear();
        document.querySelectorAll('.delcom-spam-highlight, .delcom-deleted').forEach(el => {
          el.classList.remove('delcom-spam-highlight', 'delcom-deleted');
        });
      }
    }).observe(document.body, { subtree: true, childList: true });
  }

  init();
})();
