/**
 * Delcom Extension - Base Platform Module
 *
 * Abstract base class for platform-specific content scripts.
 * Each platform (Instagram, TikTok, YouTube) extends this class.
 */

import { CONFIG, debugLog, errorLog, warnLog } from './config.js';
import { RateLimiter } from './rate-limiter.js';
import { getApiClient } from './api-client.js';
import {
  sleep,
  randomDelay,
  waitForElement,
  generateCommentId,
} from './utils.js';

/**
 * Base Platform Class
 * Provides common functionality for all platform content scripts.
 */
export class BasePlatform {
  constructor(platformName) {
    this.platform = platformName;
    this.rateLimiter = new RateLimiter(platformName);
    this.api = getApiClient();
    this.isScanning = false;
    this.spamCommentIds = new Set();
    this.connectionId = null;

    // Selectors - override in subclass
    this.selectors = {};

    debugLog(`${this.platform} platform initialized`);
  }

  // ========================================
  // Abstract Methods - Must Override
  // ========================================

  /**
   * Get page information (type, IDs, etc)
   * @returns {Object} Page info
   */
  getPageInfo() {
    throw new Error('getPageInfo() must be implemented');
  }

  /**
   * Extract comments from page
   * @returns {Array} Comments array
   */
  extractComments() {
    throw new Error('extractComments() must be implemented');
  }

  /**
   * Delete a single comment by ID
   * @param {string} commentId
   * @returns {Promise<boolean>}
   */
  async deleteComment(commentId) {
    throw new Error('deleteComment() must be implemented');
  }

  /**
   * Get content info (title, URL, etc)
   * @returns {Object}
   */
  getContentInfo() {
    throw new Error('getContentInfo() must be implemented');
  }

  // ========================================
  // Common Methods
  // ========================================

  /**
   * Initialize the platform script
   */
  async init() {
    debugLog(`Initializing ${this.platform} content script`);

    // Load connection ID from storage
    await this.loadConnectionId();

    // Setup message listener
    this.setupMessageListener();

    // Add custom styles
    this.injectStyles();

    // Watch for navigation changes (SPAs)
    this.watchNavigation();

    debugLog(`${this.platform} initialized successfully`);
  }

  /**
   * Load connection ID from storage
   */
  async loadConnectionId() {
    const key = `${this.platform}ConnectionId`;
    const result = await chrome.storage.local.get([key]);
    this.connectionId = result[key] || null;
    return this.connectionId;
  }

