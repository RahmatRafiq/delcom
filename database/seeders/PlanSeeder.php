<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Get started with basic comment moderation on TikTok.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'monthly_action_limit' => 100,
                'daily_action_limit' => 10, // 10 comments per day
                'max_platforms' => 1,
                'scan_frequency_minutes' => 120,
                'features' => ['basic_filters', 'manual_moderation'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'description' => 'Moderate comments across all platforms via browser extension.',
                'price_monthly' => 9.99,
                'price_yearly' => 99.99,
                'monthly_action_limit' => 1000,
                'daily_action_limit' => 50, // 50 comments per day
                'max_platforms' => 3,
                'scan_frequency_minutes' => 60,
                'features' => ['basic_filters', 'custom_filters', 'export_logs', 'extension_platforms'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'Full access with API integration and auto-moderation.',
                'price_monthly' => 29.99,
                'price_yearly' => 299.99,
                'monthly_action_limit' => 10000,
                'daily_action_limit' => 500, // 500 comments per day
                'max_platforms' => -1, // unlimited
                'scan_frequency_minutes' => 30,
                'features' => [
                    'basic_filters',
                    'custom_filters',
                    'export_logs',
                    'extension_platforms',
                    'api_platforms',
                    'auto_moderation',
                    'priority_scanning',
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Unlimited access with priority support and white-label options.',
                'price_monthly' => 99.99,
                'price_yearly' => 999.99,
                'monthly_action_limit' => -1, // unlimited
                'daily_action_limit' => -1, // unlimited
                'max_platforms' => -1, // unlimited
                'scan_frequency_minutes' => 15,
                'features' => [
                    'basic_filters',
                    'custom_filters',
                    'export_logs',
                    'extension_platforms',
                    'api_platforms',
                    'auto_moderation',
                    'priority_scanning',
                    'priority_support',
                    'white_label',
                    'api_access',
                ],
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
