import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';
import type {
    TeamAttendancePunctuality,
    TeamAttendanceRecordStatus,
    TeamAttendanceRecordingStyleFilter,
    TeamAttendanceRow,
    TeamAttendanceSegment,
    TeamAttendanceSource,
} from '@/pages/Attendance/teamAttendanceTypes';
import { workScheduleSplitSessionsBadgeClass } from '@/pages/Attendance/workScheduleUi';

/** Single-session net overlap may subtract template unpaid break minutes after overlap is summed. */
export type AttendanceNetOverlapOptions = {
    clockPattern?: ClockPattern;
    unpaidBreakMinutesFromTemplate?: number;
};

/** Shown when an actual punch is missing (matches clock-style ambiguity). */
export const ATTENDANCE_MISSING_PUNCH_DISPLAY = '--:--';

const HM_REGEX = /^(\d{1,2}):(\d{2})$/;

/** Parse HH:mm → minutes from midnight (0–1439 typical). Invalid → null. */
export function attendanceParseHmToMinutes(hm: string): number | null {
    const raw = hm.trim();
    const m = HM_REGEX.exec(raw);
    if (!m) {
        return null;
    }

    const h = Number(m[1]);
    const mins = Number(m[2]);
    if (
        Number.isNaN(h) ||
        Number.isNaN(mins) ||
        h < 0 ||
        h > 23 ||
        mins < 0 ||
        mins > 59
    ) {
        return null;
    }

    return h * 60 + mins;
}

/**
 * Display-only: `HH:mm` (24h) → `h:mm AM|PM`.
 * Leaves {@link ATTENDANCE_MISSING_PUNCH_DISPLAY} unchanged; unparsed strings echoed as-is.
 */
export function formatAttendanceHmTo12hWall(hm: string): string {
    const raw = hm.trim();
    if (raw === '') {
        return '—';
    }

    if (raw === ATTENDANCE_MISSING_PUNCH_DISPLAY) {
        return ATTENDANCE_MISSING_PUNCH_DISPLAY;
    }

    const total = attendanceParseHmToMinutes(raw);
    if (total === null) {
        return raw;
    }

    const h24 = Math.floor(total / 60) % 24;
    const m = total % 60;
    const period = h24 >= 12 ? 'PM' : 'AM';
    let h12 = h24 % 12;
    if (h12 === 0) {
        h12 = 12;
    }

    const minStr = String(m).padStart(2, '0');

    return `${h12}:${minStr} ${period}`;
}

function formatAttendanceScheduledHmForDisplay(
    hm: string | undefined | null,
): string {
    if (hm == null || hm.trim() === '') {
        return '—';
    }

    return formatAttendanceHmTo12hWall(hm);
}

function formatAttendanceActualPunchForDisplay(raw: string): string {
    const t = raw.trim();
    if (t === '') {
        return '';
    }

    return formatAttendanceHmTo12hWall(t);
}

/** Grace applied to first scheduled clock-in before “late” (mock UI; align later with shift templates). */
export const TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES = 15;

/**
 * Compare first segment actual-in with scheduled-in + grace day-level minutes (HH:mm strings).
 */
export function deriveTeamAttendancePunctuality(
    segments: TeamAttendanceSegment[],
    graceMinutes = TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES,
): TeamAttendancePunctuality {
    if (segments.length === 0) {
        return 'not_applicable';
    }

    const first = segments[0];
    const actualRaw = first.actual_in?.trim();
    if (!actualRaw) {
        return 'not_applicable';
    }

    const schedMin = attendanceParseHmToMinutes(first.scheduled_in);
    const actMin = attendanceParseHmToMinutes(actualRaw);
    if (schedMin === null || actMin === null) {
        return 'not_applicable';
    }

    let deadline = schedMin + graceMinutes;
    if (deadline >= 24 * 60) {
        deadline -= 24 * 60;
    }

    if (actMin <= deadline) {
        return 'on_time';
    }

    return 'late';
}

function hasAttendancePunchValue(v: string | null | undefined): boolean {
    return v != null && String(v).trim() !== '';
}

/**
 * Day-level status from actual punches: complete (all sessions paired), ongoing (open clock-in),
 * or incomplete (gaps, missing ins, out-without-in, etc.). Parity: {@link AttendanceRecordStatusResolver} (PHP).
 */
