<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Platform;
use App\Models\PlatformConnectionMethod;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    /**
     * Create a free subscription for a new user.
     */
    public function createFreeSubscription(User $user): Subscription
    {
        $freePlan = Plan::where('slug', 'free')->firstOrFail();

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    /**
     * Check if user can access a specific platform with a specific method.
     */
    public function canUserAccessPlatform(User $user, Platform $platform, string $method): bool
    {
        // Check if plan allows this platform with this method
        if (! $user->canAccessPlatform($platform->id, $method)) {
            return false;
        }

        // Check platform connection method availability
        return PlatformConnectionMethod::where('platform_id', $platform->id)
            ->where('connection_method', $method)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get connection method info for a platform.
     */
    public function getConnectionMethodInfo(Platform $platform, string $method): ?PlatformConnectionMethod
    {
        return PlatformConnectionMethod::where('platform_id', $platform->id)
            ->where('connection_method', $method)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get usage statistics for a user.
     */
    public function getUsageStats(User $user): array
    {
        return $user->getUsageStats();
    }

    /**
     * Upgrade user to a new plan.
     */
    public function upgradePlan(User $user, Plan $newPlan, string $billingCycle = 'monthly'): Subscription
    {
        // Cancel existing subscription if any
        if ($user->subscription) {
            $user->subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'ends_at' => $user->subscription->current_period_end,
            ]);
        }

        // Create new subscription
        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'current_period_start' => now(),
            'current_period_end' => $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
        ]);
    }

    /**
     * Cancel user subscription (at end of period).
     */
    public function cancelSubscription(User $user): bool
    {
        if (! $user->subscription) {
            return false;
        }

        $user->subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => $user->subscription->current_period_end,
        ]);

        return true;
    }

    /**
     * Get all active plans for pricing display.
     */
    public function getActivePlans(): \Illuminate\Database\Eloquent\Collection
    {
        return Plan::active()->get();
    }
}
