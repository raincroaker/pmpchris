import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';
import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';

export type TeamAttendanceEmployeeMini = {
    display_name: string;
    id_number: string;
    avatar_url: string | null;
};

export type TeamAttendanceRecordStatus = 'complete' | 'ongoing' | 'incomplete';

/** First segment clock-in vs scheduled start (+ grace minutes), mock rule until tied to schedules API. */
export type TeamAttendancePunctuality = 'on_time' | 'late' | 'not_applicable';

export type TeamAttendanceSource = 'device' | 'manual' | 'import';

export type TeamAttendanceSegment = {
    label: string;
    scheduled_in: string;
    scheduled_out: string;
    actual_in: string | null;
    actual_out: string | null;
};

export type TeamAttendanceRow = {
    id: number;
    employee: TeamAttendanceEmployeeMini;
    employee_record_id?: number | null;
    organizational_unit_id?: number | null;
    unit_filter_value: string;
    unit_name: string;
    unit_code: string | null;
    /** Viewer chip border color (parity with placements; `#rrggbb`). */
    placement_unit_type_color?: string | null;
    placement_is_primary?: boolean;
    /** Tooltip subtitle (unit category label). */
    placement_unit_type?: string | null;
    /** Mock job titles for detail view (parity with employee schedule). */
    positions?: EmployeeIndexPosition[];
    work_date: string;
    /** Employee profile ID (same field as Employee Schedules). */
    attendance_id: string | null;
    /** Per-day device/import row key when present; distinct from {@link attendance_id}. */
    ingest_key: string | null;
    clock_pattern: ClockPattern;
    /** Night-spanning schedule tag for mock filtering (API may model differently later). */
    is_overnight_schedule: boolean;
    work_schedule_name: string;
    /** Template snapshot id for this row (create/edit payloads). */
    work_schedule_template_id: number;
    /** Snapshot from work schedule template: unpaid minutes inside a single session (simple session). */
    unpaid_break_minutes?: number;
    segments: TeamAttendanceSegment[];
    net_hours: number;
    variance_label: string;
    punctuality: TeamAttendancePunctuality;
    status: TeamAttendanceRecordStatus;
    /** First creation channel (device ingest, HR form, import). */
    original_source: TeamAttendanceSource;
    /** Channel of the last persist (e.g. manual after HR corrects a device row). */
    last_modified_source: TeamAttendanceSource;
    created_at: string;
    created_by: string | null;
    updated_at: string;
    updated_by: string | null;
};

export type TeamAttendanceDraft = {
    employee_name: string;
    employee_id_number: string;
    employee_id: number | null;
    organizational_unit_id: number | null;
    unit_filter_value: string;
    unit_name: string;
    unit_code: string | null;
    work_date: string;
    attendance_id: string;
    /** When set, scheduled fields mirror the employee's assigned template (read-only in the form). */
    work_schedule_template_id: number | null;
    clock_pattern: ClockPattern;
    is_overnight_schedule: boolean;
    work_schedule_name: string;
    segments: TeamAttendanceSegment[];
    status: TeamAttendanceRecordStatus;
};

export type TeamAttendanceRecordingStyleFilter =
    | 'all'
    | 'simple'
    | 'split'
    | 'overnight';

export type TeamAttendanceStatusFilter = 'all' | TeamAttendanceRecordStatus;

export type TeamAttendancePunctualityFilter = 'all' | TeamAttendancePunctuality;