export function deriveTeamAttendanceRecordStatus(
    segments: TeamAttendanceSegment[],
): TeamAttendanceRecordStatus {
    if (segments.length === 0) {
        return 'incomplete';
    }

    for (let i = 0; i < segments.length; i++) {
        const seg = segments[i];
        const inP = hasAttendancePunchValue(seg.actual_in);
        const outP = hasAttendancePunchValue(seg.actual_out);

        if (inP && outP) {
            continue;
        }

        if (inP && !outP) {
            return 'ongoing';
        }

        if (!inP && outP) {
            return 'incomplete';
        }

        const hadPrior = segments
            .slice(0, i)
            .some(
                (s) =>
                    hasAttendancePunchValue(s.actual_in) ||
                    hasAttendancePunchValue(s.actual_out),
            );
        if (hadPrior) {
            return 'incomplete';
        }

        const laterHas = segments
            .slice(i + 1)
            .some(
                (s) =>
                    hasAttendancePunchValue(s.actual_in) ||
                    hasAttendancePunchValue(s.actual_out),
            );
        if (laterHas) {
            return 'incomplete';
        }

        return 'incomplete';
    }

    return 'complete';
}

/**
 * Minutes from startHm to endHm. If end is earlier on the clock, treat as crossing
 * midnight (overnight segments / night shifts).
 */
export function attendanceSpanMinutesBetweenClocks(
    startHm: string,
    endHm: string,
): number | null {
    const start = attendanceParseHmToMinutes(startHm);
    const end = attendanceParseHmToMinutes(endHm);
    if (start === null || end === null) {
        return null;
    }

    let endAdjusted = end;
    if (endAdjusted < start) {
        endAdjusted += 24 * 60;
    }

    const span = endAdjusted - start;
    if (span <= 0 || span > 48 * 60) {
        return null;
    }

    return span;
}

/** Floated `[start,end)` minute range for HH:mm punches; end extends across midnight consistently with spans. */
function attendanceFloatedClockIntervalPair(
    clockInHm: string,
    clockOutHm: string,
): { start: number; end: number } | null {
    const start = attendanceParseHmToMinutes(clockInHm.trim());
    if (start === null) {
        return null;
    }

    const span = attendanceSpanMinutesBetweenClocks(
        clockInHm.trim(),
        clockOutHm.trim(),
    );
    if (span === null) {
        return null;
    }

    const end = start + span;

    return { start, end };
}

function attendanceOverlapFloatedIntervalsMinutes(
    a: { start: number; end: number },
    b: { start: number; end: number },
): number {
    const overlapStart = Math.max(a.start, b.start);
    const overlapEnd = Math.min(a.end, b.end);

    return overlapEnd > overlapStart ? overlapEnd - overlapStart : 0;
}

/**
 * Net-time credit for one segment vs its scheduled window (one block of a split or the only block).
 *
 * **Session 1 (index 0) only:** row {@link TeamAttendancePunctuality} applies grace to the first
 * clock-in — **on time** credits from **scheduled** start (not reduced by punching in shortly after
 * start within grace); **late** credits from **actual** clock-in; **actual** out caps before
 * scheduled end on early exit; clock-out after scheduled end does not extend net.
 *
 * **Session 2+ (and segment 0 when punctuality is N/A):** grace does not apply. Net is pure
 * intersection of actual in→out with this session’s scheduled window: if punches **cover** the full
 * scheduled block, overlap equals full window length; early in/out shortens net. Between-session
 * unpaid gaps lie outside each segment window; embedded unpaid break for **one** segment is applied
 * later via {@link attendanceScheduledWindowOverlapMinutes} options.
 */
function attendanceOverlapScheduledVersusActualFloated(
    sched: { start: number; end: number },
    actual: { start: number; end: number },
    segmentIndex: number,
    punctuality?: TeamAttendancePunctuality | null,
): number {
    if (
        segmentIndex !== 0 ||
        punctuality == null ||
        punctuality === 'not_applicable'
    ) {
        return attendanceOverlapFloatedIntervalsMinutes(sched, actual);
    }

    const cappedEnd = Math.min(sched.end, actual.end);

    if (punctuality === 'on_time') {
        const credited = { start: sched.start, end: cappedEnd };

        return attendanceOverlapFloatedIntervalsMinutes(sched, credited);
    }

    /* late — first segment beyond grace */
    const credited = { start: actual.start, end: cappedEnd };

    return attendanceOverlapFloatedIntervalsMinutes(sched, credited);
}

