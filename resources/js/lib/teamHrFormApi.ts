import type { HolidayRule } from '@/pages/Attendance/attendanceRulesTypes';
import type { TeamHrWorkScheduleTemplatePayload } from '@/pages/Attendance/teamAttendanceFromTemplate';
import type { SkippedCalendarDay } from '@/pages/Leave/leaveDaysDraftUtils';
import type { TeamLeaveDayDraft } from '@/pages/Leave/teamLeaveTypes';
import teamHr from '@/routes/team-hr';

export type TeamHrFormUnit = {
    id: number;
    code: string | null;
    name: string;
    parent_id: number | null;
};

export type TeamHrFormEmployeeHit = {
    id: number;
    employee_id: number;
    employee_number: string | null;
    full_name: string;
    active_position_title: string | null;
    avatar_url: string | null;
    /** Biometric / device identifier from {@see Employee::$attendance_id}. */
    attendance_id?: string | null;
    work_schedule_template_id?: number | null;
    work_schedule_template?: TeamHrWorkScheduleTemplatePayload | null;
};

export type TeamHrLeaveUsageOverlap = {
    date: string;
    employee_leave_id: number;
    leave_type_code: string;
    leave_type_name: string;
};

export type TeamHrLeaveUsageByTypeRow = {
    code: string;
    name: string;
    leave_units_week: number;
    leave_units_month: number;
    leave_units_year: number;
    records_week: number;
    records_month: number;
    records_year: number;
};

export type TeamHrLeaveUsageSummaryPayload = {
    reference: {
        as_of: string;
        tz: string;
        week_start: string;
        week_end: string;
        month_label: string;
        year: number;
    };
    same_leave_type_code: {
        approved_leave_units_week: number;
        approved_leave_units_month: number;
        approved_leave_units_year: number;
        approved_records_distinct_week: number;
        approved_records_distinct_month: number;
        approved_records_distinct_year: number;
    };
    approved_by_type: TeamHrLeaveUsageByTypeRow[];
    overlaps: TeamHrLeaveUsageOverlap[];
    leave_policy: {
        code: string;
        name: string;
        unit: string;
        annual_entitlement: string | null;
    } | null;
    uses_counted_leave_days_only: boolean;
};

/** Approved leave usage snapshot for HR confirm dialogs (sums {@link EmployeeLeaveDay} rows). */
export async function fetchTeamHrLeaveUsageSummary(args: {
    chartBranchId: number;
    unitId: number;
    employeeId: number;
    leaveTypeCode: string;
    draftDates?: string[];
    excludeEmployeeLeaveId?: number | null;
}): Promise<TeamHrLeaveUsageSummaryPayload> {
    const dates =
        Array.isArray(args.draftDates) && args.draftDates.length > 0
            ? [
                  ...new Set(args.draftDates.map((d) => d.trim().slice(0, 10))),
              ].sort()
            : [];

    const query: Record<string, string | number | (string | number)[]> = {
        chart_branch_id: args.chartBranchId,
        unit_id: args.unitId,
        employee_id: args.employeeId,
        leave_type_code: args.leaveTypeCode.trim(),
    };

    if (
        args.excludeEmployeeLeaveId != null &&
        args.excludeEmployeeLeaveId > 0
    ) {
        query.exclude_employee_leave_id = args.excludeEmployeeLeaveId;
    }

    if (dates.length > 0) {
        query.draft_dates = dates;
    }

    const url = teamHr.leaveUsageSummary.index.url({ query });

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const bodyUnknown: unknown = await response.json();

    if (!response.ok) {
        const payload = bodyUnknown as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        let message =
            typeof payload.message === 'string' && payload.message !== ''
                ? payload.message
                : 'Unable to load leave usage summary.';

        const firstError =
            payload.errors && Object.keys(payload.errors).length > 0
                ? Object.values(payload.errors)[0]?.[0]
                : undefined;
        if (typeof firstError === 'string' && firstError !== '') {
            message = firstError;
        }

        throw new Error(message);
    }

    const payload = bodyUnknown as { data?: TeamHrLeaveUsageSummaryPayload };

    if (!payload.data || typeof payload.data !== 'object') {
        throw new Error('Invalid leave usage summary response.');
    }

    return payload.data;
}

