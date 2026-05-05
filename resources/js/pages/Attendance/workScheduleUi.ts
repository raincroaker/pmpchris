import type { ClockPattern } from '@/pages/Attendance/attendanceRulesTypes';

/** Badge styling for Split Sessions (sky). */
export const workScheduleSplitSessionsBadgeClass =
    'shrink-0 border-sky-500/40 bg-sky-500/10 px-1.5 py-0 text-[10px] font-medium text-sky-900 dark:border-sky-400/35 dark:bg-sky-500/15 dark:text-sky-100';

/** Badge styling for Overnight single session (violet). */
export const workScheduleOvernightBadgeClass =
    'shrink-0 border-violet-500/40 bg-violet-500/10 px-1.5 py-0 text-[10px] font-medium text-violet-900 dark:border-violet-400/35 dark:bg-violet-500/15 dark:text-violet-100';

/**
 * Extra tokens so local search matches friendly labels (e.g. "Single Session",
 * "Split Sessions") in addition to internal pattern keys.
 */
export function clockPatternSearchHaystack(pattern: ClockPattern): string {
    const slug = pattern.replace('_', ' ');
    const friendly =
        pattern === 'single_pair'
            ? 'single session single_pair'
            : 'split sessions split_sessions';

    return `${slug} ${friendly}`;
}

/** Display scheduled net hours in work-schedule rule panels (0–2 decimal places). */
export function formatWorkScheduleNetHours(hours: number): string {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(hours);
}

/** Minutes from midnight for HH:mm (24h). */
export function parseWorkScheduleTimeToMinutes(value: string): number | null {
    if (!value || !value.includes(':')) {
        return null;
    }
    const [hRaw, mRaw] = value.split(':');
    const h = Number.parseInt(hRaw, 10);
    const m = Number.parseInt(mRaw, 10);
    if (!Number.isFinite(h) || !Number.isFinite(m)) {
        return null;
    }

    return h * 60 + m;
}

/**
 * Approximate on-duty span for one in/out pair (breaks not deducted).
 * Overnight crosses midnight into the next calendar day.
 */
export function approximateWorkScheduleSpanMinutes(
    timeIn: string,
    timeOut: string,
    isOvernight: boolean,
): number | null {
    const start = parseWorkScheduleTimeToMinutes(timeIn);
    const end = parseWorkScheduleTimeToMinutes(timeOut);
    if (start === null || end === null) {
        return null;
    }

    if (isOvernight || end <= start) {
        return 24 * 60 - start + end;
    }

    return end - start;
}

/** Human-readable duration for OT preview (e.g. 2h, 1h 30m). */
export function formatWorkScheduleDurationLabel(totalMinutes: number): string {
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    if (m === 0) {
        return `${h}h`;
    }

    return `${h}h ${m}m`;
}