/**
 * Net minutes: **sum** each session’s credited overlap (see {@link attendanceOverlapScheduledVersusActualFloated}).
 * Row punctuality adjusts **session 1 only**; later sessions use overlap only. Between-session gaps are
 * not deducted — they are outside each session’s scheduled window.
 *
 * For **simple session**, subtract template {@link AttendanceNetOverlapOptions.unpaidBreakMinutesFromTemplate}
 * after summing overlap (one scheduled block with embedded unpaid break).
 */
export function attendanceScheduledWindowOverlapMinutes(
    segments: TeamAttendanceSegment[],
    punctuality?: TeamAttendancePunctuality | null,
    options?: AttendanceNetOverlapOptions,
): number | null {
    let sum = 0;
    let any = false;

    for (let idx = 0; idx < segments.length; idx++) {
        const s = segments[idx];
        const ai = s.actual_in?.trim();
        const ao = s.actual_out?.trim();
        if (!ai || !ao) {
            continue;
        }

        const sched = attendanceFloatedClockIntervalPair(
            s.scheduled_in,
            s.scheduled_out,
        );
        const actual = attendanceFloatedClockIntervalPair(ai, ao);
        if (sched === null || actual === null) {
            return null;
        }

        sum += attendanceOverlapScheduledVersusActualFloated(
            sched,
            actual,
            idx,
            punctuality,
        );
        any = true;
    }

    if (!any) {
        return null;
    }

    const breakMins = options?.unpaidBreakMinutesFromTemplate ?? 0;
    if (
        options?.clockPattern === 'single_pair' &&
        breakMins > 0 &&
        segments.length === 1
    ) {
        return Math.max(0, sum - breakMins);
    }

    return sum;
}

/** @deprecated Prefer {@link attendanceScheduledWindowOverlapMinutes}. */
export function attendanceNetWithinScheduledOverlapMinutes(
    segments: TeamAttendanceSegment[],
    punctuality?: TeamAttendancePunctuality | null,
    options?: AttendanceNetOverlapOptions,
): number | null {
    return attendanceScheduledWindowOverlapMinutes(
        segments,
        punctuality,
        options,
    );
}

export function attendanceNetWithinScheduledOverlapDisplay(
    segments: TeamAttendanceSegment[],
    punctuality?: TeamAttendancePunctuality | null,
    options?: AttendanceNetOverlapOptions,
): string {
    const mins = attendanceScheduledWindowOverlapMinutes(
        segments,
        punctuality,
        options,
    );

    return mins === null ? '—' : formatAttendanceNetMinutesLabel(mins);
}

/**
 * Scheduled-window overlap totals (legacy name). For list **Gross time** use {@link attendanceActualDutyGrossDisplay}.
 */
export function attendanceDutyGrossWithinScheduledOverlapMinutes(
    segments: TeamAttendanceSegment[],
    punctuality?: TeamAttendancePunctuality | null,
    options?: AttendanceNetOverlapOptions,
): number | null {
    return attendanceScheduledWindowOverlapMinutes(
        segments,
        punctuality,
        options,
    );
}

export function attendanceDutyGrossWithinScheduledOverlapDisplay(
    segments: TeamAttendanceSegment[],
    punctuality?: TeamAttendancePunctuality | null,
    options?: AttendanceNetOverlapOptions,
): string {
    const mins = attendanceDutyGrossWithinScheduledOverlapMinutes(
        segments,
        punctuality,
        options,
    );

    return mins === null ? '—' : formatAttendanceNetMinutesLabel(mins);
}

/**
 * Minutes from first segment scheduled start → actual clock-in.
 * Returned only after scheduled start + grace (same threshold as Late); otherwise null.
 */
export function attendanceFirstSegmentLateMinutes(
    segments: TeamAttendanceSegment[],
    graceMinutes: number = TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES,
): number | null {
    if (segments.length === 0) {
        return null;
    }

    const first = segments[0];
    const actualRaw = first.actual_in?.trim();
    if (!actualRaw) {
        return null;
    }

    const schedMin = attendanceParseHmToMinutes(first.scheduled_in);
    const actMin = attendanceParseHmToMinutes(actualRaw);
    if (schedMin === null || actMin === null) {
        return null;
    }

    let deadline = schedMin + graceMinutes;
    if (deadline >= 24 * 60) {
        deadline -= 24 * 60;
    }

    if (actMin <= deadline) {
        return null;
    }

    const spanVsStart = attendanceSpanMinutesBetweenClocks(
        first.scheduled_in.trim(),
        actualRaw.trim(),
    );
    if (spanVsStart !== null && spanVsStart > 0) {
        return spanVsStart;
    }

    let deltaPlain = actMin - schedMin;
    if (deltaPlain < 0) {
        deltaPlain += 24 * 60;
    }

    return deltaPlain > 0 ? deltaPlain : null;
}

