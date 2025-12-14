<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_platform_id',
        'platform_comment_id',
        'video_id',
        'post_id',
        'commenter_username',
        'commenter_id',
        'comment_text',
        'matched_filter_id',
        'matched_pattern',
        'action_taken',
        'action_source',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Action taken values.
     */
    public const ACTION_DELETED = 'deleted';

    public const ACTION_HIDDEN = 'hidden';

    public const ACTION_FLAGGED = 'flagged';

    public const ACTION_REPORTED = 'reported';

    public const ACTION_FAILED = 'failed';

    /**
     * Action source values.
     */
    public const SOURCE_BACKGROUND_JOB = 'background_job';

    public const SOURCE_EXTENSION = 'extension';

    public const SOURCE_MANUAL = 'manual';

    /**
     * Get the user that owns this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user platform connection.
     */
    public function userPlatform(): BelongsTo
    {
        return $this->belongsTo(UserPlatform::class);
    }

    /**
     * Get the filter that matched.
     */
    public function matchedFilter(): BelongsTo
    {
        return $this->belongsTo(Filter::class, 'matched_filter_id');
    }

    /**
     * Check if the action was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->action_taken !== self::ACTION_FAILED;
    }

    /**
     * Scope to get only successful logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('action_taken', '!=', self::ACTION_FAILED);
    }

    /**
     * Scope to get only failed logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('action_taken', self::ACTION_FAILED);
    }

    /**
     * Scope to get logs by action source.
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('action_source', $source);
    }

    /**
     * Scope to get logs for a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get logs for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('processed_at', today());
    }

    /**
     * Get all available action taken values.
     */
    public static function getActionTakenOptions(): array
    {
        return [
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_HIDDEN => 'Hidden',
            self::ACTION_FLAGGED => 'Flagged',
            self::ACTION_REPORTED => 'Reported',
            self::ACTION_FAILED => 'Failed',
        ];
    }

    /**
     * Get all available action source values.
     */
    public static function getActionSourceOptions(): array
    {
        return [
            self::SOURCE_BACKGROUND_JOB => 'Background Job',
            self::SOURCE_EXTENSION => 'Browser Extension',
            self::SOURCE_MANUAL => 'Manual',
        ];
    }
}
