import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';

export type JobHistoryRow = {
    id: number;
    start_date: string;
    end_date: string | null;
    total_days: number;
    job_status: 'current' | 'ended';
    employment_status: EmploymentStatusApi;
    employee: {
        id: number;
        display_name: string;
        id_number: string;
        is_org_wide: boolean;
    };
    position: {
        id: number;
        code: string;
        title: string;
    } | null;
    organizational_unit: {
        id: number;
        code: string;
        name: string;
        unit_type: string | null;
    } | null;
};

export type JobHistoryPaginator = {
    data: JobHistoryRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type JobHistorySort =
    | 'start_date'
    | 'end_date'
    | 'last_name'
    | 'id_number'
    | 'employment_status'
    | 'total_days';

export type JobHistoryFilters = {
    search: string;
    history_type: 'positions' | 'unit_assignments';
    employment_status: EmploymentStatusApi | null;
    start_from: string | null;
    start_to: string | null;
    end_from: string | null;
    end_to: string | null;
    sort: JobHistorySort;
    direction: 'asc' | 'desc';
    per_page: number;
};
