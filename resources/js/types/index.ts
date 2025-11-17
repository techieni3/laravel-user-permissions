export interface Role {
    id: number;
    name: string;
    display_name: string | null;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
    users_count?: number;
}

export interface Permission {
    id: number;
    name: string;
    display_name: string | null;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    direct_permissions?: Permission[];
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}
