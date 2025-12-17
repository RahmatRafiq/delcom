<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilterGroup extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_active',
        'applies_to_platforms',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'applies_to_platforms' => 'array',
    ];

    /**
     * Get the user that owns this filter group.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the filters in this group.
     */
    public function filters(): HasMany
    {
        return $this->hasMany(Filter::class);
    }

    /**
     * Get only active filters in this group.
     */
    public function activeFilters(): HasMany
    {
        return $this->hasMany(Filter::class)
            ->where('is_active', true)
            ->orderBy('priority', 'desc');
    }

    /**
     * Check if this filter group applies to a specific platform.
     */
    public function appliesToPlatform(string $platform): bool
    {
        if (empty($this->applies_to_platforms)) {
            return true; // Applies to all platforms if not specified
        }

        return in_array($platform, $this->applies_to_platforms);
    }

    /**
     * Scope to get only active filter groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get filter groups for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where(function ($q) use ($platform) {
            $q->whereNull('applies_to_platforms')
                ->orWhereJsonContains('applies_to_platforms', $platform);
        });
    }
}
