<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'extension/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Restrict to specific origins in production
    // For development, allow localhost and extension origins
    'allowed_origins' => array_filter([
        env('APP_URL'),
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://delcom.app',
        'https://*.delcom.app',
        // Chrome extension origins
        'chrome-extension://*',
    ]),

    'allowed_origins_patterns' => [
        '#^chrome-extension://[a-z]{32}$#', // Chrome extension IDs
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-Extension-Version',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    'max_age' => 3600, // Cache preflight for 1 hour

    'supports_credentials' => true,

];
