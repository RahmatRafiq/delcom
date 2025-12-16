// Role & Permission types for user management pages

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
}

export interface Permission {
    id: number;
    name: string;
}

// User type for user management pages (includes password, deleted_at, etc.)
// This is different from auth.ts User which is for the logged-in user
export interface User {
    id: number;
    name: string;
    email: string;
    password?: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    roles: string[];
    role_id?: number;
    trashed?: boolean;
}