export type TeamHrOvertimeUsageByPolicyRow = {
    code: string;
    name: string;
    approved_hours_week: number;
    approved_hours_month: number;
    approved_hours_year: number;
    approved_records_distinct_week: number;
    approved_records_distinct_month: number;
    approved_records_distinct_year: number;
};

export type TeamHrOvertimeUsageSummaryPayload = {
    reference: {
        as_of: string;
        tz: string;
        week_start: string;
        week_end: string;
        month_label: string;
        year: number;
    };
    same_policy_code: {
        approved_hours_week: number;
        approved_hours_month: number;
        approved_hours_year: number;
        approved_records_distinct_week: number;
        approved_records_distinct_month: number;
        approved_records_distinct_year: number;
    };
    approved_by_policy: TeamHrOvertimeUsageByPolicyRow[];
    overtime_policy: {
        code: string;
        name: string;
        context: string;
        rate_multiplier: number;
        weekly_cap_hours: number | null;
        daily_cap_hours: number | null;
    } | null;
};

export async function fetchTeamHrOvertimeUsageSummary(args: {
    chartBranchId: number;
    unitId: number;
    employeeId: number;
    policyCode: string;
    excludeEmployeeOvertimeId?: number | null;
}): Promise<TeamHrOvertimeUsageSummaryPayload> {
    const query: Record<string, string | number> = {
        chart_branch_id: args.chartBranchId,
        unit_id: args.unitId,
        employee_id: args.employeeId,
        policy_code: args.policyCode.trim(),
    };

    if (
        args.excludeEmployeeOvertimeId != null &&
        args.excludeEmployeeOvertimeId > 0
    ) {
        query.exclude_employee_overtime_id = args.excludeEmployeeOvertimeId;
    }

    const url = teamHr.overtimeUsageSummary.index.url({ query });
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const bodyUnknown: unknown = await response.json();

    if (!response.ok) {
        const payload = bodyUnknown as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        let message =
            typeof payload.message === 'string' && payload.message !== ''
                ? payload.message
                : 'Unable to load overtime usage summary.';
        const firstError =
            payload.errors && Object.keys(payload.errors).length > 0
                ? Object.values(payload.errors)[0]?.[0]
                : undefined;
        if (typeof firstError === 'string' && firstError !== '') {
            message = firstError;
        }

        throw new Error(message);
    }

    const payload = bodyUnknown as { data?: TeamHrOvertimeUsageSummaryPayload };
    if (!payload.data || typeof payload.data !== 'object') {
        throw new Error('Invalid overtime usage summary response.');
    }

    return payload.data;
}

/** Server expansion: employee work-schedule weekdays + organization holidays */
export async function fetchTeamHrLeavePeriodExpansion(args: {
    chartBranchId: number;
    unitId: number;
    employeeId: number;
    dateFrom: string;
    dateTo: string;
}): Promise<{
    counted: TeamLeaveDayDraft[];
    skipped: SkippedCalendarDay[];
}> {
    const from = args.dateFrom.trim().slice(0, 10);
    const to = args.dateTo.trim().slice(0, 10);

    const url = teamHr.leavePeriod.expand.url({
        query: {
            chart_branch_id: args.chartBranchId,
            unit_id: args.unitId,
            employee_id: args.employeeId,
            date_from: from,
            date_to: to,
        },
    });

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payloadUnknown: unknown = await response.json();

    if (!response.ok) {
        const payload = payloadUnknown as {
            message?: string;
            errors?: { employee_id?: string[] };
        };
        let message =
            typeof payload.message === 'string' && payload.message !== ''
                ? payload.message
                : 'Unable to expand the leave period.';

        const firstEmp =
            payload.errors?.employee_id &&
            typeof payload.errors.employee_id[0] === 'string'
                ? payload.errors.employee_id[0]
                : undefined;
        if (firstEmp) {
            message = firstEmp;
        }

        throw new Error(message);
    }

    const payload = payloadUnknown as {
        data?: {
            counted?: TeamLeaveDayDraft[];
            skipped?: SkippedCalendarDay[];
        };
    };

    const counted = Array.isArray(payload.data?.counted)
        ? payload.data.counted
        : [];

    const skipped = Array.isArray(payload.data?.skipped)
        ? payload.data.skipped
        : [];

    return { counted, skipped };
}

