<?php

namespace App\Jobs;

use App\Models\Filter;
use App\Models\ModerationLog;
use App\Models\UsageRecord;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanYouTubeCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300; // 5 minutes

    private UserPlatform $userPlatform;

    private int $maxVideos;

    private int $maxCommentsPerVideo;

    /**
     * Create a new job instance.
     */
    public function __construct(
        UserPlatform $userPlatform,
        int $maxVideos = 10,
        int $maxCommentsPerVideo = 100
    ) {
        $this->userPlatform = $userPlatform;
        $this->maxVideos = $maxVideos;
        $this->maxCommentsPerVideo = $maxCommentsPerVideo;
    }

    /**
     * Execute the job.
     */
    public function handle(FilterMatcher $filterMatcher): void
    {
        $user = $this->userPlatform->user;
        $platform = $this->userPlatform->platform;

        Log::info('ScanYouTubeCommentsJob: Starting scan', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'channel_id' => $this->userPlatform->platform_channel_id,
        ]);

        // Check if user can perform actions (quota check)
        if (! $user->canPerformAction()) {
            Log::warning('ScanYouTubeCommentsJob: User quota exceeded', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // Get user's active filters for YouTube
        $filters = $user->getActiveFilters($platform->name);

        if ($filters->isEmpty()) {
            Log::info('ScanYouTubeCommentsJob: No active filters found', [
                'user_id' => $user->id,
            ]);

            // Still update last_scanned_at
            $this->userPlatform->update(['last_scanned_at' => now()]);

            return;
        }

        // Initialize YouTube service
        $youtube = YouTubeService::for($this->userPlatform);

        // Test connection first
        $connectionTest = $youtube->testConnection();
        if (! $connectionTest['success']) {
            Log::error('ScanYouTubeCommentsJob: Connection failed', [
                'user_platform_id' => $this->userPlatform->id,
                'error' => $connectionTest['error'] ?? 'Unknown',
            ]);

            return;
        }

        // Get channel videos
        $videosData = $youtube->getChannelVideos($this->maxVideos);
        $videos = $videosData['items'];

        if (empty($videos)) {
            Log::info('ScanYouTubeCommentsJob: No videos found', [
                'channel_id' => $this->userPlatform->platform_channel_id,
            ]);

            $this->userPlatform->update(['last_scanned_at' => now()]);

            return;
        }

        $totalScanned = 0;
        $totalMatched = 0;
        $totalActioned = 0;
        $totalFailed = 0;

        foreach ($videos as $video) {
            $videoId = $video['contentDetails']['videoId'] ?? $video['snippet']['resourceId']['videoId'] ?? null;

            if (! $videoId) {
                continue;
            }

            $videoTitle = $video['snippet']['title'] ?? 'Unknown';

            Log::debug('ScanYouTubeCommentsJob: Scanning video', [
                'video_id' => $videoId,
                'title' => $videoTitle,
            ]);

            // Get comments for this video
            $commentsData = $youtube->getVideoComments($videoId, $this->maxCommentsPerVideo);

            if (! empty($commentsData['commentsDisabled'])) {
                continue;
            }

            foreach ($commentsData['items'] as $comment) {
                $totalScanned++;

                // Check quota before each action
                if (! $user->refresh()->canPerformAction()) {
                    Log::warning('ScanYouTubeCommentsJob: Quota exhausted mid-scan', [
                        'user_id' => $user->id,
                        'scanned' => $totalScanned,
                        'actioned' => $totalActioned,
                    ]);

                    break 2; // Break out of both loops
                }

                // Check if comment matches any filter
                $matchedFilter = $filterMatcher->findMatch($comment['textOriginal'], $filters);

                if (! $matchedFilter) {
                    continue;
                }

                $totalMatched++;

                Log::info('ScanYouTubeCommentsJob: Filter matched', [
                    'comment_id' => $comment['id'],
                    'filter_id' => $matchedFilter->id,
                    'pattern' => $matchedFilter->pattern,
                    'action' => $matchedFilter->action,
                ]);

                // Execute action based on filter setting
                $result = $this->executeAction(
                    $youtube,
                    $comment,
                    $matchedFilter,
                    $videoId
                );

                if ($result['success']) {
                    $totalActioned++;

                    // Increment filter hit count
                    $matchedFilter->incrementHitCount();

                    // Record usage
                    UsageRecord::recordAction($user->id, 'comment_moderated');
                } else {
                    $totalFailed++;
                }

                // Create moderation log
                ModerationLog::create([
                    'user_id' => $user->id,
                    'user_platform_id' => $this->userPlatform->id,
                    'platform_comment_id' => $comment['id'],
                    'video_id' => $videoId,
                    'commenter_username' => $comment['authorDisplayName'],
                    'commenter_id' => $comment['authorChannelId'],
                    'comment_text' => mb_substr($comment['textOriginal'], 0, 1000),
                    'matched_filter_id' => $matchedFilter->id,
                    'matched_pattern' => $matchedFilter->pattern,
                    'action_taken' => $result['success']
                        ? $this->mapActionToLogAction($matchedFilter->action)
                        : ModerationLog::ACTION_FAILED,
                    'action_source' => ModerationLog::SOURCE_BACKGROUND_JOB,
                    'failure_reason' => $result['error'] ?? null,
                    'processed_at' => now(),
                ]);
            }
        }

        // Update last scanned timestamp
        $this->userPlatform->update(['last_scanned_at' => now()]);

        Log::info('ScanYouTubeCommentsJob: Scan completed', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'total_scanned' => $totalScanned,
            'total_matched' => $totalMatched,
            'total_actioned' => $totalActioned,
            'total_failed' => $totalFailed,
        ]);
    }

    /**
     * Execute the moderation action.
     */
    private function executeAction(
        YouTubeService $youtube,
        array $comment,
        Filter $filter,
        string $videoId
    ): array {
        return match ($filter->action) {
            Filter::ACTION_DELETE => $youtube->deleteComment($comment['id']),
            Filter::ACTION_HIDE => $youtube->setModerationStatus($comment['id'], 'rejected'),
            Filter::ACTION_FLAG => $youtube->setModerationStatus($comment['id'], 'heldForReview'),
            Filter::ACTION_REPORT => $this->reportComment($comment, $filter, $videoId),
            default => ['success' => false, 'error' => 'Unknown action: '.$filter->action],
        };
    }

    /**
     * Map filter action to moderation log action.
     */
    private function mapActionToLogAction(string $filterAction): string
    {
        return match ($filterAction) {
            Filter::ACTION_DELETE => ModerationLog::ACTION_DELETED,
            Filter::ACTION_HIDE => ModerationLog::ACTION_HIDDEN,
            Filter::ACTION_FLAG => ModerationLog::ACTION_FLAGGED,
            Filter::ACTION_REPORT => ModerationLog::ACTION_REPORTED,
            default => ModerationLog::ACTION_FAILED,
        };
    }

    /**
     * Report a comment (just log it for now, YouTube doesn't have direct report API).
     */
    private function reportComment(array $comment, Filter $filter, string $videoId): array
    {
        // YouTube API doesn't have a direct "report" endpoint for comments
        // We'll just log it and mark as successful
        Log::info('ScanYouTubeCommentsJob: Comment flagged for report', [
            'comment_id' => $comment['id'],
            'video_id' => $videoId,
            'filter_pattern' => $filter->pattern,
            'comment_text' => mb_substr($comment['textOriginal'], 0, 200),
        ]);

        return ['success' => true];
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScanYouTubeCommentsJob: Job failed', [
            'user_platform_id' => $this->userPlatform->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
