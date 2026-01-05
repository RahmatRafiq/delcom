/* eslint-env webextensions */
/**
 * Delcom Extension - Popup Script
 */

// Environment detection
const isDevelopment = !chrome.runtime.getManifest().update_url;

const CONFIG = {
  API_BASE: isDevelopment
    ? 'http://127.0.0.1:8000/api'
    : 'https://delcom.app/api',
  WEB_BASE: isDevelopment
    ? 'http://127.0.0.1:8000'
    : 'https://delcom.app',
  VERSION: chrome.runtime.getManifest().version,
  IS_DEV: isDevelopment,
};

const API_BASE = CONFIG.API_BASE;

// DOM Elements
const loginView = document.getElementById('login-view');
const dashboardView = document.getElementById('dashboard-view');
const loginForm = document.getElementById('login-form');
const loginBtn = document.getElementById('login-btn');
const loginError = document.getElementById('login-error');
const logoutBtn = document.getElementById('logout-btn');
const scanBtn = document.getElementById('scan-btn');
const openDashboardBtn = document.getElementById('open-dashboard');
const scanningOverlay = document.getElementById('scanning-overlay');
const cancelScanBtn = document.getElementById('cancel-scan');
const statusMessage = document.getElementById('status-message');

// State
let currentUser = null;
let authToken = null;

// ========================================
// Initialization
// ========================================

document.addEventListener('DOMContentLoaded', async () => {
  await checkAuth();
  setupEventListeners();
});

async function checkAuth() {
  const stored = await chrome.storage.local.get(['authToken', 'user']);

  if (stored.authToken && stored.user) {
    authToken = stored.authToken;
    currentUser = stored.user;

    // Verify token is still valid
    try {
      const response = await apiRequest('GET', '/auth/me');
      if (response.success) {
        currentUser = response.user;
        await chrome.storage.local.set({ user: currentUser });
        showDashboard();
        return;
      }
    } catch (error) {
      // Token expired, clear storage
      await chrome.storage.local.remove(['authToken', 'user']);
    }
  }

  showLogin();
}

// ========================================
// Event Listeners
// ========================================

function setupEventListeners() {
  // Login form
  loginForm.addEventListener('submit', handleLogin);

  // Google OAuth login
  document.getElementById('google-login-btn').addEventListener('click', handleGoogleLogin);

  // Logout
  logoutBtn.addEventListener('click', handleLogout);

  // Scan button
  scanBtn.addEventListener('click', handleScan);

  // Open dashboard
  openDashboardBtn.addEventListener('click', () => {
    chrome.tabs.create({ url: `${CONFIG.WEB_BASE}/dashboard` });
  });

  // Cancel scan
  cancelScanBtn.addEventListener('click', () => {
    scanningOverlay.classList.add('hidden');
  });

  // Open register
  document.getElementById('open-register').addEventListener('click', (e) => {
    e.preventDefault();
    chrome.tabs.create({ url: `${CONFIG.WEB_BASE}/register` });
  });
}

// ========================================
// Authentication
// ========================================

async function handleGoogleLogin() {
  // Open the SSO authorize page
  // If user is logged in on web, they just need to click "Authorize"
  // If not logged in, they'll be redirected to login first
  const authUrl = `${CONFIG.WEB_BASE}/extension/authorize`;

  // Store a flag so we know we're waiting for auth
  await chrome.storage.local.set({ pendingAuth: true });

  chrome.tabs.create({ url: authUrl });

  // Don't close popup immediately - show a message
  showStatus('Please complete login in the new tab, then reopen extension.', 'info');
}

async function handleLogin(e) {
  e.preventDefault();

  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;

  loginBtn.classList.add('loading');
  hideError();

  try {
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ email, password }),
    });

    const data = await response.json();

    if (data.success) {
      authToken = data.access_token;
      currentUser = data.user;

      // Store in chrome storage
      await chrome.storage.local.set({
        authToken: authToken,
        user: currentUser,
      });

      // Fetch full user info
      const userInfo = await apiRequest('GET', '/auth/me');
      if (userInfo.success) {
        currentUser = userInfo.user;
        await chrome.storage.local.set({ user: currentUser });
      }

      showDashboard();
    } else {
      showError(data.error || 'Login failed');
    }
  } catch (error) {
    showError('Connection failed. Please try again.');
    console.error('Login error:', error);
  } finally {
    loginBtn.classList.remove('loading');
  }
}

