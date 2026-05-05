import type { HolidayRule } from '@/pages/Attendance/attendanceRulesTypes';

const MAX_YEAR_SPAN = 120;

export type HolidayOccurrence = {
    /** Stable key for list/grid (`ruleId::YYYY-MM-DD`). */
    occurrenceId: string;
    occurrenceDate: string;
    rule: HolidayRule;
};

function startOfDay(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function addDays(d: Date, days: number): Date {
    const next = new Date(d);
    next.setDate(next.getDate() + days);

    return next;
}

function daysInMonth(year: number, month1To12: number): number {
    return new Date(year, month1To12, 0).getDate();
}

function dateInYear(year: number, month1To12: number, day: number): Date {
    const dim = daysInMonth(year, month1To12);
    const d = Math.min(day, dim);

    return new Date(year, month1To12 - 1, d);
}

function toIsoDayKey(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

export function normalizeHolidayRuleDates(rule: HolidayRule): {
    start: string;
    end: string;
} {
    const legacy = rule as HolidayRule & { date?: string };
    if (legacy.date && legacy.date !== '') {
        return { start: legacy.date, end: legacy.date };
    }

    return { start: rule.start_date, end: rule.end_date };
}

/**
 * Every calendar day from `startIso` through `endIso` inclusive (ISO `YYYY-MM-DD`).
 */
export function eachIsoDayInClosedRange(
    startIso: string,
    endIso: string,
): string[] {
    const [ys, ms, ds] = startIso.split('-').map(Number);
    const [ye, me, de] = endIso.split('-').map(Number);
    const start = new Date(ys, (ms ?? 1) - 1, ds ?? 1);
    const end = new Date(ye, (me ?? 1) - 1, de ?? 1);
    if (start.getTime() > end.getTime()) {
        return [];
    }

    const out: string[] = [];
    for (
        let d = new Date(start);
        d.getTime() <= end.getTime();
        d = addDays(d, 1)
    ) {
        out.push(toIsoDayKey(d));
    }

    return out;
}

function compareIsoDay(a: string, b: string): number {
    return a.localeCompare(b);
}

function yearlyOccurrenceFirstDay(rule: HolidayRule, year: number): string {
    const { start } = normalizeHolidayRuleDates(rule);
    const [, m0, d0] = start.split('-').map(Number);

    return toIsoDayKey(dateInYear(year, m0 ?? 1, d0 ?? 1));
}

function expandRuleDaysForYear(rule: HolidayRule, year: number): string[] {
    const { start, end } = normalizeHolidayRuleDates(rule);
    const [, ms, ds] = start.split('-').map(Number);
    const [, me, de] = end.split('-').map(Number);

    const startD = dateInYear(year, ms ?? 1, ds ?? 1);
    const endD = dateInYear(year, me ?? 1, de ?? 1);
    const startKey = toIsoDayKey(startD);
    const endKey = toIsoDayKey(endD);

    return eachIsoDayInClosedRange(startKey, endKey);
}

/**
 * Expand holiday rules into per-day occurrences visible between `rangeStart` and `rangeEnd` (inclusive calendar days; time ignored).
 */
export function expandHolidayOccurrencesInRange(
    rules: HolidayRule[],
    rangeStart: Date,
    rangeEnd: Date,
): HolidayOccurrence[] {
    const gridFirst = startOfDay(rangeStart);
    const gridLast = startOfDay(rangeEnd);
    if (gridFirst.getTime() > gridLast.getTime()) {
        return [];
    }

    const gridStartKey = toIsoDayKey(gridFirst);
    const gridEndKey = toIsoDayKey(gridLast);
    const gridEndYear = gridLast.getFullYear();

    const out: HolidayOccurrence[] = [];

    for (const rule of rules) {
        const recurrence = rule.recurrence;

        if (!recurrence || recurrence.frequency !== 'yearly') {
            const { start, end } = normalizeHolidayRuleDates(rule);
            const days = eachIsoDayInClosedRange(start, end);
            for (const dayKey of days) {
                if (
                    compareIsoDay(dayKey, gridStartKey) >= 0 &&
                    compareIsoDay(dayKey, gridEndKey) <= 0
                ) {
                    out.push({
                        occurrenceId: `${rule.id}::${dayKey}`,
                        occurrenceDate: dayKey,
                        rule,
                    });
                }
            }

            continue;
        }

        const { start: anchorStart } = normalizeHolidayRuleDates(rule);
        const anchorYear = Number(anchorStart.split('-')[0] ?? gridEndYear);
        const interval = Math.max(1, recurrence.interval);
        const ends = recurrence.ends;

        let yearlyIndex = 0;
        for (
            let y = anchorYear;
            y <= gridEndYear + interval && yearlyIndex < MAX_YEAR_SPAN;
            y += interval
        ) {
            if (ends.type === 'count' && yearlyIndex >= ends.count) {
                break;
            }

            const firstDay = yearlyOccurrenceFirstDay(rule, y);
            if (
                ends.type === 'until' &&
                compareIsoDay(firstDay, ends.date) > 0
            ) {
                break;
            }

            const dayKeys = expandRuleDaysForYear(rule, y);
            for (const dayKey of dayKeys) {
                if (
                    compareIsoDay(dayKey, gridStartKey) >= 0 &&
                    compareIsoDay(dayKey, gridEndKey) <= 0
                ) {
                    out.push({
                        occurrenceId: `${rule.id}::${dayKey}`,
                        occurrenceDate: dayKey,
                        rule,
                    });
                }
            }

            yearlyIndex += 1;
        }
    }

    out.sort((a, b) =>
        a.occurrenceDate === b.occurrenceDate
            ? a.rule.name.localeCompare(b.rule.name)
            : compareIsoDay(a.occurrenceDate, b.occurrenceDate),
    );

    return out;
}

export function formatHolidayDateRangeLabel(
    startIso: string,
    endIso: string,
): string {
    if (startIso === endIso) {
        return new Date(`${startIso}T12:00:00`).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    const a = new Date(`${startIso}T12:00:00`);
    const b = new Date(`${endIso}T12:00:00`);
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };

    return `${a.toLocaleDateString(undefined, opts)} – ${b.toLocaleDateString(
        undefined,
        {
            ...opts,
            year: 'numeric',
        },
    )}`;
}

export function formatHolidayRecurrenceSummary(rule: HolidayRule): string {
    const r = rule.recurrence;
    if (!r || r.frequency !== 'yearly') {
        return 'One-time';
    }

    const interval = Math.max(1, r.interval);
    const intervalPart =
        interval === 1
            ? 'Repeats every year'
            : `Repeats every ${interval} years`;

    if (r.ends.type === 'never') {
        return `${intervalPart} · No end`;
    }

    if (r.ends.type === 'until') {
        const untilReadable = new Date(
            `${r.ends.date}T12:00:00`,
        ).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

        return `${intervalPart} · Until ${untilReadable}`;
    }

    return `${intervalPart} · ${r.ends.count} occurrence${r.ends.count === 1 ? '' : 's'}`;
}
