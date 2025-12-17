<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingModeration extends Model
{
    protected $fillable = [
        'user_id',
        'user_platform_id',
        'user_video_id',
        'platform_comment_id',
        'video_id',
        'video_title',
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

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_FAILED = 'failed';

    /**
     * Get the user that owns this pending moderation.
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
     * Get the user video (if tracked).
     */
    public function userVideo(): BelongsTo
    {
        return $this->belongsTo(UserVideo::class);
    }

    /**
     * Get the filter that matched.
     */
    public function matchedFilter(): BelongsTo
    {
        return $this->belongsTo(Filter::class, 'matched_filter_id');
    }

    /**
     * Scope to get pending items.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved items (ready for deletion).
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get items for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark as approved for deletion.
     */
    public function approve(): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark as dismissed (not spam).
     */
    public function dismiss(): bool
    {
        return $this->update([
            'status' => self::STATUS_DISMISSED,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark as successfully deleted.
     */
    public function markDeleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELETED,
            'actioned_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markFailed(string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'actioned_at' => now(),
        ]);
    }

    /**
     * Check if this item is actionable (pending or approved).
     */
    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Get all available status options.
     */
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
}
