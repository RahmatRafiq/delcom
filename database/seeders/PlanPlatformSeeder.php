<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $planPlatforms = [
            // Free plan - TikTok only (extension)
            'free' => [
                'tiktok' => 'extension',
            ],

            // Basic plan - All platforms via extension only
            'basic' => [
                'youtube' => 'any', // YouTube only has API, so 'any' will work
                'instagram' => 'extension',
                'twitter' => 'extension',
                'threads' => 'extension',
                'tiktok' => 'extension',
            ],

            // Pro plan - All platforms, all methods
            'pro' => [
                'youtube' => 'any',
                'instagram' => 'any',
                'twitter' => 'any',
                'threads' => 'any',
                'tiktok' => 'extension',
            ],

            // Enterprise plan - All platforms, all methods
            'enterprise' => [
                'youtube' => 'any',
                'instagram' => 'any',
                'twitter' => 'any',
                'threads' => 'any',
                'tiktok' => 'extension',
            ],
        ];

        foreach ($planPlatforms as $planSlug => $platforms) {
            $plan = Plan::where('slug', $planSlug)->first();

            if (! $plan) {
                $this->command->warn("Plan '{$planSlug}' not found, skipping...");

                continue;
            }

            foreach ($platforms as $platformName => $allowedMethod) {
                $platform = Platform::where('name', $platformName)->first();

                if (! $platform) {
                    $this->command->warn("Platform '{$platformName}' not found, skipping...");

                    continue;
                }

                // Use updateOrInsert to avoid duplicates
                DB::table('plan_platforms')->updateOrInsert(
                    [
                        'plan_id' => $plan->id,
                        'platform_id' => $platform->id,
                    ],
                    [
                        'allowed_method' => $allowedMethod,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
