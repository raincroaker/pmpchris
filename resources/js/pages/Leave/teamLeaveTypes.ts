/** Mock types for Employee Leaves (team HR view); UI-only until API exists. */

import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';

export type TeamLeaveRequestStatus = 'approved' | 'rejected';

export type TeamEmployeeMini = {
    display_name: string;
    id_number: string;
    avatar_url: string | null;
};

export type TeamLeaveRow = {
    id: number;
    employee: TeamEmployeeMini;
    /** `employees.id` when the row was saved from directory search. */
    employee_record_id?: number | null;
    /** Matches `value` in team directory unit filter options (not `all`). */
    unit_filter_value: string;
    /** When set, preferred for toolbar filtering (real branch units). */
    organizational_unit_id?: number | null;
    unit_name: string;
    unit_code: string | null;
    /** Tooltip label matching employee-schedule placements (directory unit type). */
    unit_type?: string;
    /** Hex `#rrggbb`; border accent on placement-style badge when set. */
    unit_type_color?: string | null;
    /** True when this row’s placement is the employee’s primary placement. */
    unit_is_primary?: boolean;
    positions?: EmployeeIndexPosition[];
    leave_type_code: string;
    leave_type_name: string;
    start_date: string;
    end_date: string;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
    /** Stored leave units when returned from the API (mocks may omit). */
    leave_days?: TeamLeaveDayDraft[];
    duration_label: string;
    status: TeamLeaveRequestStatus;
    /** HR submission date, calendar `YYYY-MM-DD` (legacy mock values may include a time). */
    submitted_at: string;
    /** Approval / decision date, calendar `YYYY-MM-DD` or null. */
    decided_at: string | null;
    /** `employees.id` of the approver when chosen from search. */
    approver_employee_id: number | null;
    approver_name: string | null;
    /** Employer-facing ID (`id_number`), when known. */
    approver_id_number: string | null;
    reason: string | null;
};

/** One calendar day consumed by a leave record (draft UI; API wiring pending). */
export type TeamLeaveDayDraft = {
    date: string;
    is_half_day: boolean;
};

export type TeamLeaveDraft = Omit<
    TeamLeaveRow,
    | 'id'
    | 'employee'
    | 'decided_at'
    | 'approver_name'
    | 'approver_employee_id'
    | 'approver_id_number'
> & {
    id?: number;
    employee_name: string;
    employee_id_number: string;
    /** Primary key when chosen from directory search */
    employee_id: number | null;
    organizational_unit_id: number | null;
    /** Form field; persisted as row `decided_at`. */
    decided_at: string;
    approver_name: string;
    approver_employee_id: number | null;
    /** Employee ID number string for combobox subtitle when re-opening the form. */
    approver_id_number: string;
    /** Explicit leave days; totals and legacy min/max span derive from this list. */
    leave_days: TeamLeaveDayDraft[];
};
