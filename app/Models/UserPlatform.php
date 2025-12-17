<?php

namespace App\Models;

use App\Services\TokenEncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPlatform extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'platform_id',
        'connection_method',
        'platform_user_id',
        'platform_username',
        'platform_channel_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
        'auto_moderation_enabled',
        'scan_mode',
        'auto_delete_enabled',
        'scan_frequency_minutes',
        'last_scanned_at',
    ];

    /**
     * Scan mode constants.
     */
    public const SCAN_MODE_FULL = 'full';

    public const SCAN_MODE_INCREMENTAL = 'incremental';

    public const SCAN_MODE_MANUAL = 'manual';

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_scanned_at' => 'datetime',
        'scopes' => 'array',
        'is_active' => 'boolean',
        'auto_moderation_enabled' => 'boolean',
        'auto_delete_enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the user that owns this platform connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the platform.
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Get the moderation logs for this platform connection.
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(UserContent::class);
    }

    /**
     * Get pending moderations for this platform connection.
     */
    public function pendingModerations(): HasMany
    {
        return $this->hasMany(PendingModeration::class);
    }

    /**
     * Check if auto-delete is disabled (use review queue instead).
     */
    public function usesReviewQueue(): bool
    {
        return ! $this->auto_delete_enabled;
    }

    /**
     * Check if using incremental scan mode.
     */
    public function isIncrementalScan(): bool
    {
        return $this->scan_mode === self::SCAN_MODE_INCREMENTAL;
    }

    /**
     * Encrypt access token when setting.
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value
            ? app(TokenEncryptionService::class)->encrypt($value)
            : null;
    }

    /**
     * Decrypt access token when getting.
     */
    public function getAccessTokenAttribute($value): ?string
    {
        return $value
            ? app(TokenEncryptionService::class)->decrypt($value)
            : null;
    }

    /**
     * Encrypt refresh token when setting.
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value
            ? app(TokenEncryptionService::class)->encrypt($value)
            : null;
    }

    /**
     * Decrypt refresh token when getting.
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        return $value
            ? app(TokenEncryptionService::class)->decrypt($value)
            : null;
    }

    /**
     * Check if the OAuth token is expired.
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Check if this platform needs to be scanned.
     */
    public function needsScanning(): bool
    {
        // Don't auto-scan if inactive, auto moderation disabled, or manual mode
        if (! $this->is_active || ! $this->auto_moderation_enabled || $this->scan_mode === self::SCAN_MODE_MANUAL) {
            return false;
        }

        if (! $this->last_scanned_at) {
            return true;
        }

        return $this->last_scanned_at
            ->addMinutes($this->scan_frequency_minutes)
            ->isPast();
    }

    /**
     * Scope to get only active platform connections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get platform connections with auto moderation enabled.
     */
    public function scopeAutoModeration($query)
    {
        return $query->where('auto_moderation_enabled', true);
    }

    /**
     * Scope to get platform connections that need scanning.
     */
    public function scopeNeedsScanning($query)
    {
        return $query->active()
            ->autoModeration()
            ->where('scan_mode', '!=', self::SCAN_MODE_MANUAL)
            ->where(function ($q) {
                $q->whereNull('last_scanned_at')
                    ->orWhereRaw('last_scanned_at < NOW() - INTERVAL scan_frequency_minutes MINUTE');
            });
    }
}
