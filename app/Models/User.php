<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'subscription_tier',
        'extension_auth_token',
        'extension_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'extension_auth_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'extension_token_expires_at' => 'datetime',
    ];

    /**
     * Get the galleries owned by the user.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    /**
     * Get the connected platforms for this user.
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(UserPlatform::class);
    }

    /**
     * Get the filter groups for this user.
     */
    public function filterGroups(): HasMany
    {
        return $this->hasMany(FilterGroup::class);
    }

    /**
     * Get the moderation logs for this user.
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->latest();
    }

    /**
     * Get all user subscriptions.
     */
    public function allSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's usage records.
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * Get the user's current plan.
     */
    public function getCurrentPlan(): ?Plan
    {
        return $this->subscription?->plan ?? Plan::free();
    }

    /**
     * Check if user is on free plan.
     */
    public function isOnFreePlan(): bool
    {
        $plan = $this->getCurrentPlan();

        return ! $plan || $plan->slug === 'free';
    }

    /**
     * Get current month usage count.
     */
    public function getCurrentMonthUsage(): int
    {
        return UsageRecord::getCurrentMonthUsage($this->id);
    }

    /**
     * Get remaining actions for this month.
     * Returns -1 for unlimited.
     */
    public function getRemainingActions(): int
    {
        $plan = $this->getCurrentPlan();

        if (! $plan || $plan->hasUnlimitedActions()) {
            return -1;
        }

        $used = $this->getCurrentMonthUsage();

        return max(0, $plan->monthly_action_limit - $used);
    }

    /**
     * Check if user can perform a moderation action.
     */
    public function canPerformAction(): bool
    {
        $remaining = $this->getRemainingActions();

        return $remaining === -1 || $remaining > 0;
    }

    /**
     * Check if user can access a specific platform.
     */
    public function canAccessPlatform(int $platformId, string $method = 'api'): bool
    {
        $plan = $this->getCurrentPlan();

        return $plan?->canAccessPlatform($platformId, $method) ?? false;
    }

    /**
     * Get usage statistics for display.
     */
    public function getUsageStats(): array
    {
        $plan = $this->getCurrentPlan();
        $used = $this->getCurrentMonthUsage();
        $limit = $plan?->monthly_action_limit ?? 100;

        return [
            'used' => $used,
            'limit' => $limit === -1 ? 'unlimited' : $limit,
            'remaining' => $limit === -1 ? 'unlimited' : max(0, $limit - $used),
            'percentage' => $limit === -1 ? 0 : min(100, round(($used / max(1, $limit)) * 100)),
            'reset_date' => now()->endOfMonth()->addDay()->format('Y-m-d'),
        ];
    }

    /**
     * Generate a new extension authentication token.
     */
    public function generateExtensionToken(): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $this->update([
            'extension_auth_token' => hash('sha256', $token),
            'extension_token_expires_at' => now()->addDays(30),
        ]);

        return $token;
    }

    /**
     * Get active filters for a specific platform.
     */
    public function getActiveFilters(?string $platform = null): \Illuminate\Support\Collection
    {
        $query = Filter::whereHas('filterGroup', function ($q) use ($platform) {
            $q->where('user_id', $this->id)
                ->where('is_active', true);

            if ($platform) {
                $q->where(function ($subQ) use ($platform) {
                    $subQ->whereNull('applies_to_platforms')
                        ->orWhereJsonContains('applies_to_platforms', $platform);
                });
            }
        })->where('is_active', true)
            ->orderBy('priority', 'desc');

        return $query->get();
    }

    /**
     * Get the options for activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'password'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $user = auth()->user();
                $userName = $user ? $user->name : 'unknown';
                $userId = $user ? $user->id : 'unknown';

                return "User {$this->name} (ID: {$this->id}) was {$eventName} by {$userName} (ID: {$userId})";
            });
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')
            ->singleFile() // Only one profile image per user
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->useDisk('public');
    }

    /**
     * Register media conversions for profile images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 100, 100)
            ->nonQueued()
            ->performOnCollections('profile_image');

        $this->addMediaConversion('avatar')
            ->fit(Fit::Crop, 200, 200)
            ->nonQueued()
            ->performOnCollections('profile_image');

        $this->addMediaConversion('large')
            ->fit(Fit::Contain, 800, 800)
            ->nonQueued()
            ->performOnCollections('profile_image');
    }
}