export function attendanceFirstSegmentLateMinutesDisplay(
    punctuality: TeamAttendancePunctuality,
    segments: TeamAttendanceSegment[],
    graceMinutes: number = TEAM_ATTENDANCE_FIRST_IN_GRACE_MINUTES,
): string {
    if (punctuality !== 'late') {
        return '—';
    }

    const mins = attendanceFirstSegmentLateMinutes(segments, graceMinutes);
    if (mins === null || mins <= 0) {
        return 'Late';
    }

    return `${mins} min late`;
}

/**
 * Scheduled work minutes minus undertime vs actual paired punches per segment (alternate metric;
 * grids use {@link attendanceNetWithinScheduledOverlapDisplay}.
 */
export function attendanceNetTimeCellDisplay(
    segments: TeamAttendanceSegment[],
): string {
    if (segments.length === 0) {
        return '—';
    }

    let scheduledTotal = 0;
    let undertimeSubtract = 0;

    for (const seg of segments) {
        const sched = attendanceSpanMinutesBetweenClocks(
            seg.scheduled_in,
            seg.scheduled_out,
        );
        if (sched === null) {
            return '—';
        }

        scheduledTotal += sched;

        const ai = seg.actual_in?.trim();
        const ao = seg.actual_out?.trim();
        if (!ai || !ao) {
            return '—';
        }

        const worked = attendanceSpanMinutesBetweenClocks(ai, ao);
        if (worked === null) {
            return '—';
        }

        undertimeSubtract += Math.max(0, sched - worked);
    }

    const netMinutes = Math.max(0, scheduledTotal - undertimeSubtract);

    return formatAttendanceNetMinutesLabel(netMinutes);
}

export function formatAttendanceNetMinutesLabel(totalMinutes: number): string {
    if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) {
        return '—';
    }

    const h = Math.floor(totalMinutes / 60);
    const m = Math.round(totalMinutes % 60);
    let mm = m;
    let hh = h;
    if (mm >= 60) {
        hh += 1;
        mm -= 60;
    }

    if (hh === 0) {
        return `${mm}m`;
    }

    if (mm === 0) {
        return `${hh}h`;
    }

    return `${hh}h ${String(mm).padStart(2, '0')}m`;
}

/**
 * Scheduled wall times for each session ( HH:mm ranges ), one row per segment for splits.
 */
export function attendanceScheduledWindowBody(
    segments: TeamAttendanceSegment[],
    pattern: ClockPattern,
): string {
    if (segments.length === 0) {
        return '—';
    }

    if (pattern === 'single_pair') {
        const s = segments[0];

        return `${formatAttendanceScheduledHmForDisplay(s?.scheduled_in)} – ${formatAttendanceScheduledHmForDisplay(s?.scheduled_out)}`;
    }

    return segments
        .map(
            (s) =>
                `${s.label}: ${formatAttendanceScheduledHmForDisplay(s.scheduled_in)} – ${formatAttendanceScheduledHmForDisplay(s.scheduled_out)}`,
        )
        .join('\n');
}

/**
 * Sum of gaps between consecutive scheduled segments (typically lunch / unpaid gap for split shifts).
 */
export function attendanceScheduledSessionsBreakMinutesTotal(
    segments: TeamAttendanceSegment[],
): number | null {
    if (segments.length < 2) {
        return null;
    }

    let total = 0;

    for (let i = 0; i < segments.length - 1; i++) {
        const gap = attendanceSpanMinutesBetweenClocks(
            segments[i].scheduled_out,
            segments[i + 1].scheduled_in,
        );
        if (gap === null) {
            return null;
        }

        total += gap;
    }

    return total;
}

/**
 * Narrative supplement under “Scheduled window” (break from split-session gaps vs single-session note).
 */