async function handleLogout() {
  try {
    await apiRequest('POST', '/auth/logout');
  } catch (error) {
    // Ignore logout errors
  }

  await chrome.storage.local.remove(['authToken', 'user']);
  authToken = null;
  currentUser = null;
  showLogin();
}

// ========================================
// Platform Connection
// ========================================

/**
 * Auto-connect a platform via extension
 * Called when user tries to scan but hasn't connected this platform yet
 */
async function autoConnectPlatform(platform, username) {
  try {
    showStatus(`Connecting ${platform} account @${username}...`, 'info');

    const response = await apiRequest('POST', '/extension/connect', {
      platform: platform,
      username: username,
      user_id: null, // We don't have platform user ID from extension
    });

    if (response.success && response.connection_id) {
      // Store connection ID
      const connectionKey = `${platform}ConnectionId`;
      await chrome.storage.local.set({ [connectionKey]: response.connection_id });

      showStatus(`Connected @${username} successfully!`, 'success');

      // Refresh platforms list
      await loadPlatforms();

      return true;
    } else {
      // Handle upgrade required
      if (response.upgrade_required) {
        showStatus(response.error || 'Please upgrade your plan to access this platform.', 'error');
      } else {
        showStatus(response.error || 'Failed to connect platform.', 'error');
      }
      console.error('Auto-connect failed:', response.error);
      return false;
    }
  } catch (error) {
    console.error('Auto-connect error:', error);
    showStatus('Failed to connect. Please try again.', 'error');
    return false;
  }
}

// ========================================
// Scanning
// ========================================

async function handleScan() {
  // Get current tab
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  // Check if on supported platform
  const isInstagram = tab.url.includes('instagram.com');
  const isTikTok = tab.url.includes('tiktok.com');

  if (!isInstagram && !isTikTok) {
    showStatus('Please navigate to Instagram or TikTok first', 'error');
    return;
  }

  // Check if user can perform actions (quota check)
  if (currentUser && currentUser.can_moderate === false) {
    showStatus('Daily or monthly limit reached. Please upgrade your plan.', 'error');
    return;
  }

  const platform = isInstagram ? 'instagram' : 'tiktok';
  const connectionKey = `${platform}ConnectionId`;

  // Check if platform is connected
  const stored = await chrome.storage.local.get([connectionKey]);
  if (!stored[connectionKey]) {
    // Try to auto-connect by getting username from page
    const pageInfo = await chrome.tabs.sendMessage(tab.id, { action: 'getPageInfo' });

    if (pageInfo.username && pageInfo.username !== 'unknown') {
      // Auto-connect this platform
      const connected = await autoConnectPlatform(platform, pageInfo.username);
      if (!connected) {
        showStatus(`Could not connect ${platform}. Please try again.`, 'error');
        return;
      }
    } else {
      // No username found - user needs to be on their profile page
      const platformName = platform.charAt(0).toUpperCase() + platform.slice(1);
      showStatus(`Please navigate to your ${platformName} profile page first, or connect via the dashboard.`, 'error');
      return;
    }
  }

  // Show scanning overlay
  scanningOverlay.classList.remove('hidden');
  document.getElementById('scan-progress').textContent = 'Analyzing page...';

  try {
    // First get page info to determine scan type
    const pageInfo = await chrome.tabs.sendMessage(tab.id, { action: 'getPageInfo' });

    let response;

    if (pageInfo.isProfilePage) {
      // Profile page - scan all posts
      document.getElementById('scan-progress').textContent = 'Scanning profile posts...';
      response = await chrome.tabs.sendMessage(tab.id, {
        action: 'scanProfile',
        options: { maxPosts: 20 } // Limit to 20 posts for now
      });

      if (response.success) {
        document.getElementById('scan-progress').textContent =
          `Found ${response.commentsCount} comments from ${response.postsScanned} posts...`;

        if (response.comments && response.comments.length > 0) {
          await processProfileComments(response.comments, response.contentInfo, response.posts, platform);
        } else {
          showStatus(`Scanned ${response.postsScanned} posts. No comments found.`, 'info');
        }
      }
    } else if (pageInfo.isPostPage || pageInfo.isReelPage || pageInfo.isVideoPage) {
      // Single post/video - scan this content only
      response = await chrome.tabs.sendMessage(tab.id, { action: 'scan' });

      if (response.success) {
        document.getElementById('scan-progress').textContent = `Found ${response.commentsCount} comments...`;

        if (response.comments && response.comments.length > 0) {
          await processComments(response.comments, response.contentInfo, platform);
        } else {
          showStatus('No comments found on this post', 'info');
        }
      }
    } else {
      showStatus('Please open a profile or post to scan', 'info');
      return;
    }

    if (response && !response.success) {
      showStatus(response.error || 'Scan failed', 'error');
    }
  } catch (error) {
    console.error('Scan error:', error);
    showStatus('Could not scan page. Please refresh and try again.', 'error');
  } finally {
    scanningOverlay.classList.add('hidden');
  }
}

