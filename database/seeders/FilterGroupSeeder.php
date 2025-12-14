<?php

namespace Database\Seeders;

use App\Models\FilterGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class FilterGroupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminData();
        $this->seedUserData();
    }

    private function seedAdminData(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        FilterGroup::updateOrCreate(
            ['user_id' => $admin->id, 'name' => 'Spam & Judi'],
            [
                'description' => 'Filter untuk spam judi online, slot, dan togel',
                'is_active' => true,
                'applies_to_platforms' => ['youtube', 'instagram', 'tiktok'],
            ]
        );

        FilterGroup::updateOrCreate(
            ['user_id' => $admin->id, 'name' => 'Self Promotion'],
            [
                'description' => 'Filter untuk buzzer dan spam promosi',
                'is_active' => true,
                'applies_to_platforms' => ['youtube', 'instagram'],
            ]
        );

        FilterGroup::updateOrCreate(
            ['user_id' => $admin->id, 'name' => 'Hate Speech'],
            [
                'description' => 'Filter untuk kata-kata kasar',
                'is_active' => false,
                'applies_to_platforms' => ['youtube'],
            ]
        );
    }

    private function seedUserData(): void
    {
        $user = User::where('email', 'user@example.com')->first();

        if (! $user) {
            return;
        }

        FilterGroup::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Anti Spam'],
            [
                'description' => 'Filter dasar untuk menangkal spam umum',
                'is_active' => true,
                'applies_to_platforms' => ['youtube'],
            ]
        );

        FilterGroup::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Link Blocker'],
            [
                'description' => 'Blokir komentar yang mengandung link mencurigakan',
                'is_active' => true,
                'applies_to_platforms' => ['youtube', 'tiktok'],
            ]
        );
    }
}
