import type {
    EmployeeIndexPosition,
    EmployeePositionFilterOption,
} from '@/pages/Employees/employeeIndexTypes';

export type EmployeeScheduleTemplateOption = {
    id: number;
    name: string;
    is_active: boolean;
};

/** Organizational unit / org-level unit row (directory units). */
export type EmployeeScheduleUnit = {
    name: string;
    unit_type: string;
    code: string | null;
    is_primary: boolean;
    /** Hex `#rrggbb` from `unit_types.color` when assigned to a unit; null for org-level rows. */
    unit_type_color: string | null;
};

export type EmployeeScheduleRow = {
    id: number;
    display_name: string;
    id_number: string;
    attendance_id: string | null;
    avatar_url: string | null;
    units: EmployeeScheduleUnit[];
    work_schedule: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
    is_org_wide: boolean;
    positions: EmployeeIndexPosition[];
};

export type EmployeeSchedulesPaginator = {
    data: EmployeeScheduleRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type EmployeeScheduleFilters = {
    search: string;
    sort: 'last_name' | 'first_name' | 'id_number' | 'id';
    direction: 'asc' | 'desc';
    per_page: number;
    position_id: number | null;
    org_scope: 'org_wide' | 'branch_scoped' | null;
    attendance_id_filter: 'has' | 'missing' | null;
    work_schedule_filter: 'assigned' | 'unassigned' | null;
    /** `organization`, a unit id string, or `null` when not filtering units. */
    unit_filter: string | null;
};

export type EmployeeScheduleUnitFilterOption = {
    value: string;
    /** Primary line: unit or org name, or sentinel label (“All units”, “Organization”). */
    label: string;
    /** Secondary line in the dropdown (organizational unit code); omitted for sentinel rows. */
    code?: string | null;
};

export type EmployeeSchedulePositionFilterOption = EmployeePositionFilterOption;
