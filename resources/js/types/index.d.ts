import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    children?: NavItem[];
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
    [key: string]: unknown; // This allows for additional properties...
}

export interface AppSetting {
    id: number;
    app_name: string;
    app_description?: string;
    app_logo?: string;
    app_favicon?: string;
    seo_title?: string;
    seo_description?: string;
    seo_keywords?: string;
    seo_og_image?: string;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    theme_mode: string;
    contact_email?: string;
    contact_phone?: string;
    contact_address?: string;
    social_links?: {
        facebook?: string;
        twitter?: string;
        instagram?: string;
        linkedin?: string;
        youtube?: string;
    };
    maintenance_mode: boolean;
    maintenance_message?: string;
}

// =====================================================
// DelCom Types - Comment Moderation System
// =====================================================

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
}

export interface FilterGroup {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    applies_to_platforms: string[] | null;
    created_at: string;
    updated_at: string;
    filters?: Filter[];
    filters_count?: number;
}

export type FilterType = 'keyword' | 'phrase' | 'regex' | 'username' | 'url' | 'emoji_spam' | 'repeat_char';
export type FilterMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';
export type FilterAction = 'delete' | 'hide' | 'flag' | 'report';

export interface Filter {
    id: number;
    filter_group_id: number;
    type: FilterType;
    pattern: string;
    match_type: FilterMatchType;
    case_sensitive: boolean;
    action: FilterAction;
    priority: number;
    hit_count: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    filter_group?: FilterGroup;
}

export interface PresetFilter {
    id: number;
    name: string;
    description: string | null;
    category: string;
    filters_data: PresetFilterData[];
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface PresetFilterData {
    type: FilterType;
    pattern: string;
    match_type: FilterMatchType;
    action?: FilterAction;
}

export type ModerationActionTaken = 'deleted' | 'hidden' | 'flagged' | 'reported' | 'failed';
export type ModerationActionSource = 'background_job' | 'extension' | 'manual';

export interface ModerationLog {
    id: number;
    user_id: number;
    user_platform_id: number;
    platform_comment_id: string;
    video_id: string | null;
    post_id: string | null;
    commenter_username: string | null;
    commenter_id: string | null;
    comment_text: string | null;
    matched_filter_id: number | null;
    matched_pattern: string | null;
    action_taken: ModerationActionTaken;
    action_source: ModerationActionSource;
    failure_reason: string | null;
    processed_at: string;
    created_at: string;
    updated_at: string;
    user_platform?: UserPlatform;
    matched_filter?: Filter;
}

export interface UserPlatform {
    id: number;
    user_id: number;
    platform_id: number;
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
