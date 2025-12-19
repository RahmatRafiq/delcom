<?php

namespace App\Services\Platforms\Youtube;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class YouTubeRateLimiter
{
    public const QUOTA_COSTS = [
        'list_videos' => 1,
        'list_comments' => 1,
        'delete_comment' => 50,
        'set_moderation_status' => 50,
        'search' => 100,
    ];

    /**
     * Get daily quota limit from config (upgradeable via GCP)
     */
    public static function getDailyQuotaLimit(): int
    {
        return config('services.youtube.daily_quota', 10000);
    }

    /**
     * Get max requests per minute from config
     */
    public static function getMaxRequestsPerMinute(): int
    {
        return config('services.youtube.max_requests_per_minute', 30);
    }

    public function canPerformAction(User $user): bool
    {
        if (! $user->canPerformAction()) {
            return false;
        }

        if ($this->isRateLimited($user)) {
            return false;
        }

        return true;
    }

    public function isRateLimited(User $user): bool
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        return $requests >= self::getMaxRequestsPerMinute();
    }

    public function incrementRequestCount(User $user): void
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        Cache::put($key, $requests + 1, now()->addMinute());
    }

    public function trackQuotaUsage(string $operation, int $count = 1): int
    {
        $cost = (self::QUOTA_COSTS[$operation] ?? 1) * $count;

        $dailyKey = 'youtube_quota:daily:'.now()->format('Y-m-d');
        $currentUsage = Cache::get($dailyKey, 0);
        $newUsage = $currentUsage + $cost;

        Cache::put($dailyKey, $newUsage, now()->endOfDay());

        $percentUsed = ($newUsage / self::getDailyQuotaLimit()) * 100;
        if ($percentUsed >= 80 && $percentUsed < 81) {
            Log::warning('YouTubeRateLimiter: Quota usage at 80%', [
                'used' => $newUsage,
                'limit' => self::getDailyQuotaLimit(),
            ]);
        } elseif ($percentUsed >= 95) {
            Log::critical('YouTubeRateLimiter: Quota usage at 95%', [
                'used' => $newUsage,
                'limit' => self::getDailyQuotaLimit(),
            ]);
        }

        return $cost;
    }

    public function getDailyQuotaUsage(): int
    {
        $dailyKey = 'youtube_quota:daily:'.now()->format('Y-m-d');

        return Cache::get($dailyKey, 0);
    }

    public function getRemainingDailyQuota(): int
    {
        return max(0, self::getDailyQuotaLimit() - $this->getDailyQuotaUsage());
    }

    public function hasQuotaFor(string $operation, int $count = 1): bool
    {
        $cost = (self::QUOTA_COSTS[$operation] ?? 1) * $count;

        return $this->getRemainingDailyQuota() >= $cost;
    }

    public function getQuotaStats(): array
    {
        $used = $this->getDailyQuotaUsage();
        $limit = self::getDailyQuotaLimit();
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

    public function getUserRateLimitStatus(User $user): array
    {
        $key = "youtube_rate_limit:user:{$user->id}";
        $requests = Cache::get($key, 0);

        return [
            'requests_this_minute' => $requests,
            'max_per_minute' => self::getMaxRequestsPerMinute(),
            'is_limited' => $requests >= self::getMaxRequestsPerMinute(),
            'remaining' => max(0, self::getMaxRequestsPerMinute() - $requests),
        ];
    }

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
