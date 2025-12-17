<?php

namespace Database\Seeders;

use App\Models\PendingModeration;
use App\Models\UserContent;
use Illuminate\Database\Seeder;

class ReviewQueueSeeder extends Seeder
{
    public function run(): void
    {
        $sampleContents = [
            ['id' => 'dQw4w9WgXcQ', 'title' => 'Tutorial Laravel 12 - Building REST API', 'type' => 'video'],
            ['id' => 'kJQP7kiw5Fk', 'title' => 'React 19 New Features Explained', 'type' => 'video'],
            ['id' => '9bZkp7q19f0', 'title' => 'Unboxing iPhone 16 Pro Max', 'type' => 'video'],
            ['id' => 'hT_nvWreIhg', 'title' => 'Cara Dapat 1 Juta Subscriber', 'type' => 'video'],
            ['id' => 'JGwWNGJdvx8', 'title' => 'Review Laptop Gaming 2024', 'type' => 'video'],
        ];

        $spamComments = [
            ['text' => 'Slot gacor hari ini! Klik link bio saya untuk maxwin!', 'pattern' => 'slot gacor', 'filter_id' => 1],
            ['text' => 'Togel online terpercaya, WD pasti dibayar 100%', 'pattern' => 'togel online', 'filter_id' => 2],
            ['text' => 'Judi bola parlay mix, odds tertinggi se-Asia', 'pattern' => 'judi bola', 'filter_id' => 3],
            ['text' => 'MAXWIN 500x di game terbaru! Daftar sekarang', 'pattern' => 'maxwin', 'filter_id' => 4],
            ['text' => 'Slot maxwin gacor hari ini RTP 99%', 'pattern' => '(slot|togel)\\s*(gacor|maxwin)', 'filter_id' => 5],
            ['text' => 'Cek profil saya untuk konten dewasa gratis', 'pattern' => 'cek profil', 'filter_id' => 1],
            ['text' => 'FREE IPHONE 15!! Klik link di bio sekarang!!!', 'pattern' => 'free iphone', 'filter_id' => 1],
            ['text' => 'Situs togel resmi, deposit 10rb WD sampai 10jt', 'pattern' => 'togel', 'filter_id' => 2],
            ['text' => 'Slot gacor malam ini scatter hitam auto jackpot', 'pattern' => 'slot gacor', 'filter_id' => 1],
            ['text' => 'Prediksi judi bola akurat 99% profit tiap hari', 'pattern' => 'judi bola', 'filter_id' => 3],
        ];

        $spammerUsernames = [
            'slot_gacor_official', 'togel_hoki_88', 'maxwin_setiap_hari', 'judi_bola_terpercaya',
            'bonus_member_baru', 'scatter_hitam_gacor', 'promo_new_member', 'wd_cepat_100',
            'link_alternatif_slot', 'deposit_pulsa_tanpa_potongan',
        ];

        $this->seedForUser(3, 4, $sampleContents, $spamComments, $spammerUsernames);
        $this->seedForUser(4, 5, array_slice($sampleContents, 0, 3), array_slice($spamComments, 0, 5), $spammerUsernames);

        $this->command->info('Review Queue seeded successfully!');
        $this->command->info('User 3: 5 contents, 15 pending moderations');
        $this->command->info('User 4: 3 contents, 8 pending moderations');
    }

    private function seedForUser(int $userId, int $userPlatformId, array $contents, array $spamComments, array $usernames): void
    {
        $userContentIds = [];

        foreach ($contents as $index => $content) {
            $userContent = UserContent::create([
                'user_id' => $userId,
                'user_platform_id' => $userPlatformId,
                'content_id' => $content['id'],
                'content_type' => $content['type'],
                'title' => $content['title'],
                'thumbnail_url' => "https://i.ytimg.com/vi/{$content['id']}/mqdefault.jpg",
                'platform_url' => "https://www.youtube.com/watch?v={$content['id']}",
                'published_at' => now()->subDays(rand(1, 30)),
                'scan_enabled' => true,
                'last_scanned_comment_id' => 'Ugw'.fake()->regexify('[A-Za-z0-9]{20}'),
                'last_scanned_at' => now()->subHours(rand(1, 24)),
                'total_comments_scanned' => rand(50, 500),
                'total_spam_found' => rand(5, 30),
            ]);

            $userContentIds[] = $userContent->id;
        }

        $statuses = [
            PendingModeration::STATUS_PENDING,
            PendingModeration::STATUS_PENDING,
            PendingModeration::STATUS_PENDING,
            PendingModeration::STATUS_APPROVED,
            PendingModeration::STATUS_DISMISSED,
            PendingModeration::STATUS_DELETED,
            PendingModeration::STATUS_FAILED,
        ];

        $commentCount = $userId === 3 ? 15 : 8;

        for ($i = 0; $i < $commentCount; $i++) {
            $spam = $spamComments[$i % count($spamComments)];
            $content = $contents[$i % count($contents)];
            $status = $statuses[$i % count($statuses)];
            $username = $usernames[$i % count($usernames)];

            $detectedAt = now()->subHours(rand(1, 72));

            PendingModeration::create([
                'user_id' => $userId,
                'user_platform_id' => $userPlatformId,
                'user_content_id' => $userContentIds[$i % count($userContentIds)],
                'platform_comment_id' => 'Ugw'.fake()->regexify('[A-Za-z0-9]{20}').'4AaABAg',
                'content_id' => $content['id'],
                'content_type' => $content['type'],
                'content_title' => $content['title'],
                'commenter_username' => $username,
                'commenter_id' => 'UC'.fake()->regexify('[A-Za-z0-9]{22}'),
                'commenter_profile_url' => 'https://www.youtube.com/channel/UC'.fake()->regexify('[A-Za-z0-9]{22}'),
                'comment_text' => $spam['text'],
                'matched_filter_id' => $spam['filter_id'],
                'matched_pattern' => $spam['pattern'],
                'confidence_score' => rand(85, 100),
                'status' => $status,
                'failure_reason' => $status === PendingModeration::STATUS_FAILED ? 'Comment not found or already deleted' : null,
                'detected_at' => $detectedAt,
                'reviewed_at' => in_array($status, [PendingModeration::STATUS_APPROVED, PendingModeration::STATUS_DISMISSED, PendingModeration::STATUS_DELETED, PendingModeration::STATUS_FAILED])
                    ? $detectedAt->addMinutes(rand(5, 120))
                    : null,
                'actioned_at' => in_array($status, [PendingModeration::STATUS_DELETED, PendingModeration::STATUS_FAILED])
                    ? $detectedAt->addMinutes(rand(60, 180))
                    : null,
            ]);
        }
    }
}
