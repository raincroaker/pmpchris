import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';

export type { EmploymentStatusApi };

export type EmploymentHistoryEmployee = {
    id: number;
    display_name: string;
    id_number: string;
    avatar_url: string | null;
    is_org_wide: boolean;
};

export type EmploymentHistoryRow = {
    id: number;
    hire_date: string;
    separation_date: string | null;
    employment_status: EmploymentStatusApi;
    tenure_days: number;
    employee: EmploymentHistoryEmployee;
};

export type EmploymentHistoryPaginator = {
    data: EmploymentHistoryRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type EmploymentHistorySort =
    | 'hire_date'
    | 'separation_date'
    | 'last_name'
    | 'id_number'
    | 'employment_status'
    | 'tenure';

/** Query + UI state echoed from the server (`filters`). */
export type EmploymentHistoryFilters = {
    search: string;
    employment_status: EmploymentStatusApi | null;
    employee_id: number | null;
    hire_from: string | null;
    hire_to: string | null;
    separation_from: string | null;
    separation_to: string | null;
    sort: EmploymentHistorySort;
    direction: 'asc' | 'desc';
    per_page: number;
};
