<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class YouTubeRateLimiter
{
    /**
     * Quota costs for different operations (based on YouTube API).
     */
    public const QUOTA_COSTS = [
        'list_videos' => 1,
        'list_comments' => 1,
        'delete_comment' => 50,
        'set_moderation_status' => 50,
        'search' => 100,
    ];

    /**
     * Default daily quota limit (shared across all users).
     */
    public const DEFAULT_DAILY_QUOTA = 10000;

    /**
     * Maximum requests per minute per user (burst protection).
     */
    public const MAX_REQUESTS_PER_MINUTE = 30;

    /**
     * Check if user can perform an action based on their plan limits.
     */
    public function canPerformAction(User $user): bool
    {
        // Check plan-based monthly limit
        if (! $user->canPerformAction()) {
            return false;
        }

        // Check burst rate limit
        if ($this->isRateLimited($user)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user is rate limited (burst protection).
     */
    public function isRateLimited(User $user): bool
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        return $requests >= self::MAX_REQUESTS_PER_MINUTE;
    }

    /**
     * Increment request count for burst protection.
     */
    public function incrementRequestCount(User $user): void
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        Cache::put($key, $requests + 1, now()->addMinute());
    }

    /**
     * Track quota usage for an operation.
     */
    public function trackQuotaUsage(string $operation, int $count = 1): int
    {
        $cost = (self::QUOTA_COSTS[$operation] ?? 1) * $count;

        // Track daily global quota
        $dailyKey = 'youtube_quota:daily:'.now()->format('Y-m-d');
        $currentUsage = Cache::get($dailyKey, 0);
        $newUsage = $currentUsage + $cost;

        Cache::put($dailyKey, $newUsage, now()->endOfDay());

        // Log if approaching limit
        $percentUsed = ($newUsage / self::DEFAULT_DAILY_QUOTA) * 100;
        if ($percentUsed >= 80 && $percentUsed < 81) {
            Log::warning('YouTubeRateLimiter: Quota usage at 80%', [
                'used' => $newUsage,
                'limit' => self::DEFAULT_DAILY_QUOTA,
            ]);
        } elseif ($percentUsed >= 95) {
            Log::critical('YouTubeRateLimiter: Quota usage at 95%', [
                'used' => $newUsage,
                'limit' => self::DEFAULT_DAILY_QUOTA,
            ]);
        }

        return $cost;
    }

    /**
     * Get current daily quota usage.
     */
    public function getDailyQuotaUsage(): int
    {
        $dailyKey = 'youtube_quota:daily:'.now()->format('Y-m-d');

        return Cache::get($dailyKey, 0);
    }

    /**
     * Get remaining daily quota.
     */
    public function getRemainingDailyQuota(): int
    {
        return max(0, self::DEFAULT_DAILY_QUOTA - $this->getDailyQuotaUsage());
    }

    /**
     * Check if there's enough quota for an operation.
     */
    public function hasQuotaFor(string $operation, int $count = 1): bool
    {
        $cost = (self::QUOTA_COSTS[$operation] ?? 1) * $count;

        return $this->getRemainingDailyQuota() >= $cost;
    }

    /**
     * Get quota statistics.
     */
    public function getQuotaStats(): array
    {
        $used = $this->getDailyQuotaUsage();
        $limit = self::DEFAULT_DAILY_QUOTA;
        $remaining = max(0, $limit - $used);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => round(($used / $limit) * 100, 2),
            'reset_at' => now()->endOfDay()->toIso8601String(),
            'can_delete_comments' => floor($remaining / self::QUOTA_COSTS['delete_comment']),
        ];
    }

    /**
     * Get user-specific rate limit status.
     */
    public function getUserRateLimitStatus(User $user): array
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        return [
            'requests_this_minute' => $requests,
            'max_per_minute' => self::MAX_REQUESTS_PER_MINUTE,
            'is_limited' => $requests >= self::MAX_REQUESTS_PER_MINUTE,
            'remaining' => max(0, self::MAX_REQUESTS_PER_MINUTE - $requests),
        ];
    }

    /**
     * Estimate quota cost for a scan operation.
     */
    public function estimateScanCost(int $videoCount, int $avgCommentsPerVideo, float $matchRate = 0.05): array
    {
        $listVideosCost = self::QUOTA_COSTS['list_videos'];
        $listCommentsCost = self::QUOTA_COSTS['list_comments'] * $videoCount;
        $estimatedMatches = (int) ceil($videoCount * $avgCommentsPerVideo * $matchRate);
        $deleteCost = self::QUOTA_COSTS['delete_comment'] * $estimatedMatches;

        $totalCost = $listVideosCost + $listCommentsCost + $deleteCost;

        return [
            'list_videos' => $listVideosCost,
            'list_comments' => $listCommentsCost,
            'estimated_deletes' => $deleteCost,
            'total_estimated' => $totalCost,
            'can_afford' => $this->getRemainingDailyQuota() >= $totalCost,
        ];
    }
}
