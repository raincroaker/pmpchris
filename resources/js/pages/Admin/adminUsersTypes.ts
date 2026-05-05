import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';

export type AdminRoleUser = {
    id: number;
    name: string;
    email: string;
    employee_number: string | null;
};

export type AdminRoleRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    users_count: number;
    users: AdminRoleUser[];
};

export type AdminRolesPaginator = {
    data: AdminRoleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type AdminAccountStatusFilter = 'all' | 'has_account' | 'no_account';
export type AdminEmploymentStateFilter = 'active' | 'inactive';

export type AdminRoleFilters = {
    view: 'users' | 'roles';
    search: string;
    sort: 'name' | 'code' | 'users_count' | 'id';
    direction: 'asc' | 'desc';
    per_page: number;
    role_id: number | null;
    org_scope: 'org_wide' | 'not_org_wide' | null;
    unit_id: number | 'unassigned' | null;
    account_status: AdminAccountStatusFilter;
    employment_state: AdminEmploymentStateFilter;
    employment_status: EmploymentStatusApi | null;
};

export type AdminUserRole = {
    id: number;
    code: string;
    name: string;
};

export type AdminUserRow = {
    id: number;
    user_id: number | null;
    has_account: boolean;
    name: string;
    email: string;
    employee_number: string | null;
    roles_count: number;
    roles: AdminUserRole[];
    employment_status: EmploymentStatusApi | null;
    scope_summary: string;
    assigned_branches: { id: number; code: string; name: string }[];
    assigned_units: { id: number; code: string; name: string }[];
};

export type AdminUsersPaginator = {
    data: AdminUserRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type AdminRoleFilterOption = {
    id: number;
    code: string;
    name: string;
};

export type AdminBranchOption = {
    id: number;
    code: string;
    name: string;
};

export type AdminUnitFilterOption = {
    id: number;
    code: string;
    name: string;
    unit_type: string;
};
