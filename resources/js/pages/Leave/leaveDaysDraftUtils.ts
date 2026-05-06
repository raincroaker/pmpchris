import type {
    TeamLeaveDayDraft,
    TeamLeaveRow,
} from '@/pages/Leave/teamLeaveTypes';

export type SkippedCalendarDay = {
    date: string;
    reason: string;
};

function formatLocalYmd(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

export function sortLeaveDays(days: TeamLeaveDayDraft[]): TeamLeaveDayDraft[] {
    return [...days].sort((a, b) => a.date.localeCompare(b.date));
}

export function leaveUnitsTotal(days: TeamLeaveDayDraft[]): number {
    return days.reduce((sum, d) => sum + (d.is_half_day ? 0.5 : 1), 0);
}

/**
 * Normalize policy-style decimal strings such as SQL/Eloquent casts ("15.0000").
 */
export function formatStoredDecimalHuman(
    raw: string | number | null | undefined,
): string | null {
    if (raw === null || raw === undefined) {
        return null;
    }

    const trimmed = String(raw).trim();

    if (trimmed === '') {
        return null;
    }

    const n = Number(trimmed.replace(',', '.'));

    if (!Number.isFinite(n)) {
        return trimmed;
    }

    return formatLeaveUnitsHuman(n);
}

export function formatLeaveUnitsHuman(units: number): string {
    if (Number.isInteger(units)) {
        return String(units);
    }
    const whole = Math.floor(units);
    const frac = units - whole;

    if (frac >= 0.5 - 1e-9) {
        return whole === 0 ? '½' : `${whole}½`;
    }

    return String(units);
}

export function formatLeaveUnitsLabel(days: TeamLeaveDayDraft[]): string {
    const units = leaveUnitsTotal(days);
    const u = formatLeaveUnitsHuman(units);
    const noun = units === 1 || units === 0.5 ? 'day' : 'days';

    return `${u} ${noun}`;
}

export function deriveLegacyBoundsFromLeaveDays(days: TeamLeaveDayDraft[]): {
    start_date: string;
    end_date: string;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
} {
    const sorted = sortLeaveDays(days);
    if (sorted.length === 0) {
        return {
            start_date: '',
            end_date: '',
            is_half_day_start: false,
            is_half_day_end: false,
        };
    }

    const first = sorted[0];
    const last = sorted[sorted.length - 1];

    return {
        start_date: first.date,
        end_date: last.date,
        is_half_day_start: first.is_half_day,
        is_half_day_end: last.is_half_day,
    };
}

/**
 * Rebuild per-day rows from a legacy range row (edit path until API stores days).
 */
/** Prefer persisted `leave_days` from the backend; otherwise expand legacy span fields. */
export function resolveLeaveDaysForRow(row: TeamLeaveRow): TeamLeaveDayDraft[] {
    const fromApi = row.leave_days;
    if (fromApi !== undefined && fromApi.length > 0) {
        return sortLeaveDays(fromApi);
    }

    return expandLegacyRowToLeaveDays(row);
}

export function expandLegacyRowToLeaveDays(
    row: TeamLeaveRow,
): TeamLeaveDayDraft[] {
    if (row.start_date === row.end_date) {
        return [
            {
                date: row.start_date,
                is_half_day: row.is_half_day_start || row.is_half_day_end,
            },
        ];
    }

    const out: TeamLeaveDayDraft[] = [];
    const cur = new Date(`${row.start_date}T12:00:00`);
    const end = new Date(`${row.end_date}T12:00:00`);

    while (cur.getTime() <= end.getTime()) {
        const iso = formatLocalYmd(cur);
        const isFirst = iso === row.start_date;
        const isLast = iso === row.end_date;
        let isHalf = false;
        if (isFirst && isLast) {
            isHalf = row.is_half_day_start || row.is_half_day_end;
        } else if (isFirst) {
            isHalf = row.is_half_day_start;
        } else if (isLast) {
            isHalf = row.is_half_day_end;
        }

        out.push({ date: iso, is_half_day: isHalf });
        cur.setDate(cur.getDate() + 1);
    }

    return out;
}