  /**
   * Setup Chrome runtime message listener
   */
  setupMessageListener() {
    chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
      this.handleMessage(message, sender, sendResponse);
      return true; // Keep channel open for async
    });
  }

  /**
   * Handle incoming messages
   */
  async handleMessage(message, sender, sendResponse) {
    try {
      switch (message.action) {
        case 'scan':
          const scanResult = await this.handleScan();
          sendResponse(scanResult);
          break;

        case 'scanProfile':
          const profileResult = await this.handleScanProfile(message.options || {});
          sendResponse(profileResult);
          break;

        case 'deleteComments':
          const deleteResult = await this.handleDeleteComments(message.comments);
          sendResponse(deleteResult);
          break;

        case 'highlightComments':
          const highlightResult = await this.highlightComments(message.commentIds);
          sendResponse(highlightResult);
          break;

        case 'getPageInfo':
          sendResponse(this.getPageInfo());
          break;

        case 'getRateLimitStatus':
          sendResponse({
            canScan: this.rateLimiter.canScan(),
            status: this.rateLimiter.getStatus(),
          });
          break;

        default:
          // Allow subclasses to handle custom messages
          if (this.handleCustomMessage) {
            await this.handleCustomMessage(message, sender, sendResponse);
          } else {
            sendResponse({ success: false, error: 'Unknown action' });
          }
      }
    } catch (err) {
      errorLog('Message handling error:', err);
      sendResponse({ success: false, error: err.message });
    }
  }

  /**
   * Handle single post/page scan
   */
  async handleScan() {
    if (this.isScanning) {
      return { success: false, error: 'Already scanning' };
    }

    this.isScanning = true;

    try {
      const pageInfo = this.getPageInfo();

      if (!pageInfo.isContentPage) {
        return { success: false, error: 'Please open a post/video to scan comments' };
      }

      // Wait for comments to load
      await this.waitForComments();

      // Load more comments if available
      await this.loadAllComments();

      // Extract comments
      const comments = this.extractComments();
      const contentInfo = this.getContentInfo();

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
      this.isScanning = false;
    }
  }

  /**
   * Handle profile/channel scan (multiple posts)
   */
  async handleScanProfile(options = {}) {
    if (this.isScanning) {
      return { success: false, error: 'Already scanning' };
    }

    // Check rate limits
    const canScan = this.rateLimiter.canScan();
    if (!canScan.allowed) {
      return { success: false, error: canScan.reason, rateLimited: true };
    }

    const pageInfo = this.getPageInfo();
    if (!pageInfo.isProfilePage) {
      return { success: false, error: 'Please navigate to a profile page first' };
    }

    this.isScanning = true;

    try {
      // Subclass should implement the actual scanning logic
      const result = await this.scanProfile(options);

      // Record scan for rate limiting
      await this.rateLimiter.recordScan(result.postsScanned || 1);

      return {
        ...result,
        rateLimit: this.rateLimiter.getStatus(),
      };
    } catch (err) {
      errorLog('Profile scan error:', err);
      return { success: false, error: err.message };
    } finally {
      this.isScanning = false;
      this.updateScanStatus('');
    }
  }

  /**
   * Scan profile - Override in subclass
   */
  async scanProfile(options) {
    throw new Error('scanProfile() must be implemented');
  }

  /**
   * Handle comment deletions
   */
  async handleDeleteComments(comments) {
    const results = [];
    let deletedCount = 0;

    for (const comment of comments) {
      try {
        const success = await this.deleteComment(comment.id);
        results.push({
          id: comment.id,
          success,
          error: success ? null : 'Failed to delete',
        });

        if (success) deletedCount++;

        // Wait between deletions
        await randomDelay(CONFIG.safety.delayBetweenDeletes);
      } catch (err) {
        results.push({
          id: comment.id,
          success: false,
          error: err.message,
        });
      }
    }

    return {
      success: true,
      deletedCount,
      results,
    };
  }

  /**
   * Highlight spam comments
   */
  async highlightComments(commentIds) {
    this.spamCommentIds = new Set(commentIds);

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

  /**
   * Wait for comments section to load
   */
  async waitForComments() {
    if (this.selectors.commentSection) {
      await waitForElement(this.selectors.commentSection, CONFIG.timeouts.elementWait);
    }
  }

  /**
   * Load all comments (click "load more")
   */
  async loadAllComments() {
    // Override in subclass if needed
    if (!this.selectors.moreComments) return;

    let attempts = 0;
    const maxAttempts = 10;

    while (attempts < maxAttempts) {
      const loadMoreBtn = document.querySelector(this.selectors.moreComments);
      if (!loadMoreBtn) break;

      loadMoreBtn.click();
      await randomDelay([400, 800]);
      attempts++;
    }
  }

  /**
   * Update scan status (send to popup)
   */
  updateScanStatus(message) {
    chrome.runtime.sendMessage({ action: 'scanStatus', message });
  }

  /**
   * Inject custom CSS styles
   */
  injectStyles() {
    if (document.getElementById('delcom-styles')) return;

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

      .delcom-scanning {
        animation: delcom-pulse 1.5s ease-in-out infinite;
      }

      @keyframes delcom-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
      }
    `;
    document.head.appendChild(style);
  }

  /**
   * Watch for SPA navigation changes
   */
  watchNavigation() {
    let lastUrl = location.href;

    new MutationObserver(() => {
      if (location.href !== lastUrl) {
        lastUrl = location.href;
        this.onNavigate();
      }
    }).observe(document.body, { subtree: true, childList: true });
  }

  /**
   * Called when URL changes (SPA navigation)
   */
  onNavigate() {
    // Clear highlights
    this.spamCommentIds.clear();
    document.querySelectorAll('.delcom-spam-highlight, .delcom-deleted').forEach(el => {
      el.classList.remove('delcom-spam-highlight', 'delcom-deleted');
    });

    debugLog('Navigation detected, cleared highlights');
  }
}

export default BasePlatform;
