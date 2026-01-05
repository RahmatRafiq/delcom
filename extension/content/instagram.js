/* eslint-env webextensions */
/**
 * Delcom Extension - Instagram Content Script
 *
 * Handles comment extraction and moderation on Instagram.
 *
 * IMPORTANT: Instagram changes their DOM structure frequently.
 * All selectors are centralized in SELECTORS for easy updates.
 */

(() => {
  'use strict';

  // ========================================
  // Environment Configuration
  // ========================================

  const isDevelopment = !chrome.runtime.getManifest().update_url;

  const CONFIG = {
    // API URLs - automatically detect environment
    API_BASE: isDevelopment
      ? 'http://localhost:8000/api'
      : 'https://delcom.app/api',
    WEB_BASE: isDevelopment
      ? 'http://localhost:8000'
      : 'https://delcom.app',
    VERSION: chrome.runtime.getManifest().version,
    IS_DEV: isDevelopment,
    debug: isDevelopment,

    maxRetries: 3,
    retryDelay: 1000,
    scrollDelay: 500,

    // Anti-detection settings
    safety: {
      maxPostsPerScan: 15,
      maxScansPerHour: 6,
      maxPostsPerDay: 150,
      delayBetweenPosts: [2000, 4000],
      delayAfterModal: [1000, 2500],
      delayBetweenScrolls: [500, 1500],
      cooldownAfterScan: 300000,
      delayBetweenDeletes: [400, 800],
    },

    timeouts: {
      elementWait: 5000,
      modalWait: 3000,
    },
  };

  /**
   * Instagram Selectors
   * CENTRALIZED: Update these when Instagram changes their DOM
   * Last updated: 2024-12
   */
  const SELECTORS = {
    // Post page selectors
    postContainer: 'article[role="presentation"]',
    commentSection: 'ul._a9ym',
    commentItem: 'div._a9zr',
    commentText: 'span._aacl._aaco',
    commentUsername: 'a._a9zc span, span._aap6 a',
    commentTime: 'time._a9ze',
    moreComments: 'button._abl-',

    // Modal selectors
    modalContainer: 'div[role="dialog"]',
    modalArticle: 'div[role="dialog"] article',
    closeModal: 'div[role="dialog"] button svg[aria-label="Close"], div[role="dialog"] [aria-label="Close"]',

    // Delete button selectors
    moreOptionsButton: 'button[aria-label="More options"], svg[aria-label="More options"], button._abl-, button[aria-label="Comment Options"]',
    confirmDelete: 'button._a9--',

    // Profile page
    profilePosts: 'article a[href*="/p/"], article a[href*="/reel/"], main a[href*="/p/"], main a[href*="/reel/"]',
    profileUsername: 'header h2, header span[dir="auto"]',

    // Caption
    caption: 'h1._a9zs, div._a9zs span',

    // Alternative selectors (fallback)
    alt: {
      commentItem: 'ul._a9ym > div._a9zr',
      replyItem: 'ul._a9yo li div._a9zr',
      moreComments: 'button[type="button"]',
      profilePosts: 'div[style*="flex"] a[href*="/p/"], a[href*="/p/"][role="link"], a[href*="/reel/"][role="link"]',
    },
  };

  // ========================================
  // Logging Functions
  // ========================================

  function debugLog(...args) {
    if (CONFIG.debug) {
      console.log('[Delcom IG]', ...args);
    }
  }

  function errorLog(...args) {
    console.error('[Delcom IG Error]', ...args);
  }

  function warnLog(...args) {
    console.warn('[Delcom IG Warning]', ...args);
  }

  // Rate limiting storage (persisted to chrome.storage)
  const rateLimiter = {
    storageKey: 'delcom_ratelimit_instagram',
    lastScanTime: 0,
    scansThisHour: 0,
    postsToday: 0,
    hourStartTime: Date.now(),
    dayStartTime: Date.now(),

    async loadState() {
      try {
        const result = await chrome.storage.local.get([this.storageKey]);
        const saved = result[this.storageKey];
        if (saved) {
          this.lastScanTime = saved.lastScanTime || 0;
          this.scansThisHour = saved.scansThisHour || 0;
          this.postsToday = saved.postsToday || 0;
          this.hourStartTime = saved.hourStartTime || Date.now();
          this.dayStartTime = saved.dayStartTime || Date.now();
          this.resetIfNeeded();
        }
      } catch (err) {
        warnLog('Failed to load rate limit state', err);
      }
    },

    async saveState() {
      try {
        await chrome.storage.local.set({
          [this.storageKey]: {
            lastScanTime: this.lastScanTime,
            scansThisHour: this.scansThisHour,
            postsToday: this.postsToday,
            hourStartTime: this.hourStartTime,
            dayStartTime: this.dayStartTime,
          },
        });
      } catch (err) {
        warnLog('Failed to save rate limit state', err);
      }
    },

    resetIfNeeded() {
      const now = Date.now();
      if (now - this.hourStartTime > 3600000) {
        this.scansThisHour = 0;
        this.hourStartTime = now;
      }
      if (now - this.dayStartTime > 86400000) {
        this.postsToday = 0;
        this.dayStartTime = now;
      }
    },

    canScan() {
      this.resetIfNeeded();
      const now = Date.now();

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

    async recordScan(postCount) {
      this.lastScanTime = Date.now();
      this.scansThisHour++;
      this.postsToday += postCount;
      await this.saveState();
    },

    getStatus() {
      this.resetIfNeeded();
      return {
        platform: 'instagram',
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

    // Get username from URL or page
    let username = null;
    if (isProfilePage) {
      const usernameMatch = url.match(/instagram\.com\/([\w.]+)\/?$/);
      if (usernameMatch) {
        username = usernameMatch[1];
      }
    }
    // Also try to get from header
    if (!username) {
      username = getProfileUsername();
    }

    debugLog('Page info:', { url, isPostPage, isReelPage, isProfilePage, postId, username });

    return {
      url,
      isPostPage,
      isReelPage,
      isProfilePage,
      isContentPage: isPostPage || isReelPage,
      postId,
      username,
      hasComments: !!document.querySelector(SELECTORS.commentSection),
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
        const modal = document.querySelector(SELECTORS.modalContainer);
        if (!modal) {
          return { success: false, error: 'Please open a post to scan comments' };
        }
      }

      // Wait for comments to load
      await waitForElement(SELECTORS.commentSection, 3000);

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
      loadMoreBtn = document.querySelector(SELECTORS.moreComments);
      if (!loadMoreBtn) break;

      loadMoreBtn.click();
      await sleep(CONFIG.scrollDelay);
      attempts++;
    }
  }

  function extractComments() {
    const comments = [];
    const container = document.querySelector(SELECTORS.modalContainer)
      || document.querySelector(SELECTORS.postContainer)
      || document;

    console.log('[Delcom] extractComments - container:', container?.tagName, container?.className?.substring?.(0, 50));

    // Strategy 1: Try old selectors first
    let commentElements = container.querySelectorAll(SELECTORS.alt.commentItem);
    console.log('[Delcom] Strategy 1 (old selector) found:', commentElements.length, 'elements');

    // Strategy 2: Find comments by looking for username links followed by text
    if (commentElements.length === 0) {
      console.log('[Delcom] Trying Strategy 2: Find by structure...');
      commentElements = findCommentsByStructure(container);
      console.log('[Delcom] Strategy 2 found:', commentElements.length, 'elements');
    }

    commentElements.forEach((element, index) => {
      try {
        const comment = parseCommentElement(element, index);
        if (comment && comment.text) {
          comments.push(comment);
          console.log('[Delcom] Extracted comment:', comment.username, '-', comment.text.substring(0, 50));
        }
      } catch (err) {
        warnLog('Error parsing comment:', err);
      }
    });

    // Also look for nested comments (replies)
    const replyElements = container.querySelectorAll(SELECTORS.alt.replyItem);
    replyElements.forEach((element, index) => {
      try {
        const comment = parseCommentElement(element, comments.length + index);
        if (comment && comment.text) {
          comment.isReply = true;
          comments.push(comment);
        }
      } catch (err) {
        warnLog('Error parsing reply:', err);
      }
    });

    debugLog(`Extracted ${comments.length} comments`);
    return comments;
  }

  /**
   * Find comments by analyzing DOM structure (Instagram Dec 2025)
   * Comments typically have: [profile picture] [username link] [comment text] [time] [like button]
   */
  function findCommentsByStructure(container) {
    const commentContainers = [];

    // Get the post owner username from URL or page
    const postOwner = getPostOwnerUsername();
    console.log('[Delcom] Post owner:', postOwner);

    // Find all elements that contain a profile link to a user
    // Comments have links like /username/ (not /p/, /reel/, /explore/, etc.)
    const allUserLinks = container.querySelectorAll('a[href^="/"][role="link"]');
    console.log('[Delcom] Found', allUserLinks.length, 'user links');

    const processedParents = new Set();
    const seenUsernames = new Set();

    allUserLinks.forEach(link => {
      const href = link.getAttribute('href') || '';

      // Skip non-user links
      if (href.includes('/p/') ||
          href.includes('/reel/') ||
          href.includes('/explore/') ||
          href.includes('/stories/') ||
          href.includes('/direct/') ||
          href.includes('/accounts/') ||
          href.includes('/tagged/') ||
          !href.match(/^\/[\w.]+\/?$/)) {
        return;
      }

      const username = href.replace(/\//g, '');

      // Skip post owner (they write the caption, not a comment)
      if (username === postOwner) {
        return;
      }

      // Find the comment container (parent that contains username + text)
      // Go up max 6 levels to find a suitable container
      let parent = link.parentElement;
      for (let i = 0; i < 6 && parent; i++) {
        // Skip if already processed
        if (processedParents.has(parent)) {
          parent = parent.parentElement;
          continue;
        }

        // Skip if this is too big (likely a section container)
        if (parent.tagName === 'SECTION' || parent.tagName === 'MAIN' || parent.tagName === 'ARTICLE') {
          parent = parent.parentElement;
          continue;
        }

        // Check if this parent has text content that looks like a comment
        const textContent = parent.textContent || '';

        // Skip if this looks like navigation or meta content
        if (textContent.includes('More posts from') ||
            textContent.includes('Suggested for you') ||
            textContent.includes('Related accounts') ||
            textContent.includes('comments') && textContent.includes('View all')) {
          break;
        }

        // A comment container should have:
        // 1. The username
        // 2. Some additional text (the comment)
        // 3. Not be too large (to avoid selecting entire sections)
        // 4. Not be too small (single element)
        if (textContent.includes(username) &&
            textContent.length > username.length + 3 &&
            textContent.length < 500 &&
            textContent.length > 5) {

          // Check if there's actual comment text (not just username + time)
          const strippedText = textContent
            .replace(new RegExp(username, 'gi'), '')
            .replace(/\d+[smhdw]\b/gi, '') // Remove time like "2h", "3d"
            .replace(/Reply|Like|Likes|View replies|Balas|Suka|replies|Verified/gi, '')
            .replace(/\s+/g, ' ')
            .trim();

          // Must have actual text content
          if (strippedText.length > 1 && strippedText.length < 400) {
            // Create a unique key to avoid duplicates
            const key = `${username}:${strippedText.substring(0, 30)}`;
            if (!seenUsernames.has(key)) {
              seenUsernames.add(key);
              processedParents.add(parent);
              commentContainers.push({
                element: parent,
                username: username,
                text: strippedText,
                link: link
              });
              console.log('[Delcom] Found comment:', username, '-', strippedText.substring(0, 50));
            }
            break;
          }
        }

        parent = parent.parentElement;
      }
    });

    console.log('[Delcom] Found', commentContainers.length, 'comment containers by structure');

    // Return elements with metadata attached
    return commentContainers.map(c => {
      c.element._delcomMeta = { username: c.username, text: c.text };
      return c.element;
    });
  }

  /**
   * Get the post owner username
   */
  function getPostOwnerUsername() {
    // Try from URL path for profile pages
    const urlMatch = window.location.pathname.match(/^\/([^\/]+)\//);

    // Try from header/meta
    const headerLink = document.querySelector('header a[href^="/"][role="link"]');
    if (headerLink) {
      const href = headerLink.getAttribute('href') || '';
      const match = href.match(/^\/([^\/]+)\/?$/);
      if (match) return match[1];
    }

    // Try from article header
    const articleHeader = document.querySelector('article header a[href^="/"]');
    if (articleHeader) {
      const href = articleHeader.getAttribute('href') || '';
      const match = href.match(/^\/([^\/]+)\/?$/);
      if (match) return match[1];
    }

    // Try meta tag
    const metaContent = document.querySelector('meta[property="og:description"]');
    if (metaContent) {
      const content = metaContent.getAttribute('content') || '';
      const match = content.match(/@(\w+)/);
      if (match) return match[1];
    }

    return null;
  }

  function parseCommentElement(element, index) {
    // Check if we have metadata from findCommentsByStructure
    const meta = element._delcomMeta;

    // Get username - try multiple methods
    let username = meta?.username;
    if (!username) {
      const usernameEl = element.querySelector(SELECTORS.commentUsername);
      username = usernameEl?.textContent?.trim();
    }
    if (!username) {
      // Try finding first user link
      const userLink = element.querySelector('a[href^="/"][role="link"]');
      if (userLink) {
        const href = userLink.getAttribute('href') || '';
        if (href.match(/^\/[\w.]+\/?$/)) {
          username = href.replace(/\//g, '');
        }
      }
    }
    username = username || `user_${index}`;

    // Get comment text - try multiple methods
    let text = '';

    // Method 1: Old selector
    const textEl = element.querySelector(SELECTORS.commentText);
    if (textEl) {
      text = textEl.textContent?.trim() || '';
    }

    // Method 2: Find text by excluding username, time, and action buttons
    if (!text) {
      // Get all text content
      const fullText = element.textContent || '';

      // Remove username
      text = fullText.replace(new RegExp(username, 'gi'), '');

      // Remove time patterns (1h, 2d, 3w, etc.)
      text = text.replace(/\b\d+[smhdw]\b/gi, '');

      // Remove action words
      text = text.replace(/Reply|Like|Likes|View replies|Balas|Suka|replies/gi, '');

      // Remove extra whitespace
      text = text.replace(/\s+/g, ' ').trim();
    }

    if (!text || text.length < 2) {
      console.log('[Delcom] parseCommentElement - no text found for element:', element.textContent?.substring(0, 100));
      return null;
    }

    // Get timestamp
    const timeEl = element.querySelector('time');
    const timestamp = timeEl?.getAttribute('datetime') || null;

    // Get profile URL
    const profileLink = element.querySelector('a[href^="/"][role="link"]');
    const profileUrl = profileLink ? `https://www.instagram.com${profileLink.getAttribute('href')}` : null;

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
    const captionEl = document.querySelector(SELECTORS.caption);
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
      await rateLimiter.recordScan(postsScanned.length);

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
      loadMoreBtn = document.querySelector(SELECTORS.moreComments) ||
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
      console.warn('[Delcom] Comment element not found:', commentId);
      return false;
    }

    debugLog('Attempting to delete comment:', commentId);
    console.log('[Delcom] ========== DELETE ATTEMPT ==========');
    console.log('[Delcom] Comment ID:', commentId);
    console.log('[Delcom] Element found:', element);
    console.log('[Delcom] Element HTML preview:', element.outerHTML.substring(0, 500));

    try {
      // First, we need to hover over the comment to make the options button visible
      // Instagram only shows the "..." button on hover
      element.scrollIntoView({ block: 'center', behavior: 'smooth' });
      await sleep(300);

      // Trigger mouse events to simulate hover - try multiple approaches
      element.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true, cancelable: true }));
      element.dispatchEvent(new MouseEvent('mouseover', { bubbles: true, cancelable: true }));
      element.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, cancelable: true }));

      // Also try on parent elements
      let parent = element.parentElement;
      for (let i = 0; i < 3 && parent; i++) {
        parent.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
        parent.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        parent = parent.parentElement;
      }

      await sleep(500); // Wait longer for button to appear

      // Find the three-dot menu button within the comment element
      // IMPORTANT: We must be very careful not to click the Like button!
      // Strategy: Find ALL buttons, then filter strictly

      // Also search in parent elements (Instagram sometimes puts button outside comment div)
      let searchElement = element;
      let allButtons = searchElement.querySelectorAll('button');

      // If no buttons found, try parent
      if (allButtons.length === 0) {
        searchElement = element.parentElement;
        if (searchElement) {
          allButtons = searchElement.querySelectorAll('button');
        }
      }

      // Still no buttons? Try grandparent
      if (allButtons.length === 0 && searchElement?.parentElement) {
        searchElement = searchElement.parentElement;
        allButtons = searchElement.querySelectorAll('button');
      }

      let optionsBtn = null;

      console.log('[Delcom] Searching through', allButtons.length, 'buttons for options menu');

      // First, log all buttons for debugging
      for (const btn of allButtons) {
        const svg = btn.querySelector('svg');
        const ariaLabel = (btn.getAttribute('aria-label') || svg?.getAttribute('aria-label') || '');
        const title = btn.getAttribute('title') || '';
        const className = btn.className || '';
        console.log('[Delcom] Button:', {
          ariaLabel,
          title,
          className: className.substring(0, 50),
          visible: btn.offsetParent !== null,
          innerHTML: btn.innerHTML.substring(0, 100)
        });
      }

      // Now find the options button with STRICT criteria
      for (const btn of allButtons) {
        const svg = btn.querySelector('svg');
        const ariaLabel = (btn.getAttribute('aria-label') || svg?.getAttribute('aria-label') || '').toLowerCase();
        const title = (btn.getAttribute('title') || '').toLowerCase();
        const combinedLabel = ariaLabel + ' ' + title;

        // BLOCKLIST: NEVER click these buttons
        const blockedLabels = [
          'like', 'heart', 'unlike', 'suka', 'menyukai',  // Like buttons
          'reply', 'balas',                                // Reply buttons
          'share', 'bagikan',                              // Share buttons
          'save', 'simpan',                                // Save buttons
          'emoji', 'sticker',                              // Input buttons
        ];

        const isBlocked = blockedLabels.some(blocked => combinedLabel.includes(blocked));
        if (isBlocked) {
          console.log('[Delcom] BLOCKED button (unsafe):', combinedLabel);
          continue;
        }

        // ALLOWLIST: Only click if aria-label explicitly contains these
        const allowedLabels = [
          'option',     // "More options", "Comment Options"
          'more',       // "More"
          'opsi',       // Indonesian: "Opsi lainnya"
          'lainnya',    // Indonesian: "Lainnya"
          'menu',       // Generic menu
          'comment option', // Comment options
        ];

        const isAllowed = allowedLabels.some(allowed => combinedLabel.includes(allowed));
        if (isAllowed) {
          optionsBtn = btn;
          console.log('[Delcom] FOUND options button:', combinedLabel);
          break;
        }
      }

      // If still not found, try by looking for three-dot/ellipsis icon
      if (!optionsBtn) {
        console.log('[Delcom] Options button not found by aria-label, trying SVG analysis...');

        for (const btn of allButtons) {
          const svg = btn.querySelector('svg');
          if (!svg) continue;

          const ariaLabel = (btn.getAttribute('aria-label') || svg?.getAttribute('aria-label') || '').toLowerCase();

          // Skip if it looks like a heart/like (even without aria-label)
          const paths = svg.querySelectorAll('path');
          let hasHeartPath = false;
          paths.forEach(path => {
            const d = path.getAttribute('d') || '';
            // Heart paths typically have curved patterns
            if (d.includes('M16') || d.includes('c-4.4') || d.includes('heart')) {
              hasHeartPath = true;
            }
          });

          if (hasHeartPath) {
            console.log('[Delcom] Skipping button with heart SVG path');
            continue;
          }

          // Skip if button has any blocked labels
          const blockedLabels = ['like', 'heart', 'unlike', 'suka', 'reply', 'balas', 'share', 'save'];
          if (blockedLabels.some(b => ariaLabel.includes(b))) {
            continue;
          }

          // Check if SVG looks like three dots (ellipsis/more icon)
          // Three dots usually have 3 circle elements or specific path pattern
          const circles = svg.querySelectorAll('circle');
          if (circles.length >= 3) {
            optionsBtn = btn;
            console.log('[Delcom] Found options button by circle SVG pattern (', circles.length, 'circles)');
            break;
          }

          // Also check for ellipsis-like path (3 dots in a row)
          // This is typically done with multiple small circles or rects
          const rects = svg.querySelectorAll('rect');
          if (rects.length >= 3) {
            optionsBtn = btn;
            console.log('[Delcom] Found options button by rect SVG pattern');
            break;
          }
        }
      }

      // Last resort: try finding by class pattern (risky, but better than nothing)
      if (!optionsBtn) {
        console.log('[Delcom] Trying last resort: class-based search...');
        for (const btn of allButtons) {
          const ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
          // Skip if it has like/heart in aria-label
          if (ariaLabel.includes('like') || ariaLabel.includes('heart') || ariaLabel.includes('suka')) {
            continue;
          }
          // Skip if aria-label is empty but button is not in a "more" position
          // The "more" button is typically the last button or not the first (first is usually like)
          const btnIndex = Array.from(allButtons).indexOf(btn);
          if (btnIndex === 0 && allButtons.length > 1) {
            console.log('[Delcom] Skipping first button (likely like button)');
            continue;
          }
          // If no aria-label but has SVG, and isn't blocked, consider it
          const svg = btn.querySelector('svg');
          if (svg && !ariaLabel) {
            console.log('[Delcom] Considering button without aria-label at index', btnIndex);
            // Check it's not a heart shape
            const pathD = svg.querySelector('path')?.getAttribute('d') || '';
            if (!pathD.includes('M16') && !pathD.toLowerCase().includes('heart')) {
              optionsBtn = btn;
              console.log('[Delcom] Using button at index', btnIndex, 'as fallback');
              break;
            }
          }
        }
      }

      if (!optionsBtn) {
        console.warn('[Delcom] ❌ More options button not found for:', commentId);
        console.log('[Delcom] Could not find a safe options button to click - ABORTING to prevent accidental like');
        console.log('[Delcom] ========== DELETE FAILED ==========');
        // DO NOT click anything if we're not sure - this prevents accidental likes
        return false;
      }

      console.log('[Delcom] ✓ Clicking options button...');
      optionsBtn.click();

      await sleep(400);

      // Wait for menu/dialog to appear
      await sleep(200);

      // Find the delete option in the popup menu/dialog
      // Instagram shows options in a role="dialog" or role="menu" element
      const menuSelectors = [
        'div[role="dialog"]',
        'div[role="menu"]',
        'div[role="listbox"]',
        'div._a9-v', // Instagram's menu container class
      ];

      let menuContainer = null;
      for (const sel of menuSelectors) {
        menuContainer = document.querySelector(sel);
        if (menuContainer) break;
      }

      if (!menuContainer) {
        debugLog('No menu container found, trying to find delete button directly');
      }

      // Find all buttons and look for "Delete" text
      const searchContainer = menuContainer || document;
      const buttons = searchContainer.querySelectorAll('button, div[role="button"]');
      let foundDelete = false;

      debugLog('Found', buttons.length, 'buttons in menu');

      for (const btn of buttons) {
        const text = btn.textContent?.toLowerCase() || '';
        debugLog('Button text:', text);

        if (text.includes('delete') || text.includes('hapus')) {
          debugLog('Found delete button, clicking...');
          btn.click();
          foundDelete = true;
          break;
        }
      }

      if (!foundDelete) {
        // Close menu by clicking outside
        debugLog('Delete option not found in menu, closing...');
        document.body.click();
        await sleep(100);
        // Press Escape to close any dialogs
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        return false;
      }

      await sleep(400);

      // Check for confirmation dialog
      const confirmButtons = document.querySelectorAll('div[role="dialog"] button, div._a9-- button');
      for (const btn of confirmButtons) {
        const text = btn.textContent?.toLowerCase() || '';
        if (text.includes('delete') || text.includes('hapus')) {
          debugLog('Confirming deletion...');
          btn.click();
          await sleep(300);
          break;
        }
      }

      // Mark as deleted
      element.classList.remove('delcom-spam-highlight');
      element.classList.add('delcom-deleted');

      debugLog('Comment deleted successfully:', commentId);
      return true;
    } catch (error) {
      console.error('[Delcom] Error deleting comment:', commentId, error);
      // Try to close any open dialogs
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
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
    debugLog('Instagram content script loaded', { version: CONFIG.VERSION, isDev: CONFIG.IS_DEV });

    // Load rate limiter state
    rateLimiter.loadState();

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
