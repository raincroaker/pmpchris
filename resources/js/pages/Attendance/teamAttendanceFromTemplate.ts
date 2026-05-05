import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';
import type { TeamAttendanceSegment } from '@/pages/Attendance/teamAttendanceTypes';

export type ScheduleDayOfWeek =
    | 'mon'
    | 'tue'
    | 'wed'
    | 'thu'
    | 'fri'
    | 'sat'
    | 'sun';

/**
 * Subset of {@link WorkScheduleTemplate::toShiftRuleArray()} used when selecting an employee
 * for Team Attendance entry (scheduled windows only).
 */
export type TeamHrWorkScheduleTemplatePayload = {
    id: number;
    name: string;
    /** Scheduled working weekdays from {@link WorkScheduleTemplate::$days}; may be omitted when not loaded */
    days?: ScheduleDayOfWeek[];
    clock_pattern: ClockPattern;
    segments: Array<{
        label: string;
        time_in: string;
        time_out: string;
        is_overnight: boolean;
    }> | null;
    time_in: string;
    time_out: string;
    is_overnight: boolean;
};

/** Normalize template `H:mm` / `HH:mm` to five-character `HH:mm` for grid validation. */
export function normalizeWorkScheduleHm(raw: string): string {
    const t = raw.trim();
    const m = /^(\d{1,2}):(\d{2})$/.exec(t);
    if (!m) {
        return t;
    }

    const h = Number.parseInt(m[1] ?? '', 10);
    const mm = m[2] ?? '';
    if (Number.isNaN(h) || h < 0 || h > 23) {
        return t;
    }

    return `${String(h).padStart(2, '0')}:${mm}`;
}

/**
 * Build attendance segments with scheduled times from the assigned work schedule template.
 * Actual punches start empty.
 */
export function segmentsFromWorkScheduleTemplatePayload(
    rule: TeamHrWorkScheduleTemplatePayload,
): TeamAttendanceSegment[] {
    if (rule.clock_pattern === 'single_pair') {
        return [
            {
                label: 'Shift',
                scheduled_in: normalizeWorkScheduleHm(rule.time_in),
                scheduled_out: normalizeWorkScheduleHm(rule.time_out),
                actual_in: null,
                actual_out: null,
            },
        ];
    }

    const raw = rule.segments;
    if (!Array.isArray(raw) || raw.length === 0) {
        return [];
    }

    return raw.map((s, i) => ({
        label: s.label.trim() !== '' ? s.label.trim() : `Session ${i + 1}`,
        scheduled_in: normalizeWorkScheduleHm(s.time_in),
        scheduled_out: normalizeWorkScheduleHm(s.time_out),
        actual_in: null,
        actual_out: null,
    }));
}
