<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'display_name',
        'tier',
        'api_base_url',
        'oauth_authorize_url',
        'oauth_token_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user platforms for this platform.
     */
    public function userPlatforms(): HasMany
    {
        return $this->hasMany(UserPlatform::class);
    }

    /**
     * Get the connection methods for this platform.
     */
    public function connectionMethods(): HasMany
    {
        return $this->hasMany(PlatformConnectionMethod::class);
    }

    /**
     * Get the plans that can access this platform.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_platforms')
            ->withPivot('allowed_method')
            ->withTimestamps();
    }

    /**
     * Get available connection methods for this platform.
     */
    public function getAvailableConnectionMethods(): \Illuminate\Support\Collection
    {
        return $this->connectionMethods()->active()->pluck('connection_method');
    }

    /**
     * Check if platform supports a specific connection method.
     */
    public function supportsMethod(string $method): bool
    {
        return $this->connectionMethods()
            ->where('connection_method', $method)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if platform supports API connection.
     */
    public function supportsApi(): bool
    {
        return $this->supportsMethod('api');
    }

    /**
     * Check if platform supports extension connection.
     */
    public function supportsExtension(): bool
    {
        return $this->supportsMethod('extension');
    }

    /**
     * Check if this platform uses API (background jobs).
     */
    public function isApiTier(): bool
    {
        return $this->tier === 'api';
    }

    /**
     * Check if this platform uses extension (DOM automation).
     */
    public function isExtensionTier(): bool
    {
        return $this->tier === 'extension';
    }

    /**
     * Scope to get only active platforms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get API-tier platforms.
     */
    public function scopeApiTier($query)
    {
        return $query->where('tier', 'api');
    }

    /**
     * Scope to get extension-tier platforms.
     */
    public function scopeExtensionTier($query)
    {
        return $query->where('tier', 'extension');
    }
}
