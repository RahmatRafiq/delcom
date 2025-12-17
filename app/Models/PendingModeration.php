<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingModeration extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_FAILED = 'failed';

    public const TYPE_VIDEO = 'video';
    public const TYPE_POST = 'post';
    public const TYPE_REEL = 'reel';
    public const TYPE_STORY = 'story';
    public const TYPE_SHORT = 'short';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'user_platform_id',
        'user_content_id',
        'platform_comment_id',
        'content_id',
        'content_type',
        'content_title',
        'commenter_username',
        'commenter_id',
        'commenter_profile_url',
        'comment_text',
        'matched_filter_id',
        'matched_pattern',
        'confidence_score',
        'status',
        'failure_reason',
        'detected_at',
        'reviewed_at',
        'actioned_at',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'detected_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userPlatform(): BelongsTo
    {
        return $this->belongsTo(UserPlatform::class);
    }

    public function userContent(): BelongsTo
    {
        return $this->belongsTo(UserContent::class);
    }

    public function matchedFilter(): BelongsTo
    {
        return $this->belongsTo(Filter::class, 'matched_filter_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfContentType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function approve(): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function dismiss(): bool
    {
        return $this->update([
            'status' => self::STATUS_DISMISSED,
            'reviewed_at' => now(),
        ]);
    }

    public function markDeleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELETED,
            'actioned_at' => now(),
        ]);
    }

    public function markFailed(string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'actioned_at' => now(),
        ]);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DISMISSED => 'Dismissed',
            self::STATUS_DELETED => 'Deleted',
            self::STATUS_FAILED => 'Failed',
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
