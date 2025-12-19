/* eslint-env webextensions */
/**
 * Delcom Extension - Background Service Worker
 *
 * Handles:
 * - Auto-scanning on schedule
 * - Badge updates
 * - Cross-tab communication
 */

const API_BASE = 'http://localhost:8000/api';

// ========================================
// Initialization
// ========================================

chrome.runtime.onInstalled.addListener(() => {
  console.log('Delcom Extension installed');

  // Set default badge
  chrome.action.setBadgeBackgroundColor({ color: '#6366f1' });

  // Initialize storage
  chrome.storage.local.get(['stats'], (result) => {
    if (!result.stats) {
      chrome.storage.local.set({
        stats: { scanned: 0, deleted: 0 },
      });
    }
  });
});

// ========================================
// Message Handling
// ========================================

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  switch (message.action) {
    case 'updateBadge':
      updateBadge(message.count);
      break;

    case 'updateStats':
      updateStats(message.scanned, message.deleted);
      break;

    case 'getAuthToken':
      chrome.storage.local.get(['authToken'], (result) => {
        sendResponse({ token: result.authToken });
      });
      return true; // Keep channel open for async response

    case 'apiRequest':
      handleApiRequest(message.method, message.endpoint, message.body)
        .then(sendResponse)
        .catch(error => sendResponse({ success: false, error: error.message }));
      return true;

    case 'showNotification':
      showNotification(message.title, message.body);
      break;

    case 'storeAuth':
      // Handle auth token from SSO page
      storeAuthToken(message.token, sender.tab?.id).then(() => {
        sendResponse({ success: true });
      });
      return true;
  }
});

// ========================================
// Badge Management
// ========================================

function updateBadge(count) {
  if (count > 0) {
    chrome.action.setBadgeText({ text: count.toString() });
    chrome.action.setBadgeBackgroundColor({ color: '#ef4444' });
  } else {
    chrome.action.setBadgeText({ text: '' });
  }
}

// ========================================
// Stats Management
// ========================================

async function updateStats(scanned, deleted) {
  const stored = await chrome.storage.local.get(['stats']);
  const stats = stored.stats || { scanned: 0, deleted: 0 };

  stats.scanned += scanned;
  stats.deleted += deleted;

  await chrome.storage.local.set({ stats });
}

// ========================================
// API Requests
// ========================================

async function handleApiRequest(method, endpoint, body) {
  const stored = await chrome.storage.local.get(['authToken']);

  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
  };

  if (stored.authToken) {
    options.headers['Authorization'] = `Bearer ${stored.authToken}`;
  }

  if (body) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(`${API_BASE}${endpoint}`, options);
    return await response.json();
  } catch (error) {
    console.error('API request failed:', error);
    throw error;
  }
}

// ========================================
// Notifications
// ========================================

function showNotification(title, body) {
  chrome.notifications.create({
    type: 'basic',
    iconUrl: '../assets/icon-128.png',
    title: title,
    message: body,
  });
}

// ========================================
// Auto-scan Alarm (Optional feature)
// ========================================

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'autoScan') {
    console.log('Auto-scan triggered');
    // Auto-scan logic can be implemented here
    // For now, we'll skip auto-scanning as it requires user interaction
  }
});

// Set up auto-scan alarm (every 30 minutes)
// chrome.alarms.create('autoScan', { periodInMinutes: 30 });

// ========================================
// Context Menu (Right-click menu)
// ========================================

chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({
    id: 'scanPage',
    title: 'Scan comments on this page',
    contexts: ['page'],
    documentUrlPatterns: ['https://www.instagram.com/*'],
  });
});

chrome.contextMenus.onClicked.addListener((info, tab) => {
  if (info.menuItemId === 'scanPage') {
    chrome.tabs.sendMessage(tab.id, { action: 'scan' });
  }
});

// ========================================
// Tab Updates (Detect Instagram navigation & SSO callback)
// ========================================

chrome.tabs.onUpdated.addListener(async (tabId, changeInfo, tab) => {
  // Handle SSO authorize page - inject content script to receive token
  if (changeInfo.status === 'complete' && tab.url) {
    // Check for extension authorize or callback page
    if (tab.url.includes('/extension/authorize') || tab.url.includes('/extension/callback')) {
      console.log('SSO page detected:', tab.url);

      // Inject a script to listen for auth token
      try {
        await chrome.scripting.executeScript({
          target: { tabId },
          func: listenForAuthToken,
        });
      } catch (error) {
        console.log('Could not inject auth listener:', error.message);
      }
    }

    // Legacy: Handle old OAuth callback with token in URL fragment
    if (tab.url.includes('/api/auth/extension-callback')) {
      console.log('Legacy OAuth callback detected:', tab.url);

      const tokenMatch = tab.url.match(/#token=([^&]+)/);
      if (tokenMatch && tokenMatch[1]) {
        const token = tokenMatch[1];
        await storeAuthToken(token, tabId);
      }
    }
  }

  // Detect Instagram navigation
  if (changeInfo.status === 'complete' && tab.url?.includes('instagram.com')) {
    // Update badge to show extension is active
    chrome.action.setBadgeText({ tabId, text: '' });
  }
});

// Function to inject into SSO pages to listen for auth token
function listenForAuthToken() {
  // Check if token is already available on window
  if (window.__DELCOM_AUTH__) {
    chrome.runtime.sendMessage({
      action: 'storeAuth',
      token: window.__DELCOM_AUTH__.token,
      user: window.__DELCOM_AUTH__.user,
    });
    return;
  }

  // Listen for custom event from the page
  window.addEventListener('delcom:auth', (event) => {
    if (event.detail && event.detail.token) {
      chrome.runtime.sendMessage({
        action: 'storeAuth',
        token: event.detail.token,
        user: event.detail.user,
      });
    }
  });

  // Check sessionStorage
  try {
    const stored = sessionStorage.getItem('delcom_auth_token');
    if (stored) {
      const data = JSON.parse(stored);
      if (data.token && Date.now() - data.timestamp < 60000) {
        chrome.runtime.sendMessage({
          action: 'storeAuth',
          token: data.token,
          user: data.user,
        });
        sessionStorage.removeItem('delcom_auth_token');
      }
    }
  } catch (e) {
    console.log('Could not read sessionStorage');
  }

  // Poll for token in case it's set after script injection
  let attempts = 0;
  const pollInterval = setInterval(() => {
    attempts++;
    if (window.__DELCOM_AUTH__) {
      chrome.runtime.sendMessage({
        action: 'storeAuth',
        token: window.__DELCOM_AUTH__.token,
        user: window.__DELCOM_AUTH__.user,
      });
      clearInterval(pollInterval);
    }
    if (attempts >= 30) {
      clearInterval(pollInterval);
    }
  }, 500);
}

// Helper to store auth token
async function storeAuthToken(token, tabId) {
  console.log('Token received, length:', token.length);

  // Store token
  await chrome.storage.local.set({ authToken: token });

  // Get user info
  try {
    const userInfo = await handleApiRequest('GET', '/auth/me', null);
    if (userInfo.success) {
      await chrome.storage.local.set({ user: userInfo.user });
      console.log('User info stored:', userInfo.user.email);

      // Show notification
      showNotification('Login Successful', `Welcome, ${userInfo.user.name}!`);
    }
  } catch (error) {
    console.error('Failed to get user info:', error);
  }

  // Close the tab
  if (tabId) {
    chrome.tabs.remove(tabId);
  }
}
