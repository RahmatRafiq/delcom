/**
 * Delcom Extension - Rate Limiter Module
 *
 * Manages rate limiting across platforms to prevent detection
 * and avoid overloading the target platform.
 */

import { CONFIG } from './config.js';

/**
 * Rate Limiter Class
 * Tracks scan activity and enforces limits
 */
export class RateLimiter {
  constructor(platformName) {
    this.platform = platformName;
    this.storageKey = `delcom_ratelimit_${platformName}`;

    // Initialize from storage or defaults
    this.lastScanTime = 0;
    this.scansThisHour = 0;
    this.postsToday = 0;
    this.hourStartTime = Date.now();
    this.dayStartTime = Date.now();

    // Load persisted state
    this.loadState();
  }

  /**
   * Load state from chrome storage
   */
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

        // Reset counters if time has passed
        this.resetIfNeeded();
      }
    } catch (err) {
      console.warn('RateLimiter: Failed to load state', err);
    }
  }

  /**
   * Save state to chrome storage
   */
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
      console.warn('RateLimiter: Failed to save state', err);
    }
  }

  /**
   * Reset counters if hour/day has passed
   */
  resetIfNeeded() {
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
  }

  /**
   * Check if scanning is allowed
   * @returns {{ allowed: boolean, reason?: string }}
   */
  canScan() {
    this.resetIfNeeded();
    const now = Date.now();
    const safety = CONFIG.safety;

    // Check cooldown
    if (now - this.lastScanTime < safety.cooldownAfterScan) {
      const remaining = Math.ceil(
        (safety.cooldownAfterScan - (now - this.lastScanTime)) / 1000
      );
      return {
        allowed: false,
        reason: `Cooldown: tunggu ${remaining} detik lagi`,
      };
    }

    // Check hourly limit
    if (this.scansThisHour >= safety.maxScansPerHour) {
      return {
        allowed: false,
        reason: `Limit: max ${safety.maxScansPerHour} scan per jam`,
      };
    }

    // Check daily limit
    if (this.postsToday >= safety.maxPostsPerDay) {
      return {
        allowed: false,
        reason: `Limit harian tercapai (${safety.maxPostsPerDay} posts)`,
      };
    }

    return { allowed: true };
  }

  /**
   * Record a completed scan
   * @param {number} postCount - Number of posts scanned
   */
  async recordScan(postCount = 1) {
    this.lastScanTime = Date.now();
    this.scansThisHour++;
    this.postsToday += postCount;
    await this.saveState();
  }

  /**
   * Get current rate limit status
   */
  getStatus() {
    this.resetIfNeeded();
    const safety = CONFIG.safety;

    return {
      platform: this.platform,
      scansThisHour: this.scansThisHour,
      maxScansPerHour: safety.maxScansPerHour,
      postsToday: this.postsToday,
      maxPostsPerDay: safety.maxPostsPerDay,
      lastScanTime: this.lastScanTime,
      cooldownRemaining: Math.max(
        0,
        safety.cooldownAfterScan - (Date.now() - this.lastScanTime)
      ),
    };
  }

  /**
   * Reset all limits (for testing)
   */
  async reset() {
    this.lastScanTime = 0;
    this.scansThisHour = 0;
    this.postsToday = 0;
    this.hourStartTime = Date.now();
    this.dayStartTime = Date.now();
    await this.saveState();
  }
}

/**
 * Create rate limiter instance for platform
 */
export function createRateLimiter(platformName) {
  return new RateLimiter(platformName);
}

export default RateLimiter;
