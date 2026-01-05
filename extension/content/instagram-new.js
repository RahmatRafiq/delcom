/**
 * Delcom Extension - Instagram Content Script
 *
 * Handles comment extraction and moderation on Instagram.
 *
 * IMPORTANT: Instagram changes their DOM structure frequently.
 * All selectors are centralized in SELECTORS for easy updates.
 */

import {
  BasePlatform,
  CONFIG,
  debugLog,
  errorLog,
  warnLog,
  sleep,
  randomDelay,
  randomBetween,
  waitForElement,
  shuffleArray,
  generateCommentId,
} from './lib/index.js';

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
  moreOptionsButton: 'button[aria-label="More options"], svg[aria-label="More options"], button._abl-',
  deleteOption: 'button:has-text("Delete")',
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

/**
 * Instagram Platform Class
 */
class InstagramPlatform extends BasePlatform {
  constructor() {
    super('instagram');
    this.selectors = SELECTORS;
  }

  // ========================================
  // Page Detection
  // ========================================

  getPageInfo() {
    const url = window.location.href;
    const isPostPage = /instagram\.com\/p\/[\w-]+/.test(url);
    const isReelPage = /instagram\.com\/reel\/[\w-]+/.test(url);
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

    debugLog('Page info:', { url, isPostPage, isReelPage, isProfilePage, postId });

    return {
      url,
      isPostPage,
      isReelPage,
      isProfilePage,
      isContentPage: isPostPage || isReelPage,
      postId,
      hasComments: !!document.querySelector(SELECTORS.commentSection),
    };
  }

  // ========================================
  // Comment Extraction
  // ========================================

