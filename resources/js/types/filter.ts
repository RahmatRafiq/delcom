// =====================================================
// Filter Types
// =====================================================

export type FilterType = 'keyword' | 'phrase' | 'regex' | 'username' | 'url' | 'emoji_spam' | 'repeat_char';
export type FilterMatchType = 'exact' | 'contains' | 'starts_with' | 'ends_with' | 'regex';
export type FilterAction = 'delete' | 'hide' | 'flag' | 'report';

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
