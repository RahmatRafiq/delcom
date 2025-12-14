<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\ModerationLog;
use App\Models\User;
use App\Models\UserPlatform;
use Illuminate\Database\Seeder;

class ModerationLogSeeder extends Seeder
{
    private array $spamComments = [
        'Slot gacor hari ini maxwin 100jt! Cek wa.me/628123456789',
        'Togel online terpercaya deposit pulsa tanpa potongan',
        'Judi bola online terbaik link di bio',
        'Link alternatif slot gacor bit.ly/slotmaxwin',
        'Slot maxwin hari ini RTP 99% pasti menang!',
    ];

    private array $promoComments = [
        'Cek bio untuk info lebih lanjut ya guys!',
        'Sub balik dong, bantu channel baru',
        'Gratis followers 10k DM sekarang',
        'Follow balik ya, channel baru nih',
        'Link di bio untuk dapat hadiah gratis',
    ];

    private array $normalComments = [
        'Nice video! Keep up the good work',
        'Konten yang sangat bermanfaat, terima kasih!',
        'Penjelasannya mudah dipahami',
    ];

    private array $usernames = [
        'SpamBot123', 'SlotGacor99', 'PromoKing', 'JudiOnline',
        'BuzzerAccount', 'RealViewer', 'GenuineFan', 'HappyWatcher',
    ];

    public function run(): void
    {
        $this->seedAdminLogs();
        $this->seedUserLogs();
    }

    private function seedAdminLogs(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            return;
        }

        $userPlatforms = UserPlatform::where('user_id', $admin->id)->get();

        if ($userPlatforms->isEmpty()) {
            return;
        }

        $filters = Filter::whereHas('filterGroup', fn ($q) => $q->where('user_id', $admin->id))->get();

        // Create spam logs (deleted)
        foreach ($this->spamComments as $comment) {
            $this->createLog($admin->id, $userPlatforms->random(), $filters, $comment, 'deleted', 'background_job');
        }

        // Create promo logs (hidden/flagged)
        foreach ($this->promoComments as $comment) {
            $this->createLog($admin->id, $userPlatforms->random(), $filters, $comment, fake()->randomElement(['hidden', 'flagged']), 'background_job');
        }

        // Create manually flagged logs
        foreach ($this->normalComments as $comment) {
            $this->createLog($admin->id, $userPlatforms->random(), collect(), $comment, 'flagged', 'manual');
        }

        // Create additional random logs for admin
        for ($i = 0; $i < 30; $i++) {
            $isFiltered = fake()->boolean(70);
            $comment = $isFiltered ? fake()->randomElement($this->spamComments) : fake()->sentence();
            $this->createLog(
                $admin->id,
                $userPlatforms->random(),
                $isFiltered ? $filters : collect(),
                $comment,
                fake()->randomElement(['deleted', 'hidden', 'flagged']),
                $isFiltered ? 'background_job' : 'manual'
            );
        }
    }

    private function seedUserLogs(): void
    {
        $user = User::where('email', 'user@example.com')->first();

        if (! $user) {
            return;
        }

        $userPlatforms = UserPlatform::where('user_id', $user->id)->get();

        if ($userPlatforms->isEmpty()) {
            return;
        }

        $filters = Filter::whereHas('filterGroup', fn ($q) => $q->where('user_id', $user->id))->get();

        // Create some spam logs for user
        $userSpamComments = [
            'Slot gacor maxwin hari ini! wa.me/6281234567890',
            'Togel singapore result hari ini, cek bit.ly/togel123',
            'Judi online terpercaya deposit via pulsa',
        ];

        foreach ($userSpamComments as $comment) {
            $this->createLog($user->id, $userPlatforms->random(), $filters, $comment, 'deleted', 'background_job');
        }

        // Create some flagged comments
        $flaggedComments = [
            'Kunjungi channel saya ya! Sub balik!',
            'Link di bio untuk dapat bonus gratis',
        ];

        foreach ($flaggedComments as $comment) {
            $this->createLog($user->id, $userPlatforms->random(), $filters, $comment, 'flagged', 'background_job');
        }

        // Create additional random logs for user
        for ($i = 0; $i < 15; $i++) {
            $isFiltered = fake()->boolean(60);
            $comment = $isFiltered ? fake()->randomElement($this->spamComments) : fake()->sentence();
            $this->createLog(
                $user->id,
                $userPlatforms->random(),
                $isFiltered ? $filters : collect(),
                $comment,
                fake()->randomElement(['deleted', 'hidden', 'flagged']),
                $isFiltered ? 'background_job' : 'manual'
            );
        }
    }

    private function createLog(int $userId, UserPlatform $userPlatform, $filters, string $comment, string $action, string $source): void
    {
        $filter = $filters->isNotEmpty() ? $filters->random() : null;

        ModerationLog::create([
            'user_id' => $userId,
            'user_platform_id' => $userPlatform->id,
            'platform_comment_id' => fake()->uuid(),
            'comment_text' => $comment,
            'commenter_id' => 'user_'.fake()->numerify('##########'),
            'commenter_username' => fake()->randomElement($this->usernames),
            'video_id' => fake()->regexify('[A-Za-z0-9]{11}'),
            'matched_filter_id' => $filter?->id,
            'matched_pattern' => $filter?->pattern,
            'action_taken' => $action,
            'action_source' => $source,
            'processed_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ]);
    }
}
