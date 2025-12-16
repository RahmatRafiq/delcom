<?php

namespace App\Console\Commands;

use App\Jobs\ScanYouTubeCommentsJob;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class ScanComments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'comments:scan
        {--user= : User ID or email to scan for}
        {--platform= : Platform name (youtube, instagram, etc)}
        {--videos=10 : Maximum number of videos to scan}
        {--comments=100 : Maximum comments per video}
        {--dry-run : Show what would be done without executing actions}
        {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     */
    protected $description = 'Scan comments on connected platforms and apply moderation filters';

    /**
     * Execute the console command.
     */
    public function handle(FilterMatcher $filterMatcher): int
    {
        $userOption = $this->option('user');
        $platformName = $this->option('platform') ?? 'youtube';
        $maxVideos = (int) $this->option('videos');
        $maxComments = (int) $this->option('comments');
        $dryRun = $this->option('dry-run');
        $sync = $this->option('sync');

        // Find user
        $user = null;
        if ($userOption) {
            $user = is_numeric($userOption)
                ? User::find($userOption)
                : User::where('email', $userOption)->first();

            if (! $user) {
                $this->error("User not found: {$userOption}");

                return Command::FAILURE;
            }
        }

        // Get user platforms to scan
        $query = UserPlatform::with(['user', 'platform'])
            ->whereHas('platform', fn ($q) => $q->where('name', $platformName))
            ->where('is_active', true);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            // Only get platforms that need scanning (auto moderation enabled)
            $query->where('auto_moderation_enabled', true);
        }

        $userPlatforms = $query->get();

        if ($userPlatforms->isEmpty()) {
            $this->warn('No active platform connections found to scan.');

            return Command::SUCCESS;
        }

        $this->info("Found {$userPlatforms->count()} platform(s) to scan.");
        $this->newLine();

        foreach ($userPlatforms as $userPlatform) {
            $userName = $userPlatform->user->name;
            $channelName = $userPlatform->platform_username ?? $userPlatform->platform_channel_id;

            $this->info("Scanning: {$userName} - {$channelName}");

            if ($dryRun) {
                $this->dryRunScan($userPlatform, $filterMatcher, $maxVideos, $maxComments);
            } elseif ($sync) {
                $this->syncScan($userPlatform, $filterMatcher, $maxVideos, $maxComments);
            } else {
                ScanYouTubeCommentsJob::dispatch($userPlatform, $maxVideos, $maxComments);
                $this->info('  → Job dispatched to queue');
            }

            $this->newLine();
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    /**
     * Run a dry-run scan (no actual actions).
     */
    private function dryRunScan(
        UserPlatform $userPlatform,
        FilterMatcher $filterMatcher,
        int $maxVideos,
        int $maxComments
    ): void {
        $user = $userPlatform->user;
        $filters = $user->getActiveFilters('youtube');

        if ($filters->isEmpty()) {
            $this->warn('  No active filters found for this user.');

            return;
        }

        $this->info("  Active filters: {$filters->count()}");

        $youtube = YouTubeService::for($userPlatform);

        // Test connection
        $connectionTest = $youtube->testConnection();
        if (! $connectionTest['success']) {
            $this->error("  Connection failed: {$connectionTest['error']}");

            return;
        }

        $this->info("  Channel: {$connectionTest['channel']['title']}");

        // Get videos
        $videosData = $youtube->getChannelVideos($maxVideos);

        if (empty($videosData['items'])) {
            $this->warn('  No videos found.');

            return;
        }

        $this->info("  Videos found: ".count($videosData['items']));

        $totalComments = 0;
        $totalMatches = 0;

        foreach ($videosData['items'] as $video) {
            $videoId = $video['contentDetails']['videoId'] ?? $video['snippet']['resourceId']['videoId'] ?? null;
            $videoTitle = $video['snippet']['title'] ?? 'Unknown';

            if (! $videoId) {
                continue;
            }

            $this->line("  → Scanning: {$videoTitle}");

            $commentsData = $youtube->getVideoComments($videoId, $maxComments);

            if (! empty($commentsData['commentsDisabled'])) {
                $this->line('    (comments disabled)');

                continue;
            }

            $comments = $commentsData['items'];
            $totalComments += count($comments);

            foreach ($comments as $comment) {
                $matchedFilter = $filterMatcher->findMatch($comment['textOriginal'], $filters);

                if ($matchedFilter) {
                    $totalMatches++;
                    $this->warn("    [MATCH] Filter: {$matchedFilter->pattern}");
                    $this->line("            Action: {$matchedFilter->action}");
                    $this->line("            Comment: ".mb_substr($comment['textOriginal'], 0, 80).'...');
                    $this->line("            Author: {$comment['authorDisplayName']}");
                }
            }
        }

        $this->newLine();
        $this->info("  Summary:");
        $this->line("    Total comments scanned: {$totalComments}");
        $this->line("    Total matches found: {$totalMatches}");
    }

    /**
     * Run a synchronous scan (immediate execution).
     */
    private function syncScan(
        UserPlatform $userPlatform,
        FilterMatcher $filterMatcher,
        int $maxVideos,
        int $maxComments
    ): void {
        $this->info('  Running synchronous scan...');

        $job = new ScanYouTubeCommentsJob($userPlatform, $maxVideos, $maxComments);
        $job->handle($filterMatcher);

        $this->info('  Scan completed.');
    }
}
