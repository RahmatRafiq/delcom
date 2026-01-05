/**
 * Delcom Extension - API Client Module
 *
 * Handles all API communication with the Delcom backend.
 */

import { CONFIG, debugLog, errorLog } from './config.js';

/**
 * API Client Class
 */
export class ApiClient {
  constructor() {
    this.baseUrl = CONFIG.API_BASE;
    this.token = null;
  }

  /**
   * Load auth token from storage
   */
  async loadToken() {
    try {
      const result = await chrome.storage.local.get(['authToken']);
      this.token = result.authToken || null;
      return this.token;
    } catch (err) {
      errorLog('Failed to load auth token:', err);
      return null;
    }
  }

  /**
   * Check if user is authenticated
   */
  async isAuthenticated() {
    const token = await this.loadToken();
    return !!token;
  }

  /**
   * Make API request
   */
  async request(method, endpoint, body = null, options = {}) {
    await this.loadToken();

    const url = `${this.baseUrl}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Extension-Version': CONFIG.VERSION,
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const fetchOptions = {
      method,
      headers,
      ...options,
    };

    if (body && method !== 'GET') {
      fetchOptions.body = JSON.stringify(body);
    }

    debugLog(`API ${method} ${endpoint}`);

    try {
      const response = await fetch(url, fetchOptions);
      const data = await response.json();

      if (!response.ok) {
        errorLog(`API Error ${response.status}:`, data);
        return {
          success: false,
          error: data.message || data.error || 'Request failed',
          status: response.status,
        };
      }

      return data;
    } catch (err) {
      errorLog('API Request failed:', err);
      return {
        success: false,
        error: err.message || 'Network error',
      };
    }
  }

  /**
   * GET request
   */
  get(endpoint, options = {}) {
    return this.request('GET', endpoint, null, options);
  }

  /**
   * POST request
   */
  post(endpoint, body = {}, options = {}) {
    return this.request('POST', endpoint, body, options);
  }

  /**
   * PUT request
   */
  put(endpoint, body = {}, options = {}) {
    return this.request('PUT', endpoint, body, options);
  }

  /**
   * DELETE request
   */
  delete(endpoint, body = null, options = {}) {
    return this.request('DELETE', endpoint, body, options);
  }

  // ========================================
  // Extension-specific endpoints
  // ========================================

  /**
   * Verify extension connection
   */
  async verify() {
    return this.get('/extension/verify');
  }

  /**
   * Get user info
   */
  async getUser() {
    return this.get('/auth/me');
  }

  /**
   * Save comments to review queue
   */
  async saveComments(connectionId, contentId, contentTitle, contentUrl, comments) {
    return this.post('/extension/save-all', {
      connection_id: connectionId,
      content_id: contentId,
      content_title: contentTitle,
      content_url: contentUrl,
      comments: comments.map(c => ({
        id: c.id,
        text: c.text,
        author_username: c.username,
        author_id: c.userId || null,
        author_profile_url: c.profileUrl || null,
        created_at: c.timestamp || null,
        post_id: c.postId || null,
        post_url: c.postUrl || null,
      })),
    });
  }

  /**
   * Analyze comments for spam
   */
  async analyzeComments(connectionId, comments) {
    return this.post('/extension/analyze', {
      connection_id: connectionId,
      comments,
    });
  }

  /**
   * Report completed deletions
   */
  async reportDeletions(connectionId, results) {
    return this.post('/extension/report-deletions', {
      connection_id: connectionId,
      results,
    });
  }

  /**
   * Get filter rules for platform
   */
  async getFilters(platformId) {
    return this.get(`/extension/filters?platform_id=${platformId}`);
  }

  /**
   * Log action for analytics
   */
  async logAction(action, data = {}) {
    return this.post('/extension/log-action', {
      action,
      data,
      timestamp: new Date().toISOString(),
    });
  }
}

// Singleton instance
let apiClientInstance = null;

/**
 * Get API client instance
 */
export function getApiClient() {
  if (!apiClientInstance) {
    apiClientInstance = new ApiClient();
  }
  return apiClientInstance;
}

export default ApiClient;
