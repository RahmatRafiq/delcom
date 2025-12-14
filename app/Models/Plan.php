<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'stripe_monthly_price_id',
        'stripe_yearly_price_id',
        'monthly_action_limit',
        'max_platforms',
        'scan_frequency_minutes',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'plan_platforms')
            ->withPivot('allowed_method')
            ->withTimestamps();
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function hasUnlimitedActions(): bool
    {
        return $this->monthly_action_limit === -1;
    }

    public function hasUnlimitedPlatforms(): bool
    {
        return $this->max_platforms === -1;
    }

    public function canAccessPlatform(int $platformId, ?string $method = null): bool
    {
        $pivot = $this->platforms()
            ->where('platform_id', $platformId)
            ->first()?->pivot;

        if (! $pivot) {
            return false;
        }

        if ($pivot->allowed_method === 'any' || ! $method) {
            return true;
        }

        return $pivot->allowed_method === $method;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function free(): ?self
    {
        return static::where('slug', 'free')->first();
    }
}
