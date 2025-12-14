<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class FilterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdminFilters();
        $this->seedUserFilters();
    }

    private function seedAdminFilters(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        $spamGroup = FilterGroup::where('user_id', $admin->id)->where('name', 'Spam & Judi')->first();
        $promoGroup = FilterGroup::where('user_id', $admin->id)->where('name', 'Self Promotion')->first();
        $hateGroup = FilterGroup::where('user_id', $admin->id)->where('name', 'Hate Speech')->first();

        // Spam & Judi filters
        if ($spamGroup) {
            $this->createFilters($spamGroup->id, [
                ['type' => 'keyword', 'pattern' => 'slot gacor', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 10],
                ['type' => 'keyword', 'pattern' => 'togel online', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 10],
                ['type' => 'keyword', 'pattern' => 'judi bola', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 10],
                ['type' => 'keyword', 'pattern' => 'maxwin', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 8],
                ['type' => 'regex', 'pattern' => '(slot|togel)\\s*(gacor|maxwin)', 'match_type' => 'regex', 'action' => 'delete', 'priority' => 8],
                ['type' => 'url', 'pattern' => 'bit.ly', 'match_type' => 'contains', 'action' => 'flag', 'priority' => 5],
                ['type' => 'regex', 'pattern' => 'wa\\.me\\/\\d+', 'match_type' => 'regex', 'action' => 'delete', 'priority' => 9],
            ]);
        }

        // Self Promotion filters
        if ($promoGroup) {
            $this->createFilters($promoGroup->id, [
                ['type' => 'keyword', 'pattern' => 'cek bio', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 5],
                ['type' => 'keyword', 'pattern' => 'link di bio', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 5],
                ['type' => 'keyword', 'pattern' => 'sub balik', 'match_type' => 'contains', 'action' => 'flag', 'priority' => 3],
                ['type' => 'regex', 'pattern' => '(gratis|free)\\s*(followers?|likes?)', 'match_type' => 'regex', 'action' => 'delete', 'priority' => 7],
            ]);
        }

        // Hate Speech filters
        if ($hateGroup) {
            $this->createFilters($hateGroup->id, [
                ['type' => 'keyword', 'pattern' => 'anjing', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 5],
                ['type' => 'keyword', 'pattern' => 'bangsat', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 5],
                ['type' => 'keyword', 'pattern' => 'goblok', 'match_type' => 'contains', 'action' => 'flag', 'priority' => 3],
            ]);
        }
    }

    private function seedUserFilters(): void
    {
        $user = User::where('email', 'user@example.com')->first();

        if (! $user) {
            return;
        }

        $antiSpamGroup = FilterGroup::where('user_id', $user->id)->where('name', 'Anti Spam')->first();
        $linkBlockerGroup = FilterGroup::where('user_id', $user->id)->where('name', 'Link Blocker')->first();

        // Anti Spam filters
        if ($antiSpamGroup) {
            $this->createFilters($antiSpamGroup->id, [
                ['type' => 'keyword', 'pattern' => 'slot gacor', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 10],
                ['type' => 'keyword', 'pattern' => 'togel', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 9],
                ['type' => 'keyword', 'pattern' => 'judi online', 'match_type' => 'contains', 'action' => 'delete', 'priority' => 9],
                ['type' => 'emoji_spam', 'pattern' => '8', 'match_type' => 'exact', 'action' => 'flag', 'priority' => 3],
                ['type' => 'repeat_char', 'pattern' => '6', 'match_type' => 'exact', 'action' => 'flag', 'priority' => 2],
            ]);
        }

        // Link Blocker filters
        if ($linkBlockerGroup) {
            $this->createFilters($linkBlockerGroup->id, [
                ['type' => 'url', 'pattern' => 'bit.ly', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 7],
                ['type' => 'url', 'pattern' => 's.id', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 7],
                ['type' => 'url', 'pattern' => 'tinyurl.com', 'match_type' => 'contains', 'action' => 'hide', 'priority' => 7],
                ['type' => 'regex', 'pattern' => 'wa\\.me\\/\\d+', 'match_type' => 'regex', 'action' => 'delete', 'priority' => 8],
            ]);
        }
    }

    private function createFilters(int $groupId, array $filters): void
    {
        foreach ($filters as $data) {
            Filter::updateOrCreate(
                ['filter_group_id' => $groupId, 'pattern' => $data['pattern']],
                array_merge($data, [
                    'filter_group_id' => $groupId,
                    'is_active' => true,
                    'hit_count' => fake()->numberBetween(0, 100),
                ])
            );
        }
    }
}
