<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
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
