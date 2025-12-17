<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserVideo extends Model
{
    protected $fillable = [
        'user_id',
        'user_platform_id',
        'video_id',
        'title',
        'thumbnail_url',
        'published_at',
        'scan_enabled',
        'last_scanned_comment_id',
        'last_scanned_at',
        'total_comments_scanned',
        'total_spam_found',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'scan_enabled' => 'boolean',
        'total_comments_scanned' => 'integer',
        'total_spam_found' => 'integer',
    ];

    /**
     * Get the user that owns this video tracking.
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
     * Get pending moderations for this video.
     */
    public function pendingModerations(): HasMany
    {
        return $this->hasMany(PendingModeration::class);
    }

    /**
     * Scope to get scannable videos.
     */
    public function scopeScannable($query)
    {
        return $query->where('scan_enabled', true);
    }

    /**
     * Scope to get videos for a user platform.
     */
    public function scopeForPlatform($query, int $userPlatformId)
    {
        return $query->where('user_platform_id', $userPlatformId);
    }

    /**
     * Update the scan checkpoint.
     */
    public function updateCheckpoint(string $commentId, int $scannedCount = 0, int $spamCount = 0): bool
    {
        return $this->update([
            'last_scanned_comment_id' => $commentId,
            'last_scanned_at' => now(),
            'total_comments_scanned' => $this->total_comments_scanned + $scannedCount,
            'total_spam_found' => $this->total_spam_found + $spamCount,
        ]);
    }

    /**
     * Reset checkpoint (for full rescan).
     */
    public function resetCheckpoint(): bool
    {
        return $this->update([
            'last_scanned_comment_id' => null,
            'last_scanned_at' => null,
        ]);
    }

    /**
     * Check if this video needs scanning.
     */
    public function needsScanning(int $frequencyMinutes = 60): bool
    {
        if (! $this->scan_enabled) {
            return false;
        }

        if (! $this->last_scanned_at) {
            return true;
        }

        return $this->last_scanned_at->addMinutes($frequencyMinutes)->isPast();
    }

    /**
     * Get YouTube video URL.
     */
    public function getYouTubeUrlAttribute(): string
    {
        return "https://www.youtube.com/watch?v={$this->video_id}";
    }

    /**
     * Get or create a UserVideo record.
     */
    public static function findOrCreateFromYouTube(
        int $userId,
        int $userPlatformId,
        string $videoId,
        ?string $title = null,
        ?string $thumbnail = null,
        ?string $publishedAt = null
    ): self {
        return self::firstOrCreate(
            [
                'user_platform_id' => $userPlatformId,
                'video_id' => $videoId,
            ],
            [
                'user_id' => $userId,
                'title' => $title,
                'thumbnail_url' => $thumbnail,
                'published_at' => $publishedAt ? now()->parse($publishedAt) : null,
            ]
        );
    }
}
