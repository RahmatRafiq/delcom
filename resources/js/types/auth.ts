import type { Config } from 'ziggy-js';

import type { Plan, Subscription, UsageStats } from './subscription';

// =====================================================
// Auth & User Types
// =====================================================

export interface Auth {
    user: User;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    profile_image?: {
        file_name: string;
        size: number;
        original_url: string;
    };
    roles: string[];
    permissions: string[];
    is_admin: boolean;
    // Subscription fields
    subscription?: Subscription;
    current_plan?: Plan;
    usage_stats?: UsageStats;
    [key: string]: unknown;
}

export interface SharedData {
    name: string;
    env: string;
    isLocalEnv: boolean;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    [key: string]: unknown;
}
