/* eslint-env webextensions */
/**
 * Delcom Extension - TikTok Content Script
 *
 * Handles comment extraction and moderation on TikTok.
 *
 * IMPORTANT: TikTok changes their DOM structure frequently.
 * All selectors are centralized in SELECTORS for easy updates.
 */

(() => {
  'use strict';

  // ========================================
  // Configuration
  // ========================================

  const isDevelopment = !chrome.runtime.getManifest().update_url;

  const CONFIG = {
    API_BASE: isDevelopment
      ? 'http://localhost:8000/api'
      : 'https://delcom.app/api',
    WEB_BASE: isDevelopment
      ? 'http://localhost:8000'
      : 'https://delcom.app',
    VERSION: chrome.runtime.getManifest().version,
    IS_DEV: isDevelopment,
    debug: isDevelopment,

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
      apiRequest: 30000,
    },
  };

  /**
   * TikTok Selectors
   * CENTRALIZED: Update these when TikTok changes their DOM
   * Last updated: 2024-12
   */
  const SELECTORS = {
    // Page detection
    videoPage: '[data-e2e="browse-video"]',
    profilePage: '[data-e2e="user-page"]',

    // Video container
    videoContainer: '[data-e2e="browse-video-container"]',
    videoPlayer: 'video',

    // Comment section
    commentSection: '[data-e2e="comment-list"]',
    commentItem: '[data-e2e="comment-item"]',
    commentText: '[data-e2e="comment-text"]',
    commentUsername: '[data-e2e="comment-username-link"]',
    commentTime: '[data-e2e="comment-time"]',
    loadMoreComments: '[data-e2e="view-more-btn"]',

    // Reply comments
    replySection: '[data-e2e="reply-list"]',
    replyItem: '[data-e2e="reply-item"]',
    viewReplies: '[data-e2e="view-replies-btn"]',

    // Comment actions
    commentOptions: '[data-e2e="comment-more-btn"]',
    deleteButton: '[data-e2e="delete-btn"]',

    // Modal/Dialog
    modal: '[role="dialog"]',
    confirmButton: '[data-e2e="confirm-btn"]',
    cancelButton: '[data-e2e="cancel-btn"]',
    closeButton: '[data-e2e="browse-close"]',

    // Profile page
    profileVideos: '[data-e2e="user-post-item"]',
    profileUsername: '[data-e2e="user-title"]',

    // Alternative selectors (backup)
    alt: {
      commentItem: '.comment-item, [class*="CommentItem"], [class*="comment-item"]',
      commentText: '.comment-text, [class*="CommentText"], [class*="comment-text"]',
      commentUsername: '.username, [class*="CommentUsername"], [class*="UserLink"]',
      commentSection: '.comment-list, [class*="CommentList"]',
      profileVideos: '.video-feed-item, [class*="VideoFeedItem"], a[href*="/video/"]',
    },
  };

  // ========================================
  // Utility Functions
  // ========================================

  function debugLog(...args) {
    if (CONFIG.debug) {
      console.log('[Delcom TikTok]', ...args);
    }
  }

  function errorLog(...args) {
    console.error('[Delcom TikTok Error]', ...args);
  }

  function warnLog(...args) {
    console.warn('[Delcom TikTok Warning]', ...args);
  }

  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  function randomDelay(range) {
    const [min, max] = Array.isArray(range) ? range : [range, range];
    const delay = Math.floor(Math.random() * (max - min + 1)) + min;
    return sleep(delay);
  }

  function randomBetween(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function shuffleArray(array) {
    const result = [...array];
    for (let i = result.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [result[i], result[j]] = [result[j], result[i]];
    }
    return result;
  }

  async function waitForElement(selector, timeout = 5000, context = document) {
    const startTime = Date.now();
    while (Date.now() - startTime < timeout) {
      const element = context.querySelector(selector);
      if (element) return element;
      await sleep(100);
    }
    return null;
  }

  function generateCommentId(platform, username, index) {
    return `${platform}_${username}_${index}_${Date.now()}`;
  }

  // ========================================
  // Rate Limiter
  // ========================================

  const rateLimiter = {
    storageKey: 'delcom_ratelimit_tiktok',
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
      const safety = CONFIG.safety;

      if (now - this.lastScanTime < safety.cooldownAfterScan) {
        const remaining = Math.ceil(
          (safety.cooldownAfterScan - (now - this.lastScanTime)) / 1000
        );
        return { allowed: false, reason: `Cooldown: tunggu ${remaining} detik lagi` };
      }

      if (this.scansThisHour >= safety.maxScansPerHour) {
        return { allowed: false, reason: `Limit: max ${safety.maxScansPerHour} scan per jam` };
      }

      if (this.postsToday >= safety.maxPostsPerDay) {
        return { allowed: false, reason: `Limit harian tercapai (${safety.maxPostsPerDay} posts)` };
      }

      return { allowed: true };
    },

    async recordScan(postCount = 1) {
      this.lastScanTime = Date.now();
      this.scansThisHour++;
      this.postsToday += postCount;
      await this.saveState();
    },

    getStatus() {
      this.resetIfNeeded();
      return {
        platform: 'tiktok',
        scansThisHour: this.scansThisHour,
        maxScansPerHour: CONFIG.safety.maxScansPerHour,
        postsToday: this.postsToday,
        maxPostsPerDay: CONFIG.safety.maxPostsPerDay,
      };
    },
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
          status: rateLimiter.getStatus(),
        });
        break;
    }
  });

  // ========================================
  // Page Detection
  // ========================================

  function getPageInfo() {
    const url = window.location.href;
    const isVideoPage = url.includes('/video/');
    const isProfilePage = /tiktok\.com\/@[\w.]+\/?$/.test(url);
    const isFYPPage = url.includes('/foryou') || url === 'https://www.tiktok.com/';

    let videoId = null;
    const videoMatch = url.match(/\/video\/(\d+)/);
    if (videoMatch) {
      videoId = videoMatch[1];
    }

    let username = null;
    const usernameMatch = url.match(/@([\w.]+)/);
    if (usernameMatch) {
      username = usernameMatch[1];
    }

    const hasComments = !!findElement(SELECTORS.commentSection);

    debugLog('Page info:', { url, isVideoPage, isProfilePage, videoId, username });

    return {
      url,
      isVideoPage,
      isProfilePage,
      isFYPPage,
      isContentPage: isVideoPage,
      isPostPage: isVideoPage,
      isReelPage: false,
      videoId,
      postId: videoId,
      username,
      hasComments,
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

      if (!pageInfo.isVideoPage) {
        return { success: false, error: 'Please open a video to scan comments' };
      }

      await waitForElement(SELECTORS.commentSection, CONFIG.timeouts.elementWait);
      await loadAllComments();

      const comments = extractComments();
      const contentInfo = getContentInfo();

      return {
        success: true,
        commentsCount: comments.length,
        comments,
        contentInfo,
      };
    } catch (err) {
      errorLog('Scan error:', err);
      return { success: false, error: err.message };
    } finally {
      isScanning = false;
    }
  }

  function extractComments() {
    const comments = [];
    let commentElements = document.querySelectorAll(SELECTORS.commentItem);

    if (!commentElements.length) {
      // Try alternatives
      for (const sel of Object.values(SELECTORS.alt)) {
        commentElements = document.querySelectorAll(sel);
        if (commentElements.length) break;
      }
    }

    if (!commentElements.length) {
      warnLog('No comment elements found');
      return comments;
    }

    commentElements.forEach((element, index) => {
      try {
        const comment = parseCommentElement(element, index);
        if (comment && comment.text) {
          comments.push(comment);
        }
      } catch (err) {
        warnLog('Error parsing comment:', err);
      }
    });

    // Also extract replies
    const replyElements = document.querySelectorAll(SELECTORS.replyItem);
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

  function parseCommentElement(element, index) {
    // Get username
    let username = 'unknown';
    const usernameEl = element.querySelector(SELECTORS.commentUsername) ||
                       element.querySelector(SELECTORS.alt.commentUsername) ||
                       element.querySelector('a[href^="/@"]');

    if (usernameEl) {
      username = usernameEl.textContent?.trim() || '';
      if (!username && usernameEl.href) {
        const match = usernameEl.href.match(/@([\w.]+)/);
        if (match) username = match[1];
      }
    }

    // Get comment text
    const textEl = element.querySelector(SELECTORS.commentText) ||
                   element.querySelector(SELECTORS.alt.commentText) ||
                   element.querySelector('span[class*="text"], p');
    const text = textEl?.textContent?.trim() || '';

    if (!text) return null;

    // Get timestamp
    const timeEl = element.querySelector(SELECTORS.commentTime) ||
                   element.querySelector('span[class*="time"]');
    const timestamp = timeEl?.textContent?.trim() || null;

    // Get profile URL
    const profileLink = element.querySelector('a[href^="/@"]');
    const profileUrl = profileLink?.href || `https://www.tiktok.com/@${username}`;

    // Generate unique ID
    const id = generateCommentId('tt', username, index);
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

  function getContentInfo() {
    const pageInfo = getPageInfo();
    const captionEl = document.querySelector('[data-e2e="video-desc"]') ||
                      document.querySelector('[class*="VideoCaption"]');

    return {
      videoId: pageInfo.videoId,
      postId: pageInfo.videoId,
      url: pageInfo.url,
      caption: captionEl?.textContent?.trim() || '',
      username: pageInfo.username,
    };
  }

  async function loadAllComments() {
    let attempts = 0;
    const maxAttempts = 5;

    while (attempts < maxAttempts) {
      const loadMoreBtn = findElement(SELECTORS.loadMoreComments) ||
                          document.querySelector('[class*="ViewMore"]');

      if (!loadMoreBtn) break;

      loadMoreBtn.click();
      await randomDelay([400, 800]);
      attempts++;
    }
  }

  // ========================================
  // Profile Scanning
  // ========================================

  async function handleScanProfile(options = {}) {
    if (isScanning) {
      return { success: false, error: 'Already scanning' };
    }

    const canScan = rateLimiter.canScan();
    if (!canScan.allowed) {
      return { success: false, error: canScan.reason, rateLimited: true };
    }

    const pageInfo = getPageInfo();
    if (!pageInfo.isProfilePage) {
      return { success: false, error: 'Please navigate to a profile page first' };
    }

    isScanning = true;
    const maxPosts = Math.min(
      options.maxPosts || CONFIG.safety.maxPostsPerScan,
      CONFIG.safety.maxPostsPerScan
    );
    const allComments = [];
    const postsScanned = [];

    try {
      updateScanStatus('Finding videos...');
      debugLog(`Starting profile scan (max ${maxPosts} videos)`);

      await loadMoreVideos(maxPosts);

      const videoLinks = findVideoLinks();
      debugLog(`Found ${videoLinks.length} videos`);

      if (videoLinks.length === 0) {
        return { success: false, error: 'No videos found on this profile' };
      }

      const shuffledVideos = shuffleArray([...videoLinks]).slice(0, maxPosts);

      for (let i = 0; i < shuffledVideos.length; i++) {
        const videoLink = shuffledVideos[i];
        updateScanStatus(`Scanning video ${i + 1}/${shuffledVideos.length}...`);

        try {
          await randomDelay(CONFIG.safety.delayBetweenPosts);

          videoLink.click();
          await randomDelay([1000, 2000]);

          await waitForElement(SELECTORS.commentSection, 3000);
          await loadAllComments();

          const comments = extractComments();
          const videoId = getPageInfo().videoId;

          if (comments.length > 0) {
            comments.forEach(c => {
              c.postId = videoId;
              c.postUrl = window.location.href;
            });

            allComments.push(...comments);
            postsScanned.push({
              postId: videoId,
              postUrl: window.location.href,
              commentsCount: comments.length,
            });
          }

          debugLog(`Video ${i + 1}: ${comments.length} comments`);

          closeVideo();
          await randomDelay(CONFIG.safety.delayAfterModal);
        } catch (err) {
          errorLog(`Error scanning video ${i + 1}:`, err);
          closeVideo();
          await sleep(500);
        }
      }

      await rateLimiter.recordScan(postsScanned.length);

      return {
        success: true,
        postsScanned: postsScanned.length,
        commentsCount: allComments.length,
        comments: allComments,
        posts: postsScanned,
        contentInfo: {
          profileUrl: window.location.href,
          username: getPageInfo().username,
        },
        rateLimit: rateLimiter.getStatus(),
      };
    } catch (err) {
      errorLog('Profile scan error:', err);
      return { success: false, error: err.message };
    } finally {
      isScanning = false;
      updateScanStatus('');
    }
  }

  async function loadMoreVideos(targetCount) {
    let lastCount = 0;
    let attempts = 0;
    const maxAttempts = 10;

    while (attempts < maxAttempts) {
      const currentCount = findVideoLinks().length;
      if (currentCount >= targetCount || currentCount === lastCount) break;

      lastCount = currentCount;
      const scrollAmount = randomBetween(300, 700);
      window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
      await randomDelay(CONFIG.safety.delayBetweenScrolls);
      attempts++;
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    await sleep(500);
  }

  function findVideoLinks() {
    const links = [];
    const selectors = [
      SELECTORS.profileVideos,
      SELECTORS.alt.profileVideos,
      'a[href*="/video/"]',
    ];

    for (const sel of selectors) {
      const found = document.querySelectorAll(sel);
      found.forEach(el => {
        const link = el.tagName === 'A' ? el : el.querySelector('a');
        if (link && link.href.includes('/video/')) {
          links.push(link);
        }
      });
    }

    // Deduplicate
    const unique = new Map();
    links.forEach(link => {
      if (!unique.has(link.href)) {
        unique.set(link.href, link);
      }
    });

    return Array.from(unique.values());
  }

  function closeVideo() {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27 }));

    const closeBtn = findElement(SELECTORS.closeButton) ||
                     document.querySelector('[aria-label="Close"]');
    if (closeBtn) {
      closeBtn.click();
    }
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
        await randomDelay(CONFIG.safety.delayBetweenDeletes);
      } catch (err) {
        results.push({
          id: comment.id,
          success: false,
          error: err.message,
        });
      }
    }

    return { success: true, deletedCount, results };
  }

  async function deleteComment(commentId) {
    const element = document.querySelector(`[data-delcom-id="${commentId}"]`);
    if (!element) {
      warnLog('Comment element not found:', commentId);
      return false;
    }

    try {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      await sleep(300);

      const moreBtn = element.querySelector(SELECTORS.commentOptions) ||
                      element.querySelector('[aria-label="More"]') ||
                      element.querySelector('button[class*="more"]');

      if (!moreBtn) {
        warnLog('More options button not found');
        return false;
      }

      moreBtn.click();
      await sleep(300);

      const deleteBtn = await waitForElement(SELECTORS.deleteButton, 2000);
      if (!deleteBtn) {
        const buttons = document.querySelectorAll(`${SELECTORS.modal} button, [role="menu"] button`);
        let foundDelete = false;

        for (const btn of buttons) {
          const btnText = btn.textContent?.toLowerCase() || '';
          if (btnText.includes('delete') || btnText.includes('hapus')) {
            btn.click();
            foundDelete = true;
            break;
          }
        }

        if (!foundDelete) {
          document.body.click();
          return false;
        }
      } else {
        deleteBtn.click();
      }

      await sleep(300);

      const confirmBtn = await waitForElement(SELECTORS.confirmButton, 2000);
      if (confirmBtn) {
        confirmBtn.click();
        await sleep(300);
      }

      element.classList.remove('delcom-spam-highlight');
      element.classList.add('delcom-deleted');

      debugLog('Comment deleted:', commentId);
      return true;
    } catch (err) {
      errorLog('Delete comment error:', err);
      return false;
    }
  }

  // ========================================
  // Comment Highlighting
  // ========================================

  async function highlightComments(commentIds) {
    spamCommentIds = new Set(commentIds);

    document.querySelectorAll('.delcom-spam-highlight').forEach(el => {
      el.classList.remove('delcom-spam-highlight');
    });

    commentIds.forEach(id => {
      const el = document.querySelector(`[data-delcom-id="${id}"]`);
      if (el) {
        el.classList.add('delcom-spam-highlight');
      }
    });

    return { success: true, highlightedCount: commentIds.length };
  }

  // ========================================
  // Helper Functions
  // ========================================

  function findElement(selector) {
    return document.querySelector(selector);
  }

  function updateScanStatus(message) {
    chrome.runtime.sendMessage({ action: 'scanStatus', message });
  }

  // ========================================
  // Initialize
  // ========================================

  function init() {
    debugLog('TikTok content script loaded');

    // Load rate limiter state
    rateLimiter.loadState();

    // Add custom styles
    const style = document.createElement('style');
    style.id = 'delcom-styles';
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
        background: #22c55e !important;
      }
    `;

    if (!document.getElementById('delcom-styles')) {
      document.head.appendChild(style);
    }

    // Watch for navigation (SPA)
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