// Listen for scan status updates from content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'scanStatus' && message.message) {
    const progressEl = document.getElementById('scan-progress');
    if (progressEl) {
      progressEl.textContent = message.message;
    }
  }
});

async function processComments(comments, contentInfo, platform = 'instagram') {
  try {
    console.log('Delcom: processComments called with', comments.length, 'comments');

    // Get connection ID from storage
    const connectionKey = `${platform}ConnectionId`;
    const stored = await chrome.storage.local.get([connectionKey]);
    const connectionId = stored[connectionKey];

    if (!connectionId) {
      showStatus(`Please connect your ${platform.charAt(0).toUpperCase() + platform.slice(1)} account first`, 'error');
      return;
    }

    // Step 1: Fetch user's filters from API
    document.getElementById('scan-progress').textContent = 'Loading filters...';
    console.log(`Delcom: Fetching filters for ${platform}...`);
    const filtersResponse = await apiRequest('GET', `/extension/filters?platform=${platform}`);
    console.log('Delcom: Filters API response:', JSON.stringify(filtersResponse));
    const filters = filtersResponse.success ? filtersResponse.filters : [];

    console.log(`Delcom: Loaded ${filters.length} filters for ${platform}`);
    if (filters.length > 0) {
      console.log('Delcom: Filters:', JSON.stringify(filters));
    }

    if (filters.length === 0) {
      // No filters - just save all to review queue (old behavior)
      document.getElementById('scan-progress').textContent = 'No filters configured. Saving to review queue...';
      await saveAllToQueue(comments, contentInfo, connectionId, platform);
      return;
    }

    // Step 2: Match comments against filters locally
    document.getElementById('scan-progress').textContent = 'Matching comments against filters...';
    console.log('Delcom: Comments to match:', comments.map(c => c.text));
    const { toDelete, toReview, clean } = matchCommentsWithFilters(comments, filters);

    console.log(`Delcom: Matched - Delete: ${toDelete.length}, Review: ${toReview.length}, Clean: ${clean.length}`);
    if (toDelete.length > 0) {
      console.log('Delcom: Comments to delete:', toDelete.map(c => c.text));
    }

    // Step 3: Auto-delete comments that match delete action
    let deletedCount = 0;
    if (toDelete.length > 0) {
      document.getElementById('scan-progress').textContent = `Deleting ${toDelete.length} spam comments...`;

      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      const deleteResult = await chrome.tabs.sendMessage(tab.id, {
        action: 'deleteComments',
        comments: toDelete,
      });

      if (deleteResult.success) {
        deletedCount = deleteResult.deletedCount;
        console.log(`Delcom: Deleted ${deletedCount} comments`);

        // Report deletions to API
        await reportDeletions(deleteResult.results, platform);
      }
    }

    // Step 4: Save review comments to queue
    let savedCount = 0;
    if (toReview.length > 0) {
      document.getElementById('scan-progress').textContent = `Saving ${toReview.length} comments for review...`;

      const saveResponse = await apiRequest('POST', '/extension/save-all', {
        connection_id: connectionId,
        content_id: contentInfo.postId || contentInfo.videoId,
        content_title: contentInfo.caption || `${platform.charAt(0).toUpperCase() + platform.slice(1)} Post`,
        content_url: contentInfo.url,
        comments: toReview.map(c => ({
          id: c.id,
          text: c.text,
          author_username: c.username,
          author_id: c.userId || null,
          author_profile_url: c.profileUrl || null,
          created_at: c.timestamp || null,
          matched_filter_id: c.matchedFilter?.id || null,
        })),
      });

      if (saveResponse.success) {
        savedCount = saveResponse.saved;
      }
    }

    // Step 5: Show results
    const messages = [];
    if (deletedCount > 0) messages.push(`Deleted ${deletedCount} spam`);
    if (savedCount > 0) messages.push(`${savedCount} for review`);
    if (clean.length > 0) messages.push(`${clean.length} clean`);

    if (messages.length > 0) {
      showStatus(messages.join(', '), deletedCount > 0 ? 'success' : 'info');
    } else {
      showStatus('No comments processed', 'info');
    }

    // Update stats
    const stats = await chrome.storage.local.get(['stats']);
    const currentStats = stats.stats || { scanned: 0, deleted: 0 };
    currentStats.scanned += comments.length;
    currentStats.deleted += deletedCount;
    await chrome.storage.local.set({ stats: currentStats });
    updateStats();

  } catch (error) {
    console.error('Process comments error:', error);
    showStatus('Failed to process comments: ' + error.message, 'error');
  }
}

