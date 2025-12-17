<?php

namespace App\Jobs;

use App\Models\Filter;
use App\Models\ModerationLog;
use App\Models\PendingModeration;
use App\Models\UsageRecord;
use App\Models\UserPlatform;
use App\Models\UserVideo;
use App\Services\FilterMatcher;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

    private ?string $specificVideoId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        UserPlatform $userPlatform,
        int $maxVideos = 10,
        int $maxCommentsPerVideo = 100,
        ?string $specificVideoId = null
    ) {
        $this->userPlatform = $userPlatform;
        $this->maxVideos = $maxVideos;
        $this->maxCommentsPerVideo = $maxCommentsPerVideo;
        $this->specificVideoId = $specificVideoId;
    }

    /**
     * Execute the job.
     */
    public function handle(FilterMatcher $filterMatcher): void
    {
        $user = $this->userPlatform->user;
        $platform = $this->userPlatform->platform;
        $useReviewQueue = $this->userPlatform->usesReviewQueue();
        $isIncremental = $this->userPlatform->isIncrementalScan();

        Log::info('ScanYouTubeCommentsJob: Starting scan', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'channel_id' => $this->userPlatform->platform_channel_id,
            'mode' => $isIncremental ? 'incremental' : 'full',
            'use_review_queue' => $useReviewQueue,
            'specific_video' => $this->specificVideoId,
        ]);

        // Only check quota if auto-delete is enabled
        if (! $useReviewQueue && ! $user->canPerformAction()) {
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

        // Get videos to scan
        $videos = $this->getVideosToScan($youtube);

        if (empty($videos)) {
            Log::info('ScanYouTubeCommentsJob: No videos to scan', [
                'channel_id' => $this->userPlatform->platform_channel_id,
            ]);

            $this->userPlatform->update(['last_scanned_at' => now()]);

            return;
        }

        $totalScanned = 0;
        $totalMatched = 0;
        $totalActioned = 0;
        $totalQueued = 0;
        $totalFailed = 0;

        foreach ($videos as $video) {
            $videoId = $video['videoId'];
            $videoTitle = $video['title'];
            $videoThumbnail = $video['thumbnail'] ?? null;
            $publishedAt = $video['publishedAt'] ?? null;

            // Get or create UserVideo record for checkpoint
            $userVideo = UserVideo::findOrCreateFromYouTube(
                $user->id,
                $this->userPlatform->id,
                $videoId,
                $videoTitle,
                $videoThumbnail,
                $publishedAt
            );

            // Skip if video is disabled for scanning
            if (! $userVideo->scan_enabled) {
                Log::debug('ScanYouTubeCommentsJob: Video scan disabled', [
                    'video_id' => $videoId,
                ]);

                continue;
            }

            Log::debug('ScanYouTubeCommentsJob: Scanning video', [
                'video_id' => $videoId,
                'title' => $videoTitle,
                'checkpoint' => $userVideo->last_scanned_comment_id,
            ]);

            // Get comments for this video
            $commentsData = $youtube->getVideoComments($videoId, $this->maxCommentsPerVideo);

            if (! empty($commentsData['commentsDisabled'])) {
                continue;
            }

            $videoScanned = 0;
            $videoMatched = 0;
            $lastCommentId = null;

            foreach ($commentsData['items'] as $comment) {
                // For incremental scan, stop at checkpoint
                if ($isIncremental && $userVideo->last_scanned_comment_id) {
                    if ($comment['id'] === $userVideo->last_scanned_comment_id) {
                        Log::debug('ScanYouTubeCommentsJob: Reached checkpoint', [
                            'video_id' => $videoId,
                            'checkpoint' => $comment['id'],
                        ]);
                        break;
                    }
                }

                // Track first comment as new checkpoint
                if ($lastCommentId === null) {
                    $lastCommentId = $comment['id'];
                }

                $totalScanned++;
                $videoScanned++;

                // Check quota before each action (only if auto-delete enabled)
                if (! $useReviewQueue && ! $user->refresh()->canPerformAction()) {
                    Log::warning('ScanYouTubeCommentsJob: Quota exhausted mid-scan', [
                        'user_id' => $user->id,
                        'scanned' => $totalScanned,
                        'actioned' => $totalActioned,
                    ]);

                    break 2;
                }

                // Check if comment matches any filter
                $matchedFilter = $filterMatcher->findMatch($comment['textOriginal'], $filters);

                if (! $matchedFilter) {
                    continue;
                }

                $totalMatched++;
                $videoMatched++;

                Log::info('ScanYouTubeCommentsJob: Filter matched', [
                    'comment_id' => $comment['id'],
                    'filter_id' => $matchedFilter->id,
                    'pattern' => $matchedFilter->pattern,
                    'action' => $matchedFilter->action,
                    'use_queue' => $useReviewQueue,
                ]);

                // Check if already in pending queue or moderation log
                $existsInQueue = PendingModeration::where('user_platform_id', $this->userPlatform->id)
                    ->where('platform_comment_id', $comment['id'])
                    ->exists();

                $existsInLog = ModerationLog::where('user_platform_id', $this->userPlatform->id)
                    ->where('platform_comment_id', $comment['id'])
                    ->exists();

                if ($existsInQueue || $existsInLog) {
                    Log::debug('ScanYouTubeCommentsJob: Comment already processed', [
                        'comment_id' => $comment['id'],
                    ]);

                    continue;
                }

                if ($useReviewQueue) {
                    // Add to review queue instead of auto-delete
                    $this->addToReviewQueue(
                        $comment,
                        $matchedFilter,
                        $videoId,
                        $videoTitle,
                        $userVideo
                    );
                    $totalQueued++;
                    $matchedFilter->incrementHitCount();
                } else {
                    // Execute action immediately (old behavior)
                    $result = $this->executeAction($youtube, $comment, $matchedFilter, $videoId);

                    DB::transaction(function () use ($user, $comment, $videoId, $matchedFilter, $result, &$totalActioned, &$totalFailed) {
                        if ($result['success']) {
                            $totalActioned++;
                            $matchedFilter->incrementHitCount();
                            UsageRecord::recordAction($user->id, 'comment_moderated');
                        } else {
                            $totalFailed++;
                        }

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
                    });
                }
            }

            // Update checkpoint for this video
            if ($lastCommentId) {
                $userVideo->updateCheckpoint($lastCommentId, $videoScanned, $videoMatched);
            }
        }

        // Update last scanned timestamp
        $this->userPlatform->update(['last_scanned_at' => now()]);

        Log::info('ScanYouTubeCommentsJob: Scan completed', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'total_scanned' => $totalScanned,
            'total_matched' => $totalMatched,
            'total_queued' => $totalQueued,
            'total_actioned' => $totalActioned,
            'total_failed' => $totalFailed,
        ]);
    }

    /**
     * Get videos to scan based on mode.
     */
    private function getVideosToScan(YouTubeService $youtube): array
    {
        // If specific video requested
        if ($this->specificVideoId) {
            return [
                [
                    'videoId' => $this->specificVideoId,
                    'title' => 'Specific Video',
                    'thumbnail' => null,
                    'publishedAt' => null,
                ],
            ];
        }

        // Get channel videos
        $videosData = $youtube->getChannelVideos($this->maxVideos);
        $videos = [];

        foreach ($videosData['items'] ?? [] as $video) {
            $videoId = $video['contentDetails']['videoId']
                ?? $video['snippet']['resourceId']['videoId']
                ?? null;

            if (! $videoId) {
                continue;
            }

            $videos[] = [
                'videoId' => $videoId,
                'title' => $video['snippet']['title'] ?? 'Unknown',
                'thumbnail' => $video['snippet']['thumbnails']['medium']['url']
                    ?? $video['snippet']['thumbnails']['default']['url']
                    ?? null,
                'publishedAt' => $video['snippet']['publishedAt'] ?? null,
            ];
        }

        return $videos;
    }

    /**
     * Add matched comment to review queue.
     */
    private function addToReviewQueue(
        array $comment,
        Filter $filter,
        string $videoId,
        string $videoTitle,
        UserVideo $userVideo
    ): void {
        $user = $this->userPlatform->user;

        PendingModeration::create([
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'user_video_id' => $userVideo->id,
            'platform_comment_id' => $comment['id'],
            'video_id' => $videoId,
            'video_title' => $videoTitle,
            'commenter_username' => $comment['authorDisplayName'],
            'commenter_id' => $comment['authorChannelId'],
            'commenter_profile_url' => $comment['authorChannelId']
                ? "https://www.youtube.com/channel/{$comment['authorChannelId']}"
                : null,
            'comment_text' => mb_substr($comment['textOriginal'], 0, 1000),
            'matched_filter_id' => $filter->id,
            'matched_pattern' => $filter->pattern,
            'confidence_score' => 100, // Could be enhanced with ML scoring later
            'status' => PendingModeration::STATUS_PENDING,
            'detected_at' => now(),
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
