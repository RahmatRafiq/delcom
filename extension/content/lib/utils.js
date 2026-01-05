/**
 * Delcom Extension - Utility Functions
 *
 * Shared utility functions for all platforms.
 */

/**
 * Sleep for specified milliseconds
 */
export function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Random delay between min and max milliseconds
 * Used for human-like behavior
 */
export function randomDelay(range) {
  const [min, max] = Array.isArray(range) ? range : [range, range];
  const delay = Math.floor(Math.random() * (max - min + 1)) + min;
  return sleep(delay);
}

/**
 * Random integer between min and max (inclusive)
 */
export function randomBetween(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Shuffle array randomly (Fisher-Yates)
 */
export function shuffleArray(array) {
  const result = [...array];
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}

/**
 * Wait for element to appear in DOM
 */
export async function waitForElement(selector, timeout = 5000, context = document) {
  const startTime = Date.now();

  while (Date.now() - startTime < timeout) {
    const element = context.querySelector(selector);
    if (element) return element;
    await sleep(100);
  }

  return null;
}

/**
 * Wait for any of multiple selectors
 */
export async function waitForAny(selectors, timeout = 5000, context = document) {
  const startTime = Date.now();

  while (Date.now() - startTime < timeout) {
    for (const selector of selectors) {
      const element = context.querySelector(selector);
      if (element) return { element, selector };
    }
    await sleep(100);
  }

  return null;
}

/**
 * Safe click with retry
 */
export async function safeClick(element, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      if (typeof element === 'string') {
        element = document.querySelector(element);
      }

      if (!element) {
        await sleep(200);
        continue;
      }

      // Try regular click
      element.click();
      return true;
    } catch (err) {
      if (i === retries - 1) {
        console.warn('safeClick failed:', err);
        return false;
      }
      await sleep(200);
    }
  }
  return false;
}

/**
 * Scroll element into view with human-like behavior
 */
export async function smoothScrollTo(element, options = {}) {
  const { offset = 0, behavior = 'smooth' } = options;

  if (typeof element === 'string') {
    element = document.querySelector(element);
  }

  if (!element) return false;

  const rect = element.getBoundingClientRect();
  const scrollTop = window.scrollY + rect.top - offset;

  window.scrollTo({
    top: scrollTop,
    behavior,
  });

  await sleep(300);
  return true;
}

/**
 * Human-like scroll down
 */
export async function humanScroll(amount = null, delayRange = [500, 1500]) {
  const scrollAmount = amount || randomBetween(300, 700);
  window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
  await randomDelay(delayRange);
}

/**
 * Generate unique ID for comment tracking
 */
export function generateCommentId(platform, username, index) {
  return `${platform}_${username}_${index}_${Date.now()}`;
}

/**
 * Truncate text safely
 */
export function truncate(text, maxLength = 100) {
  if (!text || text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
}

/**
 * Debounce function
 */
export function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Check if element is visible in viewport
 */
export function isElementVisible(element) {
  if (!element) return false;

  const rect = element.getBoundingClientRect();
  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
}

/**
 * Parse URL to extract platform-specific data
 */
export function parseUrl(url) {
  try {
    const urlObj = new URL(url);
    return {
      hostname: urlObj.hostname,
      pathname: urlObj.pathname,
      search: urlObj.search,
      hash: urlObj.hash,
    };
  } catch {
    return null;
  }
}
