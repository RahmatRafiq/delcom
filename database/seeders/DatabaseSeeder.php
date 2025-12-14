<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            AppSettingSeeder::class,
            MenuSeeder::class,
            // DelCom seeders
            PlatformSeeder::class,
            PlanSeeder::class,
            PlatformConnectionMethodSeeder::class,
            PlanPlatformSeeder::class,
            PresetFilterSeeder::class,
            UserPlatformSeeder::class,
            FilterGroupSeeder::class,
            FilterSeeder::class,
            ModerationLogSeeder::class,
        ]);
    }
}
