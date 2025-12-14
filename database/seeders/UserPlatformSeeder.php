<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use Illuminate\Database\Seeder;

class UserPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $user = User::where('email', 'user@example.com')->first();

        if (! $admin) {
            return;
        }

        $youtube = Platform::where('name', 'youtube')->first();
        $instagram = Platform::where('name', 'instagram')->first();
        $tiktok = Platform::where('name', 'tiktok')->first();

        // Admin platforms
        UserPlatform::updateOrCreate(
            ['user_id' => $admin->id, 'platform_id' => $youtube->id],
            [
                'platform_user_id' => 'UCadmin'.fake()->regexify('[A-Za-z0-9]{18}'),
                'platform_username' => 'AdminChannel',
                'is_active' => true,
                'auto_moderation_enabled' => true,
            ]
        );

        UserPlatform::updateOrCreate(
            ['user_id' => $admin->id, 'platform_id' => $instagram->id],
            [
                'platform_user_id' => fake()->numerify('##########'),
                'platform_username' => 'admin_official',
                'is_active' => true,
                'auto_moderation_enabled' => false,
            ]
        );

        // Regular user platform
        if ($user) {
            UserPlatform::updateOrCreate(
                ['user_id' => $user->id, 'platform_id' => $youtube->id],
                [
                    'platform_user_id' => 'UCuser'.fake()->regexify('[A-Za-z0-9]{19}'),
                    'platform_username' => 'UserChannel',
                    'is_active' => true,
                    'auto_moderation_enabled' => false,
                ]
            );
        }
    }
}
