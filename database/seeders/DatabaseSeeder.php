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
            AppSettingSeeder::class,
            MenuSeeder::class,
            // DelCom seeders (Plans must be seeded before Users)
            PlatformSeeder::class,
            PlanSeeder::class,
            PlatformConnectionMethodSeeder::class,
            PlanPlatformSeeder::class,
            // Users (will auto-get free subscription via model boot)
            UserSeeder::class,
            // Rest of DelCom seeders
            PresetFilterSeeder::class,
            UserPlatformSeeder::class,
            FilterGroupSeeder::class,
            FilterSeeder::class,
            ModerationLogSeeder::class,
        ]);
    }
}