export function attendanceScheduledBreakSupplementLines(
    segments: TeamAttendanceSegment[],
    pattern: ClockPattern,
    unpaidBreakMinutesFromTemplate?: number,
): string {
    if (pattern === 'split_sessions' && segments.length >= 2) {
        const total = attendanceScheduledSessionsBreakMinutesTotal(segments);

        if (total === null) {
            return 'Between-session break (scheduled): —';
        }

        return `Between-session break (scheduled): ${formatAttendanceNetMinutesLabel(total)} unpaid gap between sessions.`;
    }

    if (
        pattern === 'single_pair' &&
        unpaidBreakMinutesFromTemplate !== undefined &&
        unpaidBreakMinutesFromTemplate > 0
    ) {
        return `Unpaid break (from assigned shift template): ${formatAttendanceNetMinutesLabel(unpaidBreakMinutesFromTemplate)} minutes — deducted from net time for this session.`;
    }

    return 'No unpaid break minutes recorded for this session (assign a shift template with breaktime when applicable).';
}

/**
 * Break duration string for viewer “Breaktime” field (split-session scheduled gap).
 */
export function attendanceScheduledBreakFieldDisplay(
    segments: TeamAttendanceSegment[],
    pattern: ClockPattern,
    unpaidBreakMinutesFromTemplate?: number,
): string {
    if (pattern === 'split_sessions' && segments.length >= 2) {
        const total = attendanceScheduledSessionsBreakMinutesTotal(segments);

        return total === null ? '—' : formatAttendanceNetMinutesLabel(total);
    }

    if (
        pattern === 'single_pair' &&
        unpaidBreakMinutesFromTemplate !== undefined &&
        unpaidBreakMinutesFromTemplate > 0
    ) {
        return formatAttendanceNetMinutesLabel(unpaidBreakMinutesFromTemplate);
    }

    return '—';
}

/**
 * Gross duty minutes: **sum** of each session’s actual clock-in→clock-out span (paired punches only).
 * Split shifts exclude the midday gap — not first-in→last-out across the break.
 */
export function attendanceActualDutyGrossMinutes(
    segments: TeamAttendanceSegment[],
): number | null {
    return attendanceActualWorkedSumMinutes(segments);
}

/** Sum of each segment’s actual in→out spans (omit incomplete pairs). Canonical implementation for gross. */
export function attendanceActualWorkedSumMinutes(
    segments: TeamAttendanceSegment[],
): number | null {
    let sum = 0;
    let any = false;

    for (const s of segments) {
        const ai = s.actual_in?.trim();
        const ao = s.actual_out?.trim();
        if (!ai || !ao) {
            continue;
        }

        const span = attendanceSpanMinutesBetweenClocks(ai, ao);
        if (span === null) {
            return null;
        }

        sum += span;
        any = true;
    }

    return any ? sum : null;
}

export function attendanceActualDutyGrossDisplay(
    segments: TeamAttendanceSegment[],
): string {
    const mins = attendanceActualDutyGrossMinutes(segments);

    return mins === null ? '—' : formatAttendanceNetMinutesLabel(mins);
}

export function attendanceActualWorkedSumDisplay(
    segments: TeamAttendanceSegment[],
): string {
    const mins = attendanceActualWorkedSumMinutes(segments);

    return mins === null ? '—' : formatAttendanceNetMinutesLabel(mins);
}

/** Plain-language recording style sentence for dialogs (badges discouraged). */
export function attendanceRecordingStyleDialogText(
    pattern: ClockPattern,
    isOvernightSchedule: boolean,
): string {
    let text = clockPatternDescription(pattern);
    if (isOvernightSchedule) {
        text += ' Overnight-spanning scheduled window.';
    }

    return text;
}

/** Attendance viewer: single-line style label (three allowed values only). Overnight wins when set. */
export function attendanceRecordingStyleShortLabel(
    pattern: ClockPattern,
    isOvernightSchedule: boolean,
): string {
    if (isOvernightSchedule) {
        return 'Overnight';
    }

    return pattern === 'single_pair' ? 'Simple session' : 'Split Sessions';
}

export const ATTENDANCE_VIEW_GROSS_PUNCH_SUM_TOOLTIP =
    "Per session: sum of each actual clock-in→clock-out span (Session 1 + Session 2 + … paired punches only). The unpaid gap between scheduled sessions is not included. Grace does not affect Gross. Incomplete pairs omit that block's span — all missing can yield —.";

