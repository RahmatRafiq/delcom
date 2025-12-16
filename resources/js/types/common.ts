import type { LucideIcon } from 'lucide-react';

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
