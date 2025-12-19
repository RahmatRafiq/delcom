<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delcom - Extension Connected</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            text-align: center;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 400px;
            width: 90%;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease-out;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .user-email {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        .message {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            line-height: 1.5;
        }
        .spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 1rem auto 0;
        }
        .error {
            display: none;
            color: #f87171;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 0.5rem;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20,6 9,17 4,12"></polyline>
            </svg>
        </div>
        <h1>Connected!</h1>
        <p class="user-email">{{ $user['email'] }}</p>
        <p class="message">Your Delcom extension is now connected. This tab will close automatically...</p>
        <div class="spinner" id="spinner"></div>
        <div class="error" id="error">
            <p>Could not connect to extension. Please make sure the Delcom extension is installed.</p>
        </div>
    </div>

    <script>
        (function() {
            const token = @json($token);
            const user = @json($user);

            // Try to send token to extension via chrome.runtime.sendMessage
            // This requires the extension to have externally_connectable configured
            const EXTENSION_ID = 'YOUR_EXTENSION_ID'; // Will be replaced with actual ID

            // Method 1: Try using BroadcastChannel (works if extension has same origin)
            try {
                const channel = new BroadcastChannel('delcom_auth');
                channel.postMessage({
                    type: 'AUTH_SUCCESS',
                    token: token,
                    user: user
                });
                channel.close();
            } catch (e) {
                console.log('BroadcastChannel not available');
            }

            // Method 2: Store in sessionStorage for extension to read
            try {
                // Use a specific key that extension will look for
                sessionStorage.setItem('delcom_auth_token', JSON.stringify({
                    token: token,
                    user: user,
                    timestamp: Date.now()
                }));
            } catch (e) {
                console.log('sessionStorage not available');
            }

            // Method 3: Custom event that content script can listen for
            window.dispatchEvent(new CustomEvent('delcom:auth', {
                detail: { token, user }
            }));

            // Method 4: Set data on window for content script to read
            window.__DELCOM_AUTH__ = { token, user };

            // Close tab after a short delay
            setTimeout(() => {
                window.close();

                // If window.close() doesn't work (happens when not opened by script),
                // show a message
                setTimeout(() => {
                    document.querySelector('.message').textContent =
                        'You can now close this tab and use the Delcom extension.';
                }, 500);
            }, 2000);
        })();
    </script>
</body>
</html>