  extractComments() {
    const comments = [];
    const container = document.querySelector(SELECTORS.modalContainer)
      || document.querySelector(SELECTORS.postContainer)
      || document;

    // Find all comment elements
    const commentElements = container.querySelectorAll(SELECTORS.alt.commentItem);

    commentElements.forEach((element, index) => {
      try {
        const comment = this.parseCommentElement(element, index);
        if (comment && comment.text) {
          comments.push(comment);
        }
      } catch (err) {
        warnLog('Error parsing comment:', err);
      }
    });

    // Also look for nested comments (replies)
    const replyElements = container.querySelectorAll(SELECTORS.alt.replyItem);
    replyElements.forEach((element, index) => {
      try {
        const comment = this.parseCommentElement(element, comments.length + index);
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

  parseCommentElement(element, index) {
    // Get username
    const usernameEl = element.querySelector('a._a9zc span, span._aap6 a');
    const username = usernameEl?.textContent?.trim() || `user_${index}`;

    // Get comment text
    const textEl = element.querySelector(SELECTORS.commentText);
    const text = textEl?.textContent?.trim() || '';

    if (!text) return null;

    // Get timestamp
    const timeEl = element.querySelector(SELECTORS.commentTime);
    const timestamp = timeEl?.getAttribute('datetime') || null;

    // Get profile URL
    const profileLink = element.querySelector('a._a9zc, a[href*="/"]');
    const profileUrl = profileLink?.href || null;

    // Generate unique ID
    const id = generateCommentId('ig', username, index);

    // Store reference
    element.dataset.delcomId = id;

    return {
      id,
      text,
      username,
      timestamp,
      profileUrl,
      element: null,
    };
  }

  // ========================================
  // Content Info
  // ========================================

  getContentInfo() {
    const pageInfo = this.getPageInfo();
    const captionEl = document.querySelector(SELECTORS.caption);

    return {
      postId: pageInfo.postId,
      url: pageInfo.url,
      caption: captionEl?.textContent?.trim() || '',
    };
  }

  // ========================================
  // Comment Deletion
  // ========================================

  async deleteComment(commentId) {
    const element = document.querySelector(`[data-delcom-id="${commentId}"]`);
    if (!element) {
      warnLog('Comment element not found:', commentId);
      return false;
    }

    try {
      // Find the more options button
      const moreBtn = element.querySelector(SELECTORS.moreOptionsButton);
      if (!moreBtn) {
        warnLog('More options button not found for:', commentId);
        return false;
      }

      // Click to open menu
      const clickTarget = moreBtn.closest('button') || moreBtn;
      clickTarget.click();
      await sleep(300);

      // Find and click delete option
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
        warnLog('Delete button not found');
        return false;
      }

      await sleep(300);

      // Confirm deletion if dialog appears
      const confirmBtn = document.querySelector(SELECTORS.confirmDelete);
      if (confirmBtn && confirmBtn.textContent.toLowerCase().includes('delete')) {
        confirmBtn.click();
        await sleep(300);
      }

      // Mark as deleted
      element.classList.remove('delcom-spam-highlight');
      element.classList.add('delcom-deleted');

      debugLog('Comment deleted:', commentId);
      return true;
    } catch (err) {
      errorLog('Error deleting comment:', commentId, err);
      return false;
    }
  }

  // ========================================
  // Profile Scanning
  // ========================================

  async scanProfile(options = {}) {
    const maxPosts = Math.min(
      options.maxPosts || CONFIG.safety.maxPostsPerScan,
      CONFIG.safety.maxPostsPerScan
    );

    const allComments = [];
    const postsScanned = [];

    debugLog(`Starting profile scan (max ${maxPosts} posts)`);
    this.updateScanStatus('Finding posts...');

    // Scroll to load posts
    await this.loadMorePosts(maxPosts);

    // Find post links
    const postLinks = this.findPostLinks();
    debugLog(`Found ${postLinks.length} posts`);

    if (postLinks.length === 0) {
      return { success: false, error: 'No posts found on this profile' };
    }

    // Shuffle for random order
    const shuffledPosts = shuffleArray([...postLinks]).slice(0, maxPosts);

    for (let i = 0; i < shuffledPosts.length; i++) {
      const postLink = shuffledPosts[i];
      this.updateScanStatus(`Scanning post ${i + 1}/${shuffledPosts.length}...`);

      try {
        // Random delay
        await randomDelay(CONFIG.safety.delayBetweenPosts);

        // Click to open modal
        postLink.click();
        await randomDelay([1000, 2000]);

        // Wait for modal
        const modal = await waitForElement(SELECTORS.modalArticle, 3000);
        if (!modal) {
          warnLog(`Modal not found for post ${i + 1}`);
          this.closeModal();
          await randomDelay([500, 1000]);
          continue;
        }

        // Get post ID from URL
        const postUrl = window.location.href;
        const postMatch = postUrl.match(/\/(?:p|reel)\/([\w-]+)/);
        const postId = postMatch ? postMatch[1] : `post_${i}`;

        // Load all comments
        await this.loadAllCommentsInModal();

        // Extract comments
        const comments = this.extractCommentsFromModal();

        if (comments.length > 0) {
          comments.forEach(c => {
            c.postId = postId;
            c.postUrl = postUrl;
          });

          allComments.push(...comments);
          postsScanned.push({
            postId,
            postUrl,
            commentsCount: comments.length,
          });
        }

        debugLog(`Post ${i + 1}: ${comments.length} comments`);

        // Close modal
        this.closeModal();
        await randomDelay(CONFIG.safety.delayAfterModal);
      } catch (err) {
        errorLog(`Error scanning post ${i + 1}:`, err);
        this.closeModal();
        await randomDelay([500, 1500]);
      }
    }

    return {
      success: true,
      postsScanned: postsScanned.length,
      commentsCount: allComments.length,
      comments: allComments,
      posts: postsScanned,
      contentInfo: {
        profileUrl: window.location.href,
        username: this.getProfileUsername(),
      },
    };
  }

  async loadMorePosts(targetCount) {
    let lastCount = 0;
    let attempts = 0;
    const maxAttempts = 10;

    while (attempts < maxAttempts) {
      const currentCount = this.findPostLinks().length;

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

    // Scroll back to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await sleep(500);
  }

  findPostLinks() {
    const allLinks = [];
    const selectors = [
      SELECTORS.profilePosts,
      SELECTORS.alt.profilePosts,
    ];

    selectors.forEach(sel => {
      const found = document.querySelectorAll(sel);
      allLinks.push(...found);
    });

    // Deduplicate
    const unique = new Map();
    allLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && !unique.has(href)) {
        unique.set(href, link);
      }
    });

    return Array.from(unique.values());
  }

  async loadAllCommentsInModal() {
    let attempts = 0;
    const maxAttempts = 5;

    while (attempts < maxAttempts) {
      const loadMoreBtn = document.querySelector(SELECTORS.moreComments) ||
                          document.querySelector(SELECTORS.alt.moreComments);

      if (!loadMoreBtn) break;

      // Check if it's actually a load more button
      const text = loadMoreBtn.textContent?.toLowerCase() || '';
      if (!text.includes('view') && !text.includes('load')) break;

      loadMoreBtn.click();
      await randomDelay([400, 800]);
      attempts++;
    }
  }

  extractCommentsFromModal() {
    const comments = [];
    const modal = document.querySelector(SELECTORS.modalContainer);
    if (!modal) return comments;

    // Find comments in modal
    const ulElements = modal.querySelectorAll('ul');
    const commentContainers = [];

    ulElements.forEach((ul) => {
      const items = ul.querySelectorAll(':scope > li, :scope > div');

      items.forEach((item) => {
        const profileLink = item.querySelector('a[href^="/"]:not([href*="/p/"]):not([href*="/reel/"])');
        const spans = item.querySelectorAll('span');

        if (profileLink && spans.length > 0) {
          let commentText = '';
          let username = '';

          // Get username from link
          const href = profileLink.getAttribute('href');
          if (href && href.startsWith('/') && href.length > 1) {
            username = href.replace(/\//g, '');
          }

          // Find comment text
          spans.forEach(span => {
            const text = span.textContent?.trim();
            if (text &&
                text !== username &&
                text.length > 1 &&
                !text.match(/^\d+[smhdw]$/) &&
                !text.match(/^(Reply|Like|Likes|replies|Reply to|View replies)/) &&
                !span.querySelector('a')) {
              if (text.length > commentText.length) {
                commentText = text;
              }
            }
          });

          if (username && commentText && commentText.length > 2) {
            commentContainers.push({
              element: item,
              username,
              text: commentText,
            });
          }
        }
      });
    });

    // Process and deduplicate
    const seenTexts = new Set();
    commentContainers.forEach((container, index) => {
      const key = `${container.username}:${container.text.substring(0, 50)}`;
      if (seenTexts.has(key)) return;
      seenTexts.add(key);

      const id = generateCommentId('ig', container.username, index);
      container.element.dataset.delcomId = id;

      const timeEl = container.element.querySelector('time');
      const timestamp = timeEl?.getAttribute('datetime') || null;
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

    debugLog(`Extracted ${comments.length} comments from modal`);
    return comments;
  }

  closeModal() {
    // Try clicking close button
    const closeBtn = document.querySelector(SELECTORS.closeModal);
    if (closeBtn) {
      const btn = closeBtn.closest('button') || closeBtn;
      btn.click();
      return;
    }

    // Try pressing Escape
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27 }));

    // Try clicking outside modal
    const backdrop = document.querySelector(SELECTORS.modalContainer)?.parentElement;
    if (backdrop) {
      backdrop.click();
    }
  }

  getProfileUsername() {
    // Try URL first
    const match = window.location.pathname.match(/^\/([^\/]+)\/?$/);
    if (match) return match[1];

    // Try page
    const usernameEl = document.querySelector(SELECTORS.profileUsername);
    return usernameEl?.textContent?.trim() || 'unknown';
  }
}

// ========================================
// Initialize
// ========================================

(async () => {
  'use strict';

  const platform = new InstagramPlatform();
  await platform.init();

  debugLog('Instagram content script ready');
})();