/**
 * Match comments against user's filters
 * Returns { toDelete: [], toReview: [], clean: [] }
 */
function matchCommentsWithFilters(comments, filters) {
  const toDelete = [];
  const toReview = [];
  const clean = [];

  for (const comment of comments) {
    let matched = false;

    for (const filter of filters) {
      if (matchesFilter(comment.text, filter)) {
        comment.matchedFilter = filter;

        if (filter.action === 'delete') {
          toDelete.push(comment);
        } else {
          // action = 'review' or anything else
          toReview.push(comment);
        }

        matched = true;
        break; // Stop at first match
      }
    }

    if (!matched) {
      clean.push(comment);
    }
  }

  return { toDelete, toReview, clean };
}

/**
 * Check if comment text matches a filter pattern
 */
function matchesFilter(text, filter) {
  if (!text || !filter.pattern) {
    console.log(`Delcom: matchesFilter - text or pattern empty. text="${text}", pattern="${filter?.pattern}"`);
    return false;
  }

  const normalizedText = filter.case_sensitive ? text : text.toLowerCase();
  const pattern = filter.case_sensitive ? filter.pattern : filter.pattern.toLowerCase();

  try {
    let result = false;
    if (filter.is_regex || filter.type === 'regex') {
      // Regex matching
      const regex = new RegExp(pattern, filter.case_sensitive ? '' : 'i');
      result = regex.test(text);
    } else {
      // Keyword matching (contains)
      result = normalizedText.includes(pattern);
    }

    console.log(`Delcom: matchesFilter - text="${text.substring(0, 50)}...", pattern="${pattern}", result=${result}`);
    return result;
  } catch (err) {
    console.error('Filter match error:', err);
    return false;
  }
}

/**
 * Fallback: Save all comments to review queue (when no filters configured)
 */
async function saveAllToQueue(comments, contentInfo, connectionId, platform) {
  const response = await apiRequest('POST', '/extension/save-all', {
    connection_id: connectionId,
    content_id: contentInfo.postId || contentInfo.videoId,
    content_title: contentInfo.caption || `${platform.charAt(0).toUpperCase() + platform.slice(1)} Post`,
    content_url: contentInfo.url,
    comments: comments.map(c => ({
      id: c.id,
      text: c.text,
      author_username: c.username,
      author_id: c.userId || null,
      author_profile_url: c.profileUrl || null,
      created_at: c.timestamp || null,
    })),
  });

  if (response.success) {
    const { total, saved, skipped } = response;

    if (saved > 0) {
      showStatus(`Saved ${saved} comments to review queue!` + (skipped > 0 ? ` (${skipped} duplicates)` : ''), 'success');
    } else if (skipped > 0) {
      showStatus(`All ${skipped} comments already in queue`, 'info');
    } else {
      showStatus('No comments to save', 'info');
    }

    // Update stats
    const stats = await chrome.storage.local.get(['stats']);
    const currentStats = stats.stats || { scanned: 0, deleted: 0 };
    currentStats.scanned += total;
    await chrome.storage.local.set({ stats: currentStats });
    updateStats();
  } else {
    if (response.quota_exceeded) {
      showStatus('Daily or monthly limit reached. Please upgrade your plan.', 'error');
      if (currentUser) {
        currentUser.can_moderate = false;
      }
    } else {
      showStatus(response.error || 'Failed to save comments', 'error');
    }
  }
}

