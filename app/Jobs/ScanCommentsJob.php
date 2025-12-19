<?php

namespace App\Jobs;

use App\Contracts\PlatformServiceInterface;
use App\Models\Filter;
use App\Models\ModerationLog;
use App\Models\PendingModeration;
use App\Models\UsageRecord;
use App\Models\UserContent;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use App\Services\PlatformServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScanCommentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    private UserPlatform $userPlatform;

    private int $maxContents;

    private int $maxCommentsPerContent;

    private ?string $specificContentId;

    public function __construct(
        UserPlatform $userPlatform,
        int $maxContents = 10,
        int $maxCommentsPerContent = 100,
        ?string $specificContentId = null
    ) {
        $this->userPlatform = $userPlatform;
        $this->maxContents = $maxContents;
        $this->maxCommentsPerContent = $maxCommentsPerContent;
        $this->specificContentId = $specificContentId;
    }

    public function handle(FilterMatcher $filterMatcher): void
    {
        $user = $this->userPlatform->user;
        $platform = $this->userPlatform->platform;
        $useReviewQueue = $this->userPlatform->usesReviewQueue();
        $isIncremental = $this->userPlatform->isIncrementalScan();

        Log::info('ScanCommentsJob: Starting scan', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'platform' => $platform->name,
            'mode' => $isIncremental ? 'incremental' : 'full',
            'use_review_queue' => $useReviewQueue,
            'specific_content' => $this->specificContentId,
        ]);

        if (! $useReviewQueue && ! $user->canPerformAction()) {
            Log::warning('ScanCommentsJob: User quota exceeded', ['user_id' => $user->id]);

            return;
        }

        $filters = $user->getActiveFilters($platform->name);

        if ($filters->isEmpty()) {
            Log::info('ScanCommentsJob: No active filters found', ['user_id' => $user->id]);
            $this->userPlatform->update(['last_scanned_at' => now()]);

            return;
        }

        $service = PlatformServiceFactory::make($this->userPlatform);

        $connectionTest = $service->testConnection();
        if (! $connectionTest['success']) {
            Log::error('ScanCommentsJob: Connection failed', [
                'user_platform_id' => $this->userPlatform->id,
                'error' => $connectionTest['error'] ?? 'Unknown',
            ]);

            return;
        }

        $contents = $this->getContentsToScan($service);

        if (empty($contents)) {
            Log::info('ScanCommentsJob: No contents to scan', [
                'platform' => $platform->name,
            ]);
            $this->userPlatform->update(['last_scanned_at' => now()]);

            return;
        }

        $totalScanned = 0;
        $totalMatched = 0;
        $totalActioned = 0;
        $totalQueued = 0;
        $totalFailed = 0;

        foreach ($contents as $content) {
            $contentId = $content['contentId'];
            $contentType = $content['contentType'] ?? $service->getContentType();
            $contentTitle = $content['title'];
            $thumbnail = $content['thumbnail'] ?? null;
            $platformUrl = $content['platformUrl'] ?? null;
            $publishedAt = $content['publishedAt'] ?? null;

            $userContent = UserContent::findOrCreateFromPlatform(
                $user->id,
                $this->userPlatform->id,
                $contentId,
                $contentType,
                $contentTitle,
                $thumbnail,
                $platformUrl,
                $publishedAt
            );

            if (! $userContent->scan_enabled) {
                Log::debug('ScanCommentsJob: Content scan disabled', ['content_id' => $contentId]);

                continue;
            }

            Log::debug('ScanCommentsJob: Scanning content', [
                'content_id' => $contentId,
                'content_type' => $contentType,
                'title' => $contentTitle,
                'checkpoint' => $userContent->last_scanned_comment_id,
            ]);

            $commentsData = $service->getComments($contentId, $this->maxCommentsPerContent);

            if (! empty($commentsData['commentsDisabled'])) {
                continue;
            }

            $contentScanned = 0;
            $contentMatched = 0;
            $lastCommentId = null;

            foreach ($commentsData['items'] as $comment) {
                if ($isIncremental && $userContent->last_scanned_comment_id) {
                    if ($comment['id'] === $userContent->last_scanned_comment_id) {
                        Log::debug('ScanCommentsJob: Reached checkpoint', [
                            'content_id' => $contentId,
                            'checkpoint' => $comment['id'],
                        ]);
                        break;
                    }
                }

                if ($lastCommentId === null) {
                    $lastCommentId = $comment['id'];
                }

                $totalScanned++;
                $contentScanned++;

                if (! $useReviewQueue && ! $user->refresh()->canPerformAction()) {
                    Log::warning('ScanCommentsJob: Quota exhausted mid-scan', [
                        'user_id' => $user->id,
                        'scanned' => $totalScanned,
                        'actioned' => $totalActioned,
                    ]);
                    break 2;
                }

                $commentText = $comment['textOriginal'] ?? $comment['textDisplay'] ?? '';
                $matchedFilter = $filterMatcher->findMatch($commentText, $filters);

                if (! $matchedFilter) {
                    continue;
                }

                $totalMatched++;
                $contentMatched++;

                Log::info('ScanCommentsJob: Filter matched', [
                    'comment_id' => $comment['id'],
                    'filter_id' => $matchedFilter->id,
                    'pattern' => $matchedFilter->pattern,
                    'action' => $matchedFilter->action,
                    'use_queue' => $useReviewQueue,
                ]);

                $existsInQueue = PendingModeration::where('user_platform_id', $this->userPlatform->id)
                    ->where('platform_comment_id', $comment['id'])
                    ->exists();

                $existsInLog = ModerationLog::where('user_platform_id', $this->userPlatform->id)
                    ->where('platform_comment_id', $comment['id'])
                    ->exists();

                if ($existsInQueue || $existsInLog) {
                    Log::debug('ScanCommentsJob: Comment already processed', ['comment_id' => $comment['id']]);

                    continue;
                }

                if ($useReviewQueue) {
                    $this->addToReviewQueue($comment, $matchedFilter, $contentId, $contentType, $contentTitle, $userContent);
                    $totalQueued++;
                    $matchedFilter->incrementHitCount();
                } else {
                    $result = $this->executeAction($service, $comment, $matchedFilter);

                    DB::transaction(function () use ($user, $comment, $contentId, $contentType, $matchedFilter, $result, &$totalActioned, &$totalFailed) {
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
                            'content_id' => $contentId,
                            'content_type' => $contentType,
                            'commenter_username' => $comment['authorDisplayName'],
                            'commenter_id' => $comment['authorChannelId'] ?? null,
                            'comment_text' => mb_substr($comment['textOriginal'] ?? $comment['textDisplay'] ?? '', 0, 1000),
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

            if ($lastCommentId) {
                $userContent->updateCheckpoint($lastCommentId, $contentScanned, $contentMatched);
            }
        }

        $this->userPlatform->update(['last_scanned_at' => now()]);

        Log::info('ScanCommentsJob: Scan completed', [
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'platform' => $platform->name,
            'total_scanned' => $totalScanned,
            'total_matched' => $totalMatched,
            'total_queued' => $totalQueued,
            'total_actioned' => $totalActioned,
            'total_failed' => $totalFailed,
        ]);
    }

    private function getContentsToScan(PlatformServiceInterface $service): array
    {
        if ($this->specificContentId) {
            return [
                [
                    'contentId' => $this->specificContentId,
                    'contentType' => $service->getContentType(),
                    'title' => 'Specific Content',
                    'thumbnail' => null,
                    'platformUrl' => null,
                    'publishedAt' => null,
                ],
            ];
        }

        $contentsData = $service->getContents($this->maxContents);

        return $contentsData['items'] ?? [];
    }

    private function addToReviewQueue(
        array $comment,
        Filter $filter,
        string $contentId,
        string $contentType,
        string $contentTitle,
        UserContent $userContent
    ): void {
        $user = $this->userPlatform->user;

        PendingModeration::create([
            'user_id' => $user->id,
            'user_platform_id' => $this->userPlatform->id,
            'user_content_id' => $userContent->id,
            'platform_comment_id' => $comment['id'],
            'content_id' => $contentId,
            'content_type' => $contentType,
            'content_title' => $contentTitle,
            'commenter_username' => $comment['authorDisplayName'],
            'commenter_id' => $comment['authorChannelId'] ?? null,
            'commenter_profile_url' => $comment['authorChannelId']
                ? $this->getCommenterProfileUrl($comment['authorChannelId'])
                : null,
            'comment_text' => mb_substr($comment['textOriginal'] ?? $comment['textDisplay'] ?? '', 0, 1000),
            'matched_filter_id' => $filter->id,
            'matched_pattern' => $filter->pattern,
            'confidence_score' => 100,
            'status' => PendingModeration::STATUS_PENDING,
            'detected_at' => now(),
        ]);
    }

    private function getCommenterProfileUrl(?string $authorChannelId): ?string
    {
        if (! $authorChannelId) {
            return null;
        }

        $platformName = $this->userPlatform->platform->name;

        return match ($platformName) {
            'youtube' => "https://www.youtube.com/channel/{$authorChannelId}",
            'instagram' => "https://www.instagram.com/{$authorChannelId}",
            default => null,
        };
    }

    private function executeAction(PlatformServiceInterface $service, array $comment, Filter $filter): array
    {
        return match ($filter->action) {
            Filter::ACTION_DELETE => $service->deleteComment($comment['id']),
            Filter::ACTION_HIDE => $service->hideComment($comment['id']),
            Filter::ACTION_FLAG => $service->hideComment($comment['id']),
            Filter::ACTION_REPORT => $this->reportComment($comment, $filter),
            default => ['success' => false, 'error' => 'Unknown action: '.$filter->action],
        };
    }

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

    private function reportComment(array $comment, Filter $filter): array
    {
        Log::info('ScanCommentsJob: Comment flagged for report', [
            'comment_id' => $comment['id'],
            'filter_pattern' => $filter->pattern,
            'comment_text' => mb_substr($comment['textOriginal'] ?? '', 0, 200),
        ]);

        return ['success' => true];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ScanCommentsJob: Job failed', [
            'user_platform_id' => $this->userPlatform->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