export const ATTENDANCE_VIEW_NET_PUNCH_SUM_TOOLTIP =
    'Net is the sum of credited overlap per session, then for a simple (single) session the shift template’s unpaid break minutes are subtracted. Session 1: grace/on-time credits from scheduled start; late from actual in. Split sessions: gaps between session windows are not work time. Incomplete pairs omit that session.';

export const ATTENDANCE_VIEW_FIRST_IN_GRACE_TOOLTIP =
    'Late-arrival allowance after the first scheduled clock-in until an arrival counts as Late (demo: fixed minutes; aligns with templates when API supplies grace).';

export const ATTENDANCE_VIEW_LATE_MINUTES_TOOLTIP =
    'Minutes from Session 1 scheduled start until the recorded clock-in, shown only when that arrival is after start + grace (Late). Differs from “minutes past grace only”.';

export const ATTENDANCE_VIEW_BREAK_FIELD_TOOLTIP =
    'Split shifts: unpaid gap between scheduled sessions. Simple session: unpaid break minutes from the assigned work schedule template (subtracted from net).';

export const ATTENDANCE_VIEW_PUNCTUALITY_TOOLTIP =
    'Late when first clock-in is after Session 1 scheduled start plus grace; on time otherwise (mock grace until schedules API provides it per row).';

export const ATTENDANCE_VIEW_PROFILE_ATTENDANCE_ID_TOOLTIP =
    'Biometric or HR “attendance ID” stored on the employee profile (same as Employee Schedules). Empty when the profile has no ID yet.';

export const ATTENDANCE_VIEW_INGEST_KEY_TOOLTIP =
    'Optional per-day key from the ingest pipeline (device row or import). Separate from the profile attendance ID.';

export const ATTENDANCE_VIEW_AUDIT_ORIGINAL_SOURCE_TOOLTIP =
    'How this row was first created (device ingest, HR form, or import).';

export const ATTENDANCE_VIEW_AUDIT_LAST_SAVED_VIA_TOOLTIP =
    'Channel of the latest save (e.g. manual after HR corrects a device row).';

/** Add/edit attendance entry dialog — tooltips (replaces inline helper paragraphs). */
export const TEAM_ATTENDANCE_FORM_HEADER_TOOLTIP =
    'Select unit and employee. Schedule and attendance ID load from Employee Schedules; enter punches below.';

export const TEAM_ATTENDANCE_FORM_STATUS_TOOLTIP =
    'Derived from actual punches: complete when every session has in and out; ongoing when a session is missing clock-out; incomplete otherwise.';

export const TEAM_ATTENDANCE_FORM_WORK_SCHEDULE_TOOLTIP =
    'Loaded from the selected employee’s assigned template (Employee Schedules). Not editable on this form.';

export const TEAM_ATTENDANCE_FORM_PROFILE_ATTENDANCE_ID_TOOLTIP =
    'From the employee profile (biometric / device ID). Not editable on this form.';

export const TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_LOCKED_TOOLTIP =
    'Scheduled times come from the work schedule template. Edit actual punches only.';

export const TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_UNLOCKED_TOOLTIP =
    'Scheduled = expected window; Actual = punches shown in the table.';

export const TEAM_ATTENDANCE_FORM_RECORDING_STYLE_TOOLTIP =
    'Simple session: one clock-in and one clock-out per work day. Split sessions: multiple same-day blocks. Overnight shows when the assigned template is marked overnight.';

/** User-facing badge label for stored clock pattern. */
export function clockPatternBadgeLabel(pattern: ClockPattern): string {
    return pattern === 'single_pair' ? 'Simple session' : 'Split sessions';
}

/** Short label for settings / dialogs. */
export function clockPatternDescription(pattern: ClockPattern): string {
    return pattern === 'single_pair'
        ? 'One clock-in and one clock-out per work day.'
        : 'Multiple same-day sessions (e.g. morning and afternoon blocks).';
}

export function clockPatternBadgeClass(pattern: ClockPattern): string {
    return pattern === 'single_pair'
        ? 'shrink-0 border-muted-foreground/25 bg-muted/40 px-1.5 py-0 text-[10px] font-medium text-muted-foreground'
        : workScheduleSplitSessionsBadgeClass;
}

