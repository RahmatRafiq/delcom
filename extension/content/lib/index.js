/**
 * Delcom Extension - Library Index
 *
 * Central export for all shared modules.
 */

export { CONFIG, debugLog, errorLog, warnLog } from './config.js';
export { RateLimiter, createRateLimiter } from './rate-limiter.js';
export { ApiClient, getApiClient } from './api-client.js';
export { BasePlatform } from './base-platform.js';
export {
  sleep,
  randomDelay,
  randomBetween,
  shuffleArray,
  waitForElement,
  waitForAny,
  safeClick,
  smoothScrollTo,
  humanScroll,
  generateCommentId,
  truncate,
  debounce,
  isElementVisible,
  parseUrl,
} from './utils.js';
