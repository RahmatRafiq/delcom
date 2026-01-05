/* eslint-env webextensions */
/**
 * Delcom Extension - Auth Listener Content Script
 *
 * Runs on Delcom web pages to capture auth tokens from SSO flow
 * and send them back to the extension.
 */

(function() {
  'use strict';

  console.log('[Delcom Auth] Content script loaded on:', window.location.href);
  console.log('[Delcom Auth] Pathname:', window.location.pathname);

  // Check if this is an authorize or callback page
  const isAuthorizePage = window.location.pathname.includes('/extension/authorize');
  const isCallbackPage = window.location.pathname.includes('/extension/callback');

  console.log('[Delcom Auth] isAuthorizePage:', isAuthorizePage, 'isCallbackPage:', isCallbackPage);

  if (!isAuthorizePage && !isCallbackPage) {
    console.log('[Delcom Auth] Not an auth page, exiting');
    return;
  }

  console.log('[Delcom Auth] Auth page detected:', isAuthorizePage ? 'authorize' : 'callback');

  // Function to send token to extension
  function sendTokenToExtension(token, user) {
    console.log('[Delcom Auth] Sending token to extension, length:', token.length);

    chrome.runtime.sendMessage({
      action: 'storeAuth',
      token: token,
      user: user,
    }, (response) => {
      if (chrome.runtime.lastError) {
        console.error('[Delcom Auth] Error sending message:', chrome.runtime.lastError);
      } else {
        console.log('[Delcom Auth] Token stored successfully');
      }
    });
  }

  // Method 1: Check if token is already on window (for callback page)
  function checkWindowToken() {
    if (window.__DELCOM_AUTH__ && window.__DELCOM_AUTH__.token) {
      console.log('[Delcom Auth] Found token on window');
      sendTokenToExtension(window.__DELCOM_AUTH__.token, window.__DELCOM_AUTH__.user);
      return true;
    }
    return false;
  }

  // Method 2: Check sessionStorage
  function checkSessionStorage() {
    try {
      const stored = sessionStorage.getItem('delcom_auth_token');
      if (stored) {
        const data = JSON.parse(stored);
        if (data.token && Date.now() - data.timestamp < 60000) { // 1 minute validity
          console.log('[Delcom Auth] Found token in sessionStorage');
          sendTokenToExtension(data.token, data.user);
          sessionStorage.removeItem('delcom_auth_token');
          return true;
        }
      }
    } catch (e) {
      console.log('[Delcom Auth] sessionStorage not available');
    }
    return false;
  }

  // Method 3: Listen for custom event from Authorize page
  window.addEventListener('delcom:auth', (event) => {
    if (event.detail && event.detail.token) {
      console.log('[Delcom Auth] Received token via custom event');
      sendTokenToExtension(event.detail.token, event.detail.user);
    }
  });

  // Method 4: Listen for BroadcastChannel messages
  try {
    const channel = new BroadcastChannel('delcom_auth');
    channel.onmessage = (event) => {
      if (event.data.type === 'AUTH_SUCCESS' && event.data.token) {
        console.log('[Delcom Auth] Received token via BroadcastChannel');
        sendTokenToExtension(event.data.token, event.data.user);
      }
    };
  } catch (e) {
    console.log('[Delcom Auth] BroadcastChannel not available');
  }

  // Initial checks
  if (isCallbackPage) {
    // For callback page, token should already be available
    if (!checkWindowToken()) {
      checkSessionStorage();
    }

    // Poll for token in case it's set after script runs
    let attempts = 0;
    const pollInterval = setInterval(() => {
      attempts++;
      if (checkWindowToken() || checkSessionStorage()) {
        clearInterval(pollInterval);
      }
      if (attempts >= 20) { // 10 seconds max
        clearInterval(pollInterval);
        console.log('[Delcom Auth] Gave up waiting for token');
      }
    }, 500);
  }

  // For authorize page, also check periodically
  if (isAuthorizePage) {
    let attempts = 0;
    const pollInterval = setInterval(() => {
      attempts++;
      if (checkWindowToken() || checkSessionStorage()) {
        clearInterval(pollInterval);
      }
      if (attempts >= 60) { // 30 seconds max
        clearInterval(pollInterval);
      }
    }, 500);
  }

})();
