// Forward declaration for circular dependency
import type { Platform } from './platform';

// =====================================================
// Subscription & Plans
// =====================================================

export type SubscriptionStatus = 'active' | 'canceled' | 'past_due' | 'trialing' | 'paused' | 'expired';
export type BillingCycle = 'monthly' | 'yearly' | 'free';

export interface Plan {
    id: number;
    slug: 'free' | 'basic' | 'pro' | 'enterprise';
    name: string;
    description: string | null;
    price_monthly: number;
    price_yearly: number;
    monthly_action_limit: number; // -1 = unlimited
    daily_action_limit: number; // -1 = unlimited
    max_platforms: number;
    scan_frequency_minutes: number;
    features: string[] | null;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
    platforms?: Platform[];
}

export interface Subscription {
    id: number;
    user_id: number;
    plan_id: number;
    stripe_subscription_id: string | null;
    stripe_customer_id: string | null;
    billing_cycle: BillingCycle;
    status: SubscriptionStatus;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    canceled_at: string | null;
    ends_at: string | null;
    created_at: string;
    updated_at: string;
    plan?: Plan;
}

export interface UsageStats {
    // Monthly stats
    used: number;
    limit: number | 'unlimited';
    remaining: number | 'unlimited';
    percentage: number;
    reset_date: string;
    // Daily stats
    daily_used: number;
    daily_limit: number | 'unlimited';
    daily_remaining: number | 'unlimited';
    daily_percentage: number;
}

export interface UsageRecord {
    id: number;
    user_id: number;
    year: number;
    month: number;
    actions_count: number;
    last_action_at: string | null;
    created_at: string;
    updated_at: string;
}
