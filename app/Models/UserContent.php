<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserContent extends Model
{
    public const TYPE_VIDEO = 'video';

    public const TYPE_POST = 'post';

    public const TYPE_REEL = 'reel';

    public const TYPE_STORY = 'story';

    public const TYPE_SHORT = 'short';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'user_platform_id',
        'content_id',
        'content_type',
        'title',
        'thumbnail_url',
        'platform_url',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userPlatform(): BelongsTo
    {
        return $this->belongsTo(UserPlatform::class);
    }

    public function pendingModerations(): HasMany
    {
        return $this->hasMany(PendingModeration::class);
    }

    public function scopeScannable($query)
    {
        return $query->where('scan_enabled', true);
    }

    public function scopeForPlatform($query, int $userPlatformId)
    {
        return $query->where('user_platform_id', $userPlatformId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('content_type', $type);
    }

    public function updateCheckpoint(string $commentId, int $scannedCount = 0, int $spamCount = 0): bool
    {
        return $this->update([
            'last_scanned_comment_id' => $commentId,
            'last_scanned_at' => now(),
            'total_comments_scanned' => $this->total_comments_scanned + $scannedCount,
            'total_spam_found' => $this->total_spam_found + $spamCount,
        ]);
    }

    public function resetCheckpoint(): bool
    {
        return $this->update([
            'last_scanned_comment_id' => null,
            'last_scanned_at' => null,
        ]);
    }

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

    public function getPlatformUrlAttribute($value): ?string
    {
        if ($value) {
            return $value;
        }

        return $this->generatePlatformUrl();
    }

    protected function generatePlatformUrl(): ?string
    {
        $platform = $this->userPlatform?->platform;
        if (! $platform) {
            return null;
        }

        return match ($platform->name) {
            'youtube' => "https://www.youtube.com/watch?v={$this->content_id}",
            'instagram' => "https://www.instagram.com/p/{$this->content_id}",
            'tiktok' => "https://www.tiktok.com/@{$this->userPlatform->platform_username}/video/{$this->content_id}",
            'facebook' => "https://www.facebook.com/{$this->content_id}",
            default => null,
        };
    }

    public static function findOrCreateFromPlatform(
        int $userId,
        int $userPlatformId,
        string $contentId,
        string $contentType = self::TYPE_VIDEO,
        ?string $title = null,
        ?string $thumbnail = null,
        ?string $platformUrl = null,
        ?string $publishedAt = null
    ): self {
        return self::firstOrCreate(
            [
                'user_platform_id' => $userPlatformId,
                'content_id' => $contentId,
            ],
            [
                'user_id' => $userId,
                'content_type' => $contentType,
                'title' => $title,
                'thumbnail_url' => $thumbnail,
                'platform_url' => $platformUrl,
                'published_at' => $publishedAt ? now()->parse($publishedAt) : null,
            ]
        );
    }
}
