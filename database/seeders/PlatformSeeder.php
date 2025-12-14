<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'youtube',
                'display_name' => 'YouTube',
                'tier' => 'api',
                'api_base_url' => 'https://www.googleapis.com/youtube/v3',
                'oauth_authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'oauth_token_url' => 'https://oauth2.googleapis.com/token',
                'is_active' => true,
            ],
            [
                'name' => 'instagram',
                'display_name' => 'Instagram',
                'tier' => 'api',
                'api_base_url' => 'https://graph.instagram.com',
                'oauth_authorize_url' => 'https://api.instagram.com/oauth/authorize',
                'oauth_token_url' => 'https://api.instagram.com/oauth/access_token',
                'is_active' => true,
            ],
            [
                'name' => 'twitter',
                'display_name' => 'X (Twitter)',
                'tier' => 'api',
                'api_base_url' => 'https://api.twitter.com/2',
                'oauth_authorize_url' => 'https://twitter.com/i/oauth2/authorize',
                'oauth_token_url' => 'https://api.twitter.com/2/oauth2/token',
                'is_active' => true,
            ],
            [
                'name' => 'threads',
                'display_name' => 'Threads',
                'tier' => 'api',
                'api_base_url' => 'https://graph.threads.net',
                'oauth_authorize_url' => 'https://threads.net/oauth/authorize',
                'oauth_token_url' => 'https://graph.threads.net/oauth/access_token',
                'is_active' => true,
            ],
            [
                'name' => 'tiktok',
                'display_name' => 'TikTok',
                'tier' => 'extension',
                'api_base_url' => null,
                'oauth_authorize_url' => null,
                'oauth_token_url' => null,
                'is_active' => true,
            ],
        ];

        foreach ($platforms as $platform) {
            Platform::updateOrCreate(
                ['name' => $platform['name']],
                $platform
            );
        }
    }
}
