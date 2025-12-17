<?php

namespace App\Console\Commands;

use App\Jobs\ScanCommentsJob;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use App\Services\PlatformServiceFactory;
use App\Services\Platforms\Youtube\YouTubeRateLimiter;
use Illuminate\Console\Command;

class ScanComments extends Command
{
    protected $signature = 'comments:scan
        {--user= : User ID or email to scan for}
        {--platform= : Platform name (youtube, instagram, etc)}
        {--contents=10 : Maximum number of contents to scan}
        {--comments=100 : Maximum comments per content}
        {--dry-run : Show what would be done without executing actions}
        {--sync : Run synchronously instead of queuing}
        {--stats : Show API quota statistics}';

    protected $description = 'Scan comments on connected platforms and apply moderation filters';

    public function handle(FilterMatcher $filterMatcher): int
    {
        if ($this->option('stats')) {
            return $this->showQuotaStats();
        }

        $userOption = $this->option('user');
        $platformName = $this->option('platform');
        $maxContents = (int) $this->option('contents');
        $maxComments = (int) $this->option('comments');
        $dryRun = $this->option('dry-run');
        $sync = $this->option('sync');

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

        $query = UserPlatform::with(['user', 'platform'])
            ->where('is_active', true);

        if ($platformName) {
            $query->whereHas('platform', fn ($q) => $q->where('name', $platformName));
        }

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
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
            $platformDisplay = $userPlatform->platform->name;
            $accountName = $userPlatform->platform_username ?? $userPlatform->platform_channel_id;

            $this->info("Scanning: {$userName} - {$platformDisplay} ({$accountName})");

            if (! PlatformServiceFactory::supports($userPlatform->platform->name)) {
                $this->warn("  → Platform not supported: {$platformDisplay}");
                continue;
            }

            if ($dryRun) {
                $this->dryRunScan($userPlatform, $filterMatcher, $maxContents, $maxComments);
            } elseif ($sync) {
                $this->syncScan($userPlatform, $filterMatcher, $maxContents, $maxComments);
            } else {
                ScanCommentsJob::dispatch($userPlatform, $maxContents, $maxComments);
                $this->info('  → Job dispatched to queue');
            }

            $this->newLine();
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    private function dryRunScan(
        UserPlatform $userPlatform,
        FilterMatcher $filterMatcher,
        int $maxContents,
        int $maxComments
    ): void {
        $user = $userPlatform->user;
        $platformName = $userPlatform->platform->name;
        $filters = $user->getActiveFilters($platformName);

        if ($filters->isEmpty()) {
            $this->warn('  No active filters found for this user.');
            return;
        }

        $this->info("  Active filters: {$filters->count()}");

        $service = PlatformServiceFactory::make($userPlatform);

        $connectionTest = $service->testConnection();
        if (! $connectionTest['success']) {
            $this->error("  Connection failed: ".($connectionTest['error'] ?? 'Unknown'));
            return;
        }

        $this->info("  Account: ".($connectionTest['channel']['title'] ?? $connectionTest['account']['username'] ?? 'Connected'));

        $contentsData = $service->getContents($maxContents);

        if (empty($contentsData['items'])) {
            $this->warn('  No contents found.');
            return;
        }

        $this->info('  Contents found: '.count($contentsData['items']));

        $totalComments = 0;
        $totalMatches = 0;

        foreach ($contentsData['items'] as $content) {
            $contentId = $content['contentId'];
            $contentTitle = $content['title'] ?? 'Unknown';

            $this->line("  → Scanning: {$contentTitle}");

            $commentsData = $service->getComments($contentId, $maxComments);

            if (! empty($commentsData['commentsDisabled'])) {
                $this->line('    (comments disabled)');
                continue;
            }

            $comments = $commentsData['items'];
            $totalComments += count($comments);

            foreach ($comments as $comment) {
                $commentText = $comment['textOriginal'] ?? $comment['textDisplay'] ?? '';
                $matchedFilter = $filterMatcher->findMatch($commentText, $filters);

                if ($matchedFilter) {
                    $totalMatches++;
                    $this->warn("    [MATCH] Filter: {$matchedFilter->pattern}");
                    $this->line("            Action: {$matchedFilter->action}");
                    $this->line('            Comment: '.mb_substr($commentText, 0, 80).'...');
                    $this->line("            Author: ".($comment['authorDisplayName'] ?? 'Unknown'));
                }
            }
        }

        $this->newLine();
        $this->info('  Summary:');
        $this->line("    Total comments scanned: {$totalComments}");
        $this->line("    Total matches found: {$totalMatches}");
    }

    private function syncScan(
        UserPlatform $userPlatform,
        FilterMatcher $filterMatcher,
        int $maxContents,
        int $maxComments
    ): void {
        $this->info('  Running synchronous scan...');

        $job = new ScanCommentsJob($userPlatform, $maxContents, $maxComments);
        $job->handle($filterMatcher);

        $this->info('  Scan completed.');
    }

    private function showQuotaStats(): int
    {
        $limiter = new YouTubeRateLimiter;
        $stats = $limiter->getQuotaStats();

        $this->info('YouTube API Quota Statistics');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Daily Limit', number_format($stats['limit']).' units'],
                ['Used Today', number_format($stats['used']).' units'],
                ['Remaining', number_format($stats['remaining']).' units'],
                ['Usage', $stats['percentage'].'%'],
                ['Can Delete', $stats['can_delete_comments'].' comments'],
                ['Resets At', $stats['reset_at']],
            ]
        );

        $this->newLine();
        $this->info('Quota Costs:');
        $this->table(
            ['Operation', 'Cost'],
            [
                ['List Videos', '1 unit'],
                ['List Comments', '1 unit'],
                ['Delete Comment', '50 units'],
                ['Search', '100 units'],
            ]
        );

        if ($stats['percentage'] >= 80) {
            $this->newLine();
            $this->error('Warning: Quota usage is above 80%!');
        }

        return Command::SUCCESS;
    }
}
