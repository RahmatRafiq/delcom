/**
 * Delcom Extension - Configuration Module
 *
 * Centralized configuration for all platforms.
 * Environment-aware settings.
 */

// Detect environment
const isDevelopment = !chrome.runtime.getManifest().update_url;

export const CONFIG = {
  // API Configuration - IMPORTANT: Change for production
  API_BASE: isDevelopment
    ? 'http://localhost:8000/api'
    : 'https://delcom.app/api',

  WEB_BASE: isDevelopment
    ? 'http://localhost:8000'
    : 'https://delcom.app',

  // Extension Info
  VERSION: chrome.runtime.getManifest().version,
  IS_DEV: isDevelopment,

  // Safety Limits (shared across platforms)
  safety: {
    maxPostsPerScan: 15,
    maxScansPerHour: 6,
    maxPostsPerDay: 150,
    delayBetweenPosts: [2000, 4000],
    delayAfterModal: [1000, 2500],
    delayBetweenScrolls: [500, 1500],
    cooldownAfterScan: 300000, // 5 minutes
    delayBetweenDeletes: [400, 800],
  },

  // Request Timeouts
  timeouts: {
    elementWait: 5000,
    modalWait: 3000,
    apiRequest: 30000,
  },

  // Debug Mode
  debug: isDevelopment,
};

/**
 * Log message if in debug mode
 */
export function debugLog(...args) {
  if (CONFIG.debug) {
    console.log('[Delcom]', ...args);
  }
}

/**
 * Log error (always)
 */
export function errorLog(...args) {
  console.error('[Delcom Error]', ...args);
}

/**
 * Log warning (always)
 */
export function warnLog(...args) {
  console.warn('[Delcom Warning]', ...args);
}

export default CONFIG;
