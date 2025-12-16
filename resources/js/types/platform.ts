import type { Plan } from './subscription';

// =====================================================
// Platform & Connection Types
// =====================================================

export type ConnectionMethod = 'api' | 'extension';
export type AllowedMethod = 'api' | 'extension' | 'any';

export interface Platform {
    id: number;
    name: string;
    display_name: string;
    tier: 'api' | 'extension';
    api_base_url: string | null;
    oauth_authorize_url: string | null;
    oauth_token_url: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    connection_methods?: PlatformConnectionMethod[];
    plans?: Plan[];
}

export interface PlatformConnectionMethod {
    id: number;
    platform_id: number;
    connection_method: ConnectionMethod;
    requires_business_account: boolean;
    requires_paid_api: boolean;
    notes: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    platform?: Platform;
}

export interface PlanPlatform {
    plan_id: number;
    platform_id: number;
    allowed_method: AllowedMethod;
    created_at: string;
    updated_at: string;
}

export interface UserPlatform {
    id: number;
    user_id: number;
    platform_id: number;
    connection_method: ConnectionMethod;
    platform_user_id: string | null;
    platform_username: string | null;
    platform_channel_id: string | null;
    is_active: boolean;
    auto_moderation_enabled: boolean;
    scan_frequency_minutes: number;
    last_scanned_at: string | null;
    created_at: string;
    updated_at: string;
    platform?: Platform;
}
