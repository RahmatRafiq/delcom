<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    public const ACTION_DELETED = 'deleted';
    public const ACTION_HIDDEN = 'hidden';
    public const ACTION_FLAGGED = 'flagged';
    public const ACTION_REPORTED = 'reported';
    public const ACTION_FAILED = 'failed';

    public const SOURCE_BACKGROUND_JOB = 'background_job';
    public const SOURCE_EXTENSION = 'extension';
    public const SOURCE_MANUAL = 'manual';

    public const TYPE_VIDEO = 'video';
    public const TYPE_POST = 'post';
    public const TYPE_REEL = 'reel';
    public const TYPE_STORY = 'story';
    public const TYPE_SHORT = 'short';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'user_platform_id',
        'platform_comment_id',
        'content_id',
        'content_type',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userPlatform(): BelongsTo
    {
        return $this->belongsTo(UserPlatform::class);
    }

    public function matchedFilter(): BelongsTo
    {
        return $this->belongsTo(Filter::class, 'matched_filter_id');
    }

    public function isSuccessful(): bool
    {
        return $this->action_taken !== self::ACTION_FAILED;
    }

    public function scopeSuccessful($query)
    {
        return $query->where('action_taken', '!=', self::ACTION_FAILED);
    }

    public function scopeFailed($query)
    {
        return $query->where('action_taken', self::ACTION_FAILED);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('action_source', $source);
    }

    public function scopeOfContentType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('processed_at', today());
    }

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

    public static function getActionSourceOptions(): array
    {
        return [
            self::SOURCE_BACKGROUND_JOB => 'Background Job',
            self::SOURCE_EXTENSION => 'Browser Extension',
            self::SOURCE_MANUAL => 'Manual',
        ];
    }

    public static function getContentTypeOptions(): array
    {
        return [
            self::TYPE_VIDEO => 'Video',
            self::TYPE_POST => 'Post',
            self::TYPE_REEL => 'Reel',
            self::TYPE_STORY => 'Story',
            self::TYPE_SHORT => 'Short',
            self::TYPE_OTHER => 'Other',
        ];
    }
}
