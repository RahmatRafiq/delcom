/* eslint-env webextensions */
/**
 * Delcom Extension - Popup Script
 */

// Configuration
const API_BASE = 'http://localhost:8000/api';

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
    chrome.tabs.create({ url: 'http://localhost:8000/dashboard' });
  });

  // Cancel scan
  cancelScanBtn.addEventListener('click', () => {
    scanningOverlay.classList.add('hidden');
  });

  // Open register
  document.getElementById('open-register').addEventListener('click', (e) => {
    e.preventDefault();
    chrome.tabs.create({ url: 'http://localhost:8000/register' });
  });
}

// ========================================
// Authentication
// ========================================

async function handleGoogleLogin() {
  // Open Google OAuth in a new tab
  const authUrl = `${API_BASE.replace('/api', '')}/auth/google?extension=true`;

  chrome.tabs.create({ url: authUrl }, (tab) => {
    const tabId = tab.id;

    // Poll for auth completion
    const checkInterval = setInterval(async () => {
      try {
        chrome.tabs.get(tabId, async (t) => {
          if (chrome.runtime.lastError) {
            // Tab was closed
            clearInterval(checkInterval);
            return;
          }

          // Check if redirected to callback page with token in hash
          if (t.url && t.url.includes('/api/auth/extension-callback')) {
            clearInterval(checkInterval);

            // Extract token from URL hash (#token=xxx)
            const hashMatch = t.url.match(/#token=([^&]+)/);
            const token = hashMatch ? hashMatch[1] : null;

            if (token) {
              authToken = token;

              // Get user info
              const userInfo = await apiRequest('GET', '/auth/me');
              if (userInfo.success) {
                currentUser = userInfo.user;
                await chrome.storage.local.set({
                  authToken: authToken,
                  user: currentUser,
                });

                // Close the OAuth tab
                chrome.tabs.remove(tabId);
                showDashboard();
              } else {
                showError('Failed to get user info');
              }
            }
          }
        });
      } catch (error) {
        console.error('OAuth check error:', error);
      }
    }, 500);

    // Stop polling after 5 minutes
    setTimeout(() => clearInterval(checkInterval), 300000);
  });
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
// Scanning
// ========================================

async function handleScan() {
  // Get current tab
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (!tab.url.includes('instagram.com')) {
    showStatus('Please navigate to Instagram first', 'error');
    return;
  }

  // Show scanning overlay
  scanningOverlay.classList.remove('hidden');
  document.getElementById('scan-progress').textContent = 'Analyzing page...';

  try {
    // Send message to content script to scan
    const response = await chrome.tabs.sendMessage(tab.id, { action: 'scan' });

    if (response.success) {
      document.getElementById('scan-progress').textContent = `Found ${response.commentsCount} comments...`;

      // If comments found, send to API for filtering
      if (response.comments && response.comments.length > 0) {
        await processComments(response.comments, response.contentInfo);
      } else {
        showStatus('No comments found on this page', 'info');
      }
    } else {
      showStatus(response.error || 'Scan failed', 'error');
    }
  } catch (error) {
    console.error('Scan error:', error);
    showStatus('Could not scan page. Please refresh and try again.', 'error');
  } finally {
    scanningOverlay.classList.add('hidden');
  }
}

async function processComments(comments, contentInfo) {
  try {
    // Get connection ID from storage
    const stored = await chrome.storage.local.get(['instagramConnectionId']);

    if (!stored.instagramConnectionId) {
      showStatus('Please connect your Instagram account first', 'error');
      return;
    }

    document.getElementById('scan-progress').textContent = 'Checking for spam...';

    const response = await apiRequest('POST', '/extension/comments', {
      connection_id: stored.instagramConnectionId,
      content_id: contentInfo.postId,
      content_title: contentInfo.caption || 'Instagram Post',
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
      const { scanned, matched, queued, to_delete } = response;

      if (matched > 0) {
        if (response.use_review_queue) {
          showStatus(`Found ${matched} spam comment(s). Added to review queue.`, 'success');
        } else if (to_delete && to_delete.length > 0) {
          // Execute deletions
          document.getElementById('scan-progress').textContent = `Deleting ${to_delete.length} spam comments...`;
          await executeDeletes(to_delete);
        }
      } else {
        showStatus(`Scanned ${scanned} comments. No spam detected!`, 'success');
      }

      // Update stats
      updateStats();
    }
  } catch (error) {
    console.error('Process comments error:', error);
    showStatus('Failed to process comments', 'error');
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

async function reportDeletions(results) {
  const stored = await chrome.storage.local.get(['instagramConnectionId']);

  if (!stored.instagramConnectionId) return;

  try {
    await apiRequest('POST', '/extension/report-deletions', {
      connection_id: stored.instagramConnectionId,
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
            <button class="btn-secondary text-xs px-4 py-2" onclick="chrome.tabs.create({url: 'http://localhost:8000/dashboard/connected-accounts'})">
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

        // Store Instagram connection ID if available
        const instagramConn = response.connections.find(c => c.platform === 'instagram');
        if (instagramConn) {
          await chrome.storage.local.set({ instagramConnectionId: instagramConn.id });
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