async function processProfileComments(comments, contentInfo, posts, platform = 'instagram') {
  try {
    // Get connection ID from storage
    const connectionKey = `${platform}ConnectionId`;
    const stored = await chrome.storage.local.get([connectionKey]);
    const connectionId = stored[connectionKey];

    if (!connectionId) {
      showStatus(`Please connect your ${platform.charAt(0).toUpperCase() + platform.slice(1)} account first`, 'error');
      return;
    }

    // Step 1: Fetch user's filters from API
    document.getElementById('scan-progress').textContent = 'Loading filters...';
    const filtersResponse = await apiRequest('GET', `/extension/filters?platform=${platform}`);
    const filters = filtersResponse.success ? filtersResponse.filters : [];

    console.log(`Delcom: Loaded ${filters.length} filters for ${platform}`);

    if (filters.length === 0) {
      // No filters - save all to review queue
      document.getElementById('scan-progress').textContent = `No filters. Saving ${comments.length} comments...`;
      await saveProfileCommentsToQueue(comments, contentInfo, posts, connectionId, platform);
      return;
    }

    // Step 2: Match comments against filters locally
    document.getElementById('scan-progress').textContent = 'Matching comments against filters...';
    const { toDelete, toReview, clean } = matchCommentsWithFilters(comments, filters);

    console.log(`Delcom: Profile matched - Delete: ${toDelete.length}, Review: ${toReview.length}, Clean: ${clean.length}`);

    // Step 3: Auto-delete comments that match delete action
    // NOTE: For profile scan, we need to re-open each post modal to delete
    // This is complex - for now, we'll just mark them for deletion and let user handle
    let deletedCount = 0;
    if (toDelete.length > 0) {
      // For profile scans, deletion is more complex as we need to navigate to each post
      // For now, add them to review queue with a "delete" flag so user can bulk delete from web
      document.getElementById('scan-progress').textContent = `Found ${toDelete.length} spam comments. Adding to queue for deletion...`;

      // Mark these for auto-delete when user opens from web dashboard
      const deleteResponse = await apiRequest('POST', '/extension/save-all', {
        connection_id: connectionId,
        content_id: `profile_${contentInfo.username}`,
        content_title: `@${contentInfo.username} Profile`,
        content_url: contentInfo.profileUrl,
        comments: toDelete.map(c => ({
          id: c.id,
          text: c.text,
          author_username: c.username,
          author_id: c.userId || null,
          author_profile_url: c.profileUrl || null,
          created_at: c.timestamp || null,
          post_id: c.postId || null,
          post_url: c.postUrl || null,
          matched_filter_id: c.matchedFilter?.id || null,
          auto_action: 'delete', // Mark for auto-delete
        })),
      });

      if (deleteResponse.success) {
        deletedCount = deleteResponse.saved;
      }
    }

    // Step 4: Save review comments to queue
    let savedCount = 0;
    if (toReview.length > 0) {
      document.getElementById('scan-progress').textContent = `Saving ${toReview.length} comments for review...`;

      const saveResponse = await apiRequest('POST', '/extension/save-all', {
        connection_id: connectionId,
        content_id: `profile_${contentInfo.username}`,
        content_title: `@${contentInfo.username} Profile`,
        content_url: contentInfo.profileUrl,
        comments: toReview.map(c => ({
          id: c.id,
          text: c.text,
          author_username: c.username,
          author_id: c.userId || null,
          author_profile_url: c.profileUrl || null,
          created_at: c.timestamp || null,
          post_id: c.postId || null,
          post_url: c.postUrl || null,
          matched_filter_id: c.matchedFilter?.id || null,
        })),
      });

      if (saveResponse.success) {
        savedCount = saveResponse.saved;
      }
    }

    // Step 5: Show results
    const messages = [];
    if (deletedCount > 0) messages.push(`${deletedCount} spam queued for deletion`);
    if (savedCount > 0) messages.push(`${savedCount} for review`);
    if (clean.length > 0) messages.push(`${clean.length} clean`);

    if (messages.length > 0) {
      showStatus(`${posts.length} posts: ` + messages.join(', '), deletedCount > 0 ? 'success' : 'info');
    } else {
      showStatus(`Scanned ${posts.length} posts. No comments to process.`, 'info');
    }

    // Update stats
    const stats = await chrome.storage.local.get(['stats']);
    const currentStats = stats.stats || { scanned: 0, deleted: 0 };
    currentStats.scanned += comments.length;
    await chrome.storage.local.set({ stats: currentStats });
    updateStats();

  } catch (error) {
    console.error('Process profile comments error:', error);
    showStatus('Failed to process comments: ' + error.message, 'error');
  }
}

