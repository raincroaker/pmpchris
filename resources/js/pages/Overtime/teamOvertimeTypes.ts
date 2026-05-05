/** Mock types for Employee Overtime (team HR view); UI-only until API exists. */

import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';
import type { OvertimePolicyContext } from '@/pages/Overtime/overtimePolicyTypes';

export type TeamOvertimeRequestStatus = 'approved' | 'rejected';

export type TeamEmployeeMini = {
    display_name: string;
    id_number: string;
    avatar_url: string | null;
};

export type TeamOvertimeRow = {
    id: number;
    employee: TeamEmployeeMini;
    employee_record_id?: number | null;
    unit_filter_value: string;
    organizational_unit_id?: number | null;
    unit_name: string;
    unit_code: string | null;
    /** Tooltip label matching employee-schedule placements (directory unit type). */
    unit_type?: string;
    unit_type_color?: string | null;
    unit_is_primary?: boolean;
    positions?: EmployeeIndexPosition[];
    ot_date: string;
    hours: number;
    context: OvertimePolicyContext;
    policy_code: string;
    rate_multiplier: number;
    status: TeamOvertimeRequestStatus;
    /** HR submission date, calendar `YYYY-MM-DD`. */
    submitted_at: string;
    /** Approval / decision date, calendar `YYYY-MM-DD` or null. */
    decided_at: string | null;
    approver_employee_id: number | null;
    approver_name: string | null;
    approver_id_number: string | null;
    reason: string | null;
};

export type TeamOvertimeDraft = Omit<
    TeamOvertimeRow,
    | 'id'
    | 'employee'
    | 'decided_at'
    | 'approver_name'
    | 'approver_employee_id'
    | 'approver_id_number'
    | 'rate_multiplier'
> & {
    id?: number;
    employee_name: string;
    employee_id_number: string;
    employee_id: number | null;
    organizational_unit_id: number | null;
    /** Form field; persisted as row `decided_at`. */
    decided_at: string;
    approver_name: string;
    approver_employee_id: number | null;
    approver_id_number: string;
};