export async function fetchTeamHrOrganizationHolidayRulesForRange(args: {
    dateFrom: string;
    dateTo: string;
}): Promise<HolidayRule[]> {
    const from = args.dateFrom.trim().slice(0, 10);
    const to = args.dateTo.trim().slice(0, 10);

    const url = teamHr.organizationHolidays.index.url({
        query: { date_from: from, date_to: to },
    });

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error('Unable to load organization holidays.');
    }

    const payload = (await response.json()) as { data?: HolidayRule[] };

    return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchTeamHrFormUnits(): Promise<{
    units: TeamHrFormUnit[];
    workspaceBranchId: number | null;
}> {
    const response = await fetch(teamHr.units.index.url(), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error('Unable to load units.');
    }

    const payload = (await response.json()) as {
        data?: TeamHrFormUnit[];
        meta?: { workspace_branch_id?: number | null };
    };

    return {
        units: Array.isArray(payload.data) ? payload.data : [],
        workspaceBranchId:
            payload.meta?.workspace_branch_id !== undefined
                ? payload.meta.workspace_branch_id
                : null,
    };
}

export async function fetchTeamHrFormEmployees(args: {
    chartBranchId: number;
    unitId: number;
    query: string;
    signal?: AbortSignal;
    limit?: number;
}): Promise<TeamHrFormEmployeeHit[]> {
    const q = args.query.trim();

    const url = teamHr.employees.search.url({
        query: {
            chart_branch_id: args.chartBranchId,
            unit_id: args.unitId,
            ...(q !== '' ? { q } : {}),
            limit: args.limit ?? 50,
        },
    });

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal: args.signal,
    });

    if (!response.ok) {
        throw new Error('Unable to search employees.');
    }

    const payload = (await response.json()) as {
        data?: TeamHrFormEmployeeHit[];
    };

    return Array.isArray(payload.data) ? payload.data : [];
}

const MIN_DECISION_MAKER_QUERY_LEN = 2;

/** Branch-wide active employees for decision-maker pickers; requires at least two letters in `query`. */
export async function fetchTeamHrDecisionMakerEmployees(args: {
    chartBranchId: number;
    query: string;
    signal?: AbortSignal;
    limit?: number;
}): Promise<TeamHrFormEmployeeHit[]> {
    const q = args.query.trim();
    if (q.length < MIN_DECISION_MAKER_QUERY_LEN) {
        return [];
    }

    const url = teamHr.employees.decisionMakers.search.url({
        query: {
            chart_branch_id: args.chartBranchId,
            q,
            limit: args.limit ?? 50,
        },
    });

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal: args.signal,
    });

    if (!response.ok) {
        throw new Error('Unable to search employees.');
    }

    const payload = (await response.json()) as {
        data?: TeamHrFormEmployeeHit[];
    };

    return Array.isArray(payload.data) ? payload.data : [];
}

/** Format `Date` / ISO string for `<input type="datetime-local" />` in local timezone. */
export function isoToDatetimeLocalValue(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }

    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function datetimeLocalValueToIso(local: string): string | null {
    if (local.trim() === '') {
        return null;
    }

    const parsed = new Date(local);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toISOString();
}