/**
 * Fallback: Save profile comments to queue (when no filters configured)
 */
async function saveProfileCommentsToQueue(comments, contentInfo, posts, connectionId, platform) {
  const response = await apiRequest('POST', '/extension/save-all', {
    connection_id: connectionId,
    content_id: `profile_${contentInfo.username}`,
    content_title: `@${contentInfo.username} Profile`,
    content_url: contentInfo.profileUrl,
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

  if (response.success) {
    const { saved, skipped } = response;

    if (saved > 0) {
      showStatus(
        `Saved ${saved} comments from ${posts.length} posts!` +
        (skipped > 0 ? ` (${skipped} duplicates)` : ''),
        'success'
      );
    } else if (skipped > 0) {
      showStatus(`All ${skipped} comments already in queue`, 'info');
    } else {
      showStatus('No new comments to save', 'info');
    }

    // Update stats
    const stats = await chrome.storage.local.get(['stats']);
    const currentStats = stats.stats || { scanned: 0, deleted: 0 };
    currentStats.scanned += comments.length;
    await chrome.storage.local.set({ stats: currentStats });
    updateStats();
  } else {
    if (response.quota_exceeded) {
      showStatus('Daily or monthly limit reached. Please upgrade your plan.', 'error');
      if (currentUser) {
        currentUser.can_moderate = false;
      }
    } else {
      showStatus(response.error || 'Failed to save comments', 'error');
    }
  }
}

async function executeDeletes(comments) {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  try {
    const response = await chrome.tabs.sendMessage(tab.id, {
      action: 'deleteComments',
      comments: comments,
    });

    if (response.success) {
      showStatus(`Deleted ${response.deletedCount} spam comment(s)!`, 'success');

      // Report results to API
      await reportDeletions(response.results);
    }
  } catch (error) {
    console.error('Delete error:', error);
    showStatus('Some deletions failed', 'error');
  }
}

async function reportDeletions(results, platform = 'instagram') {
  const connectionKey = `${platform}ConnectionId`;
  const stored = await chrome.storage.local.get([connectionKey]);
  const connectionId = stored[connectionKey];

  if (!connectionId) return;

  try {
    await apiRequest('POST', '/extension/report-deletions', {
      connection_id: connectionId,
      results: results,
    });
  } catch (error) {
    console.error('Report deletions error:', error);
  }
}

// ========================================
// UI Updates
// ========================================

function showLogin() {
  loginView.classList.remove('hidden');
  dashboardView.classList.add('hidden');

  // Clear form
  document.getElementById('email').value = '';
  document.getElementById('password').value = '';
  hideError();
}

function showDashboard() {
  loginView.classList.add('hidden');
  dashboardView.classList.remove('hidden');

  // Update user info
  if (currentUser) {
    document.getElementById('user-name').textContent = currentUser.name;
    document.getElementById('user-initial').textContent = currentUser.name.charAt(0).toUpperCase();
    document.getElementById('user-plan').textContent = currentUser.plan || 'Free';

    // Update usage
    if (currentUser.usage) {
      const usage = currentUser.usage;
      const dailyUsed = usage.daily_used || 0;
      const dailyLimit = usage.daily_limit === 'unlimited' ? '∞' : usage.daily_limit;
      const percentage = usage.daily_percentage || 0;

      document.getElementById('usage-text').textContent = `${dailyUsed} / ${dailyLimit}`;
      document.getElementById('usage-fill').style.width = `${percentage}%`;
    }
  }

  // Load platforms
  loadPlatforms();

  // Update stats
  updateStats();
}

async function loadPlatforms() {
  const platformList = document.getElementById('platform-list');

  try {
    const response = await apiRequest('GET', '/extension/verify');

    if (response.success && response.connections) {
      if (response.connections.length === 0) {
        platformList.innerHTML = `
          <div class="text-center py-4">
            <p class="text-white/50 text-sm mb-3">No platforms connected</p>
            <button class="btn-secondary text-xs px-4 py-2" onclick="chrome.tabs.create({url: '${CONFIG.WEB_BASE}/dashboard/connected-accounts'})">
              Connect Account
            </button>
          </div>
        `;
      } else {
        platformList.innerHTML = response.connections.map(conn => `
          <div class="platform-item">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center ${getPlatformColor(conn.platform)}">
                ${getPlatformIcon(conn.platform)}
              </div>
              <span class="text-sm text-white/90">@${conn.username}</span>
            </div>
            <span class="status-badge status-badge-success">Connected</span>
          </div>
        `).join('');

        // Store connection IDs for each platform
        const storageUpdates = {};

        const instagramConn = response.connections.find(c => c.platform === 'instagram');
        if (instagramConn) {
          storageUpdates.instagramConnectionId = instagramConn.id;
        }

        const tiktokConn = response.connections.find(c => c.platform === 'tiktok');
        if (tiktokConn) {
          storageUpdates.tiktokConnectionId = tiktokConn.id;
        }

        const youtubeConn = response.connections.find(c => c.platform === 'youtube');
        if (youtubeConn) {
          storageUpdates.youtubeConnectionId = youtubeConn.id;
        }

        if (Object.keys(storageUpdates).length > 0) {
          await chrome.storage.local.set(storageUpdates);
        }
      }
    }
  } catch (error) {
    console.error('Load platforms error:', error);
    platformList.innerHTML = '<div class="text-center py-4 text-white/50 text-sm">Failed to load platforms</div>';
  }
}

function getPlatformColor(platform) {
  switch (platform) {
    case 'instagram':
      return 'bg-gradient-to-br from-purple-500 to-pink-500 text-white';
    case 'tiktok':
      return 'bg-gradient-to-br from-cyan-400 to-pink-500 text-white';
    case 'youtube':
      return 'bg-red-500 text-white';
    default:
      return 'bg-white/10 text-white/60';
  }
}

function getPlatformIcon(platform) {
  switch (platform) {
    case 'instagram':
      return `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>`;
    case 'tiktok':
      return `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 20.1a6.34 6.34 0 0010.86-4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1-.1z"/></svg>`;
    case 'youtube':
      return `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>`;
    default:
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>`;
  }
}

async function updateStats() {
  // Get stats from storage
  const stored = await chrome.storage.local.get(['stats']);
  const stats = stored.stats || { scanned: 0, deleted: 0 };

  document.getElementById('stat-scanned').textContent = stats.scanned;
  document.getElementById('stat-deleted').textContent = stats.deleted;
}

function showError(message) {
  loginError.textContent = message;
  loginError.classList.remove('hidden');
}

function hideError() {
  loginError.classList.add('hidden');
}

function showStatus(message, type = 'info') {
  const bgColors = {
    success: 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
    error: 'bg-red-500/10 border-red-500/20 text-red-400',
    info: 'bg-blue-500/10 border-blue-500/20 text-blue-400',
  };

  statusMessage.className = `mt-4 p-3 rounded-xl border flex items-center gap-2 text-sm ${bgColors[type] || bgColors.info}`;
  statusMessage.innerHTML = `
    <span class="flex-shrink-0">${getStatusIcon(type)}</span>
    <span>${message}</span>
  `;
  statusMessage.classList.remove('hidden');

  // Auto hide after 5 seconds
  setTimeout(() => {
    statusMessage.classList.add('hidden');
  }, 5000);
}

function getStatusIcon(type) {
  switch (type) {
    case 'success':
      return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>';
    case 'error':
      return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    default:
      return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }
}

// ========================================
// API Helper
// ========================================

async function apiRequest(method, endpoint, body = null) {
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };

  if (authToken) {
    options.headers['Authorization'] = `Bearer ${authToken}`;
  }

  if (body) {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(`${API_BASE}${endpoint}`, options);
  return response.json();
}
