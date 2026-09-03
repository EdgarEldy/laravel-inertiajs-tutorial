export interface Permission {
    id: number;
    resource: string;
    action: string;
    name: string;
    roles_count?: number;
    created_at: string;
}

export interface Role {
    id: number;
    role_name: string;
    users_count?: number;
    permissions?: Permission[];
    created_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles?: Role[];
    created_at: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Shape of a Laravel paginator serialized as a plain Inertia prop (built
 * from `LengthAwarePaginator::through()`, not a `JsonResource::collection()`
 * wrapping a paginator - see `RoleController::index()` for why).
 */
export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
