import type { CalendarRecurrenceEnds } from '@/components/calendar/calendar-events';
import type {
    WorkScheduleAttendanceRulesDraft,
    WorkScheduleOvertimeRulesDraft,
} from '@/pages/Attendance/workScheduleFormRulesTypes';

export const dayOfWeekOptions = [
    { key: 'mon', label: 'Mon' },
    { key: 'tue', label: 'Tue' },
    { key: 'wed', label: 'Wed' },
    { key: 'thu', label: 'Thu' },
    { key: 'fri', label: 'Fri' },
    { key: 'sat', label: 'Sat' },
    { key: 'sun', label: 'Sun' },
] as const;

export type DayOfWeekKey = (typeof dayOfWeekOptions)[number]['key'];

/** Single continuous window vs same-day sessions with derived between-session gaps (unpaid break). */
export type ClockPattern = 'single_pair' | 'split_sessions';

export type ShiftSegment = {
    label: string;
    time_in: string;
    time_out: string;
    /** Split sessions: same calendar day only — keep false (validators enforce). */
    is_overnight: boolean;
};

/**
 * Same-day sessions (two or more). Gaps between consecutive sessions must sum to `unpaid_break_minutes`.
 * Optional overtime may be modeled as an additional contiguous session row (often the last session) or separately on the Overtime tab.
 */
export type SplitSegments = readonly ShiftSegment[];

export type ShiftRule = {
    id: number;
    name: string;
    days: DayOfWeekKey[];
    clock_pattern: ClockPattern;
    /** When `split_sessions`, two or more segments. Between-session gap minutes (summed) mirror `unpaid_break_minutes`. */
    segments: SplitSegments | null;
    time_in: string;
    time_out: string;
    is_overnight: boolean;
    is_active: boolean;
    /** Single pair: explicit break inside span. Split sessions: sum of derived gaps between consecutive sessions. */
    unpaid_break_minutes: number;
    /**
     * Late arrival grace (minutes after scheduled first start of day still on-time).
     * Single Session: scheduled time in. Split Sessions: Session 1 time in only (not Session 2).
     */
    grace_late_arrival_minutes: number;
    /** Optional notes for HR context (policy, location, etc.). */
    notes?: string | null;
    /** Persisted attendance rule panel state (JSON on `work_schedule_templates`). */
    attendance_rules?: WorkScheduleAttendanceRulesDraft | null;
    /** Persisted overtime rule panel state (JSON on `work_schedule_templates`). */
    overtime_rules?: WorkScheduleOvertimeRulesDraft | null;
};

/** Default numeric fields for new work schedule drafts (all minutes, non-negative integers). */
export const shiftRuleNumericDefaults = {
    unpaid_break_minutes: 0,
    grace_late_arrival_minutes: 0,
} as const;

/** Default Session 1 / Session 2 rows for split-session templates (UI-only until API persistence). */
export function defaultSplitSegments(): [ShiftSegment, ShiftSegment] {
    return [
        {
            label: 'Session 1',
            time_in: '08:30',
            time_out: '12:00',
            is_overnight: false,
        },
        {
            label: 'Session 2',
            time_in: '13:00',
            time_out: '17:00',
            is_overnight: false,
        },
    ];
}

/**
 * Fallback segment when adding a third same-day session (Team Attendance) or overtime block row.
 */
export function defaultThirdSessionSegment(): ShiftSegment {
    return {
        label: 'Session 3',
        time_in: '18:00',
        time_out: '20:00',
        is_overnight: false,
    };
}

export const holidayPayPolicyOptions = [
    'Double Pay',
    'No Premium',
    'Custom Multiplier',
] as const;

export type HolidayPayPolicy = (typeof holidayPayPolicyOptions)[number];

/**
 * Holidays currently support yearly repetition only (aligned with company calendar recurrence shape).
 */
export type HolidayRuleRecurrence = {
    frequency: 'yearly';
    interval: number;
    ends: CalendarRecurrenceEnds;
};

export type HolidayRule = {
    id: number;
    /** ISO date YYYY-MM-DD (first day of the holiday). */
    start_date: string;
    /** ISO date YYYY-MM-DD (last day inclusive; same as `start_date` for a single day). */
    end_date: string;
    name: string;
    /** References `HolidayTypeDefinition.id` from holiday type seed/settings. */
    type_id: string;
    /** When set, occurrences repeat on the same month/day span each year (see holiday-rule-expansion). */
    recurrence?: HolidayRuleRecurrence | null;
    /** Optional memo (policy context, proclamation ref, etc.). */
    notes?: string | null;
    /** User label shown in holiday sheet details. */
    setBy?: string | null;
    /** User label shown in holiday sheet details. */
    lastEditedBy?: string | null;
};
