<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'month',
        'actions_count',
        'last_action_at',
    ];

    protected $casts = [
        'last_action_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function incrementForUser(int $userId): self
    {
        $now = now();
        $record = self::firstOrCreate(
            [
                'user_id' => $userId,
                'year' => $now->year,
                'month' => $now->month,
            ],
            ['actions_count' => 0]
        );

        $record->increment('actions_count');
        $record->update(['last_action_at' => $now]);

        return $record;
    }

    public static function getCurrentMonthUsage(int $userId): int
    {
        $now = now();

        return self::where('user_id', $userId)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->value('actions_count') ?? 0;
    }

    public static function getMonthlyUsage(int $userId, int $year, int $month): int
    {
        return self::where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->value('actions_count') ?? 0;
    }

    /**
     * Get today's usage count from ModerationLog (successful actions only).
     */
    public static function getTodayUsage(int $userId): int
    {
        return ModerationLog::where('user_id', $userId)
            ->whereDate('processed_at', today())
            ->whereIn('action_taken', ['deleted', 'hidden', 'reported'])
            ->count();
    }

    /**
     * Record a moderation action for a user.
     * Alias for incrementForUser with optional action type logging.
     */
    public static function recordAction(int $userId, string $actionType = 'moderation'): self
    {
        return self::incrementForUser($userId);
    }
}
