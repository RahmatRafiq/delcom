<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\PlatformConnectionMethod;
use Illuminate\Database\Seeder;

class PlatformConnectionMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // YouTube - API only (Google OAuth)
            'youtube' => [
                [
                    'connection_method' => 'api',
                    'requires_business_account' => false,
                    'requires_paid_api' => false,
                    'notes' => 'Google OAuth with YouTube Data API v3',
                    'is_active' => true,
                ],
            ],

            // Instagram - Both (API needs Business account)
            'instagram' => [
                [
                    'connection_method' => 'api',
                    'requires_business_account' => true,
                    'requires_paid_api' => false,
                    'notes' => 'Instagram Graph API via Meta Business. Requires Instagram Business/Creator account linked to Facebook Page.',
                    'is_active' => true,
                ],
                [
                    'connection_method' => 'extension',
                    'requires_business_account' => false,
                    'requires_paid_api' => false,
                    'notes' => 'Browser extension for regular Instagram accounts',
                    'is_active' => true,
                ],
            ],

            // Twitter/X - Both (API needs paid tier)
            'twitter' => [
                [
                    'connection_method' => 'api',
                    'requires_business_account' => false,
                    'requires_paid_api' => true,
                    'notes' => 'Twitter API v2. Requires Basic API tier ($100/mo) or higher.',
                    'is_active' => true,
                ],
                [
                    'connection_method' => 'extension',
                    'requires_business_account' => false,
                    'requires_paid_api' => false,
                    'notes' => 'Browser extension for all Twitter accounts',
                    'is_active' => true,
                ],
            ],

            // Threads - Both (API needs Business account)
            'threads' => [
                [
                    'connection_method' => 'api',
                    'requires_business_account' => true,
                    'requires_paid_api' => false,
                    'notes' => 'Threads API via Meta. Requires Instagram Business/Creator account.',
                    'is_active' => true,
                ],
                [
                    'connection_method' => 'extension',
                    'requires_business_account' => false,
                    'requires_paid_api' => false,
                    'notes' => 'Browser extension for all Threads accounts',
                    'is_active' => true,
                ],
            ],

            // TikTok - Extension only (no public API)
            'tiktok' => [
                [
                    'connection_method' => 'extension',
                    'requires_business_account' => false,
                    'requires_paid_api' => false,
                    'notes' => 'Browser extension required. TikTok does not provide public comment moderation API.',
                    'is_active' => true,
                ],
            ],
        ];

        foreach ($methods as $platformName => $platformMethods) {
            $platform = Platform::where('name', $platformName)->first();

            if (! $platform) {
                $this->command->warn("Platform '{$platformName}' not found, skipping...");

                continue;
            }

            foreach ($platformMethods as $method) {
                PlatformConnectionMethod::updateOrCreate(
                    [
                        'platform_id' => $platform->id,
                        'connection_method' => $method['connection_method'],
                    ],
                    $method
                );
            }
        }
    }
}
