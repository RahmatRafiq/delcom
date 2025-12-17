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

      case 'deleteComments':
        handleDeleteComments(message.comments).then(sendResponse);
        return true;

      case 'highlightComments':
        highlightComments(message.commentIds).then(sendResponse);
        return true;

      case 'getPageInfo':
        sendResponse(getPageInfo());
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
    const isProfilePage = /instagram\.com\/[\w.]+\/?$/.test(url);

    let postId = null;
    const postMatch = url.match(/\/(?:p|reel)\/([\w-]+)/);
    if (postMatch) {
      postId = postMatch[1];
    }

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
