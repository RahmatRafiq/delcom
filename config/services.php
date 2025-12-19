<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'project_id' => env('GOOGLE_PROJECT_ID'),
        'service_account_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media Platform APIs
    |--------------------------------------------------------------------------
    */

    // YouTube API scopes (used with Google OAuth)
    'youtube' => [
        'scopes' => [
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/youtube.readonly',
        ],
        // Quota settings (adjustable for GCP quota upgrades)
        'daily_quota' => (int) env('YOUTUBE_DAILY_QUOTA', 10000),
        'max_requests_per_minute' => (int) env('YOUTUBE_MAX_REQUESTS_PER_MINUTE', 30),
    ],

    // Facebook (for Instagram Business API)
    // Instagram API requires Facebook OAuth + linked Instagram Business account
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
        'scopes' => [
            'public_profile',
            'email',
            'pages_show_list',
            'pages_read_engagement',
            'instagram_basic',
            'instagram_manage_comments',
        ],
    ],

    // Instagram Graph API (uses Facebook OAuth tokens)
    'instagram' => [
        'graph_url' => 'https://graph.facebook.com/v21.0',
    ],

    // Twitter/X (OAuth 2.0)
    // Requires Basic API tier ($100/mo) or higher
    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
        'scopes' => [
            'tweet.read',
            'users.read',
            'offline.access',
        ],
    ],

    // Threads (Meta Graph API)
    // Requires Instagram Business/Creator account
    'threads' => [
        'client_id' => env('THREADS_CLIENT_ID'),
        'client_secret' => env('THREADS_CLIENT_SECRET'),
        'redirect' => env('THREADS_REDIRECT_URI'),
        'scopes' => [
            'threads_basic',
            'threads_manage_replies',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Services Configuration
    |--------------------------------------------------------------------------
    | Configure AI providers for spam detection
    */

    'ai' => [
        'enabled' => env('AI_SPAM_DETECTION_ENABLED', false),
        'provider' => env('AI_PROVIDER', 'openai'), // 'openai' or 'anthropic'
        'model' => env('AI_MODEL', 'gpt-4o-mini'), // 'gpt-4o-mini', 'claude-3-haiku-20240307'
        'spam_threshold' => (float) env('AI_SPAM_THRESHOLD', 0.7),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    ],

];