export function rowMatchesAttendanceRecordingStyleFilter(
    row: TeamAttendanceRow,
    filter: TeamAttendanceRecordingStyleFilter,
): boolean {
    switch (filter) {
        case 'all':
            return true;

        case 'overnight':
            return row.is_overnight_schedule;

        case 'simple':
            return (
                row.clock_pattern === 'single_pair' &&
                !row.is_overnight_schedule
            );

        case 'split':
            return (
                row.clock_pattern === 'split_sessions' &&
                !row.is_overnight_schedule
            );
    }
}

/** Table “Time”: actual clock in/out only (no scheduled column in grid). */
export function clockInOutDisplay(
    pattern: ClockPattern,
    segments: TeamAttendanceSegment[],
): string {
    if (segments.length === 0) {
        return '—';
    }

    const miss = ATTENDANCE_MISSING_PUNCH_DISPLAY;

    if (pattern === 'single_pair') {
        const s = segments[0];
        const a = s.actual_in?.trim();
        const b = s.actual_out?.trim();

        if (a && b) {
            return `${formatAttendanceActualPunchForDisplay(a)} – ${formatAttendanceActualPunchForDisplay(b)}`;
        }

        if (a) {
            return `${formatAttendanceActualPunchForDisplay(a)} – ${miss}`;
        }

        if (b) {
            return `${miss} – ${formatAttendanceActualPunchForDisplay(b)}`;
        }

        return '—';
    }

    return segments
        .map((s) => {
            const a = s.actual_in?.trim();
            const b = s.actual_out?.trim();

            if (a && b) {
                return `${s.label}: ${formatAttendanceActualPunchForDisplay(a)} – ${formatAttendanceActualPunchForDisplay(b)}`;
            }

            if (a) {
                return `${s.label}: ${formatAttendanceActualPunchForDisplay(a)} – ${miss}`;
            }

            if (b) {
                return `${s.label}: ${miss} – ${formatAttendanceActualPunchForDisplay(b)}`;
            }

            return `${s.label}: —`;
        })
        .join('\n');
}

export function attendanceStatusLabel(
    status: TeamAttendanceRecordStatus,
): string {
    switch (status) {
        case 'complete':
            return 'Complete';

        case 'ongoing':
            return 'Ongoing';

        case 'incomplete':
            return 'Incomplete';

        default: {
            return status;
        }
    }
}

export function punctualityLabel(
    punctuality: TeamAttendancePunctuality,
): string {
    switch (punctuality) {
        case 'on_time':
            return 'On time';

        case 'late':
            return 'Late';

        case 'not_applicable':
            return 'N/A';

        default: {
            return punctuality;
        }
    }
}

export function punctualityBadgeClass(
    punctuality: TeamAttendancePunctuality,
): string {
    if (punctuality === 'on_time') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-50';
    }

    if (punctuality === 'late') {
        return 'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:border-amber-400/35 dark:bg-amber-400/14 dark:text-amber-50';
    }

    return 'border-muted-foreground/35 bg-muted/40 text-muted-foreground';
}

export function attendanceStatusBadgeClass(
    status: TeamAttendanceRecordStatus,
): string {
    if (status === 'complete') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300';
    }

    if (status === 'incomplete') {
        return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300';
    }

    return 'border-sky-500/35 bg-sky-500/12 text-sky-900 dark:border-sky-400/35 dark:bg-sky-500/14 dark:text-sky-50';
}

/** Local date + time for ISO-8601 strings from the server (or mock anchors). */
export function formatIsoDateTimeLocal(iso: string): string {
    const ms = Date.parse(iso);
    if (Number.isNaN(ms)) {
        return iso;
    }

    return new Date(ms).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export function attendanceSourceBadgeClass(
    source: TeamAttendanceSource,
): string {
    if (source === 'device') {
        return 'border-violet-500/30 bg-violet-500/10 text-violet-800 dark:border-violet-400/30 dark:bg-violet-400/15 dark:text-violet-200';
    }

    if (source === 'import') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/15 dark:text-amber-100';
    }

    return 'border-slate-500/30 bg-slate-500/10 text-slate-800 dark:border-slate-400/30 dark:bg-slate-500/15 dark:text-slate-100';
}

export function attendanceSourceLabel(source: TeamAttendanceSource): string {
    switch (source) {
        case 'device':
            return 'Device';

        case 'manual':
            return 'Manual';

        case 'import':
            return 'Import';

        default: {
            return source;
        }
    }
}
