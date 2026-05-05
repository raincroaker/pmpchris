import type {
    CalendarEvent,
    CalendarEventRecurrence,
    CalendarEventRecurrenceException,
} from '@/components/calendar/calendar-events';

const MAX_OCCURRENCES_PER_SERIES = 500;

const WEEKDAY_LABELS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function startOfDay(d: Date): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function endOfDay(d: Date): Date {
    return new Date(
        d.getFullYear(),
        d.getMonth(),
        d.getDate(),
        23,
        59,
        59,
        999,
    );
}

function addDays(d: Date, days: number): Date {
    const next = new Date(d);
    next.setDate(next.getDate() + days);
    return next;
}

function compareDayOnly(a: Date, b: Date): number {
    const da = startOfDay(a).getTime();
    const db = startOfDay(b).getTime();
    if (da === db) {
        return 0;
    }

    return da < db ? -1 : 1;
}

/** Monday as week start when weekStartsOn === 1 (matches MonthCalendarShell). */
function startOfWeekMonday(d: Date): Date {
    const day = d.getDay();
    const offset = (day + 6) % 7;
    return startOfDay(addDays(d, -offset));
}

export function parseEventDateString(value: string): Date | null {
    const normalized = value.replace(' ', 'T');
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatEventTimestamp(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${day} ${h}:${min}`;
}

/**
 * First and last calendar days shown in the 6×7 grid (matches MonthCalendarShell `gridDays`).
 */
export function getVisibleGridRange(
    displayDate: Date,
    weekStartsOn: 0 | 1,
): { start: Date; end: Date } {
    const year = displayDate.getFullYear();
    const month = displayDate.getMonth();
    const firstOfMonth = new Date(year, month, 1);
    const firstWeekday = firstOfMonth.getDay();
    const offset = weekStartsOn === 1 ? (firstWeekday + 6) % 7 : firstWeekday;
    const startDate = new Date(year, month, 1 - offset);
    const endDate = addDays(startDate, 41);
    return {
        start: startOfDay(startDate),
        end: endOfDay(endDate),
    };
}

function durationMs(start: Date, end: Date): number {
    return end.getTime() - start.getTime();
}

function shiftStartPreserveDuration(
    anchorStart: Date,
    anchorEnd: Date,
    newStartDay: Date,
): { start: Date; end: Date } {
    const newStart = new Date(startOfDay(newStartDay));
    newStart.setHours(
        anchorStart.getHours(),
        anchorStart.getMinutes(),
        anchorStart.getSeconds(),
        anchorStart.getMilliseconds(),
    );
    const dur = durationMs(anchorStart, anchorEnd);
    const newEnd = new Date(newStart.getTime() + dur);
    return { start: newStart, end: newEnd };
}

function dayInRange(day: Date, rangeStart: Date, rangeEnd: Date): boolean {
    const t = startOfDay(day).getTime();
    return t >= startOfDay(rangeStart).getTime() && t <= rangeEnd.getTime();
}

function recurrenceUntilEnd(
    ends: CalendarEventRecurrence['ends'],
): Date | null {
    if (ends.type === 'until') {
        const [y, m, d] = ends.date.split('-').map(Number);
        return endOfDay(new Date(y, (m ?? 1) - 1, d ?? 1));
    }

    return null;
}

function matchesWeeklyPattern(
    d: Date,
    anchor: Date,
    interval: number,
    byWeekday: number[],
    weekStartsOn: 0 | 1,
): boolean {
    const d0 = startOfDay(d);
    const a0 = startOfDay(anchor);
    if (compareDayOnly(d0, a0) < 0) {
        return false;
    }

    if (!byWeekday.includes(d0.getDay())) {
        return false;
    }

    const anchorWeek =
        weekStartsOn === 1
            ? startOfWeekMonday(a0)
            : startOfDay(addDays(a0, -a0.getDay()));
    const dWeek =
        weekStartsOn === 1
            ? startOfWeekMonday(d0)
            : startOfDay(addDays(d0, -d0.getDay()));

    const msWeek = 7 * 24 * 60 * 60 * 1000;
    const weeksBetween = Math.round(
        (dWeek.getTime() - anchorWeek.getTime()) / msWeek,
    );
    if (weeksBetween < 0 || weeksBetween % interval !== 0) {
        return false;
    }

    return true;
}

function collectDailyOccurrenceDays(
    anchor: Date,
    interval: number,
    rangeStart: Date,
    rangeEnd: Date,
    ends: CalendarEventRecurrence['ends'],
): Date[] {
    const days: Date[] = [];
    const untilCap = recurrenceUntilEnd(ends);

    for (let k = 0; k < MAX_OCCURRENCES_PER_SERIES; k += 1) {
        if (ends.type === 'count' && k >= ends.count) {
            break;
        }

        const d = addDays(startOfDay(anchor), k * interval);

        if (untilCap && d.getTime() > untilCap.getTime()) {
            break;
        }

        if (dayInRange(d, rangeStart, rangeEnd)) {
            days.push(new Date(d));
        }
    }

    return days;
}

function collectWeeklyOccurrenceDays(
    anchor: Date,
    interval: number,
    byWeekday: number[],
    weekStartsOn: 0 | 1,
    rangeStart: Date,
    rangeEnd: Date,
    ends: CalendarEventRecurrence['ends'],
): Date[] {
    const days: Date[] = [];
    const untilCap = recurrenceUntilEnd(ends);
    const by = byWeekday.length ? byWeekday : [anchor.getDay()];
    let ordinal = 0;
    let d = startOfDay(anchor);
    const scanEnd = untilCap
        ? new Date(
              Math.min(
                  endOfDay(addDays(rangeEnd, 730)).getTime(),
                  untilCap.getTime(),
              ),
          )
        : endOfDay(addDays(rangeEnd, 730));

    while (
        d.getTime() <= scanEnd.getTime() &&
        ordinal < MAX_OCCURRENCES_PER_SERIES
    ) {
        if (matchesWeeklyPattern(d, anchor, interval, by, weekStartsOn)) {
            if (untilCap && d.getTime() > untilCap.getTime()) {
                break;
            }

            ordinal += 1;

            if (ends.type === 'count' && ordinal > ends.count) {
                break;
            }

            if (dayInRange(d, rangeStart, rangeEnd)) {
                days.push(new Date(d));
            }
        }

        d = addDays(d, 1);
    }

    return days;
}

function collectMonthlyOccurrenceDays(
    anchor: Date,
    interval: number,
    rangeStart: Date,
    rangeEnd: Date,
    ends: CalendarEventRecurrence['ends'],
): Date[] {
    const days: Date[] = [];
    const untilCap = recurrenceUntilEnd(ends);
    const dom = anchor.getDate();
    const anchorMonth = anchor.getMonth();
    const anchorYear = anchor.getFullYear();
    const anchorSod = startOfDay(anchor);

    let emitted = 0;
    for (let m = 0; m < 4800; m += interval) {
        const candidate = new Date(anchorYear, anchorMonth + m, dom);

        if (candidate.getDate() !== dom) {
            continue;
        }

        if (compareDayOnly(candidate, anchorSod) < 0) {
            continue;
        }

        if (untilCap && candidate.getTime() > untilCap.getTime()) {
            break;
        }

        emitted += 1;
        if (ends.type === 'count' && emitted > ends.count) {
            break;
        }

        if (dayInRange(candidate, rangeStart, rangeEnd)) {
            days.push(startOfDay(candidate));
        }

        if (emitted >= MAX_OCCURRENCES_PER_SERIES) {
            break;
        }
    }

    return days;
}

function collectYearlyOccurrenceDays(
    anchor: Date,
    interval: number,
    rangeStart: Date,
    rangeEnd: Date,
    ends: CalendarEventRecurrence['ends'],
): Date[] {
    const days: Date[] = [];
    const untilCap = recurrenceUntilEnd(ends);
    const month = anchor.getMonth();
    const dom = anchor.getDate();
    const y0 = anchor.getFullYear();
    const anchorSod = startOfDay(anchor);

    let emitted = 0;
    for (let iy = 0; iy < 400; iy += interval) {
        const candidate = new Date(y0 + iy, month, dom);

        if (candidate.getMonth() !== month) {
            continue;
        }

        if (compareDayOnly(candidate, anchorSod) < 0) {
            continue;
        }

        if (untilCap && candidate.getTime() > untilCap.getTime()) {
            break;
        }

        emitted += 1;
        if (ends.type === 'count' && emitted > ends.count) {
            break;
        }

        if (dayInRange(candidate, rangeStart, rangeEnd)) {
            days.push(startOfDay(candidate));
        }

        if (emitted >= MAX_OCCURRENCES_PER_SERIES) {
            break;
        }
    }

    return days;
}

/**
 * Expands recurring events into dated instances for `[rangeStart, rangeEnd]`.
 * Non-recurring events are included once if their start falls in range.
 */
export function expandEventsForRange(
    events: CalendarEvent[],
    rangeStart: Date,
    rangeEnd: Date,
    weekStartsOn: 0 | 1 = 1,
): CalendarEvent[] {
    const out: CalendarEvent[] = [];

    for (const event of events) {
        const anchorStart = parseEventDateString(event.startsAt);
        const anchorEnd = parseEventDateString(event.endsAt);
        if (!anchorStart || !anchorEnd) {
            continue;
        }

        if (!event.recurrence) {
            const startsBeforeRangeEnd =
                anchorStart.getTime() <= endOfDay(rangeEnd).getTime();
            const endsAfterRangeStart =
                anchorEnd.getTime() >= startOfDay(rangeStart).getTime();
            if (startsBeforeRangeEnd && endsAfterRangeStart) {
                out.push({ ...event });
            }

            continue;
        }

        const { recurrence } = event;
        const interval = Math.max(1, recurrence.interval || 1);
        const ends = recurrence.ends;

        let occurrenceDays: Date[] = [];

        switch (recurrence.frequency) {
            case 'daily':
                occurrenceDays = collectDailyOccurrenceDays(
                    anchorStart,
                    interval,
                    rangeStart,
                    rangeEnd,
                    ends,
                );
                break;
            case 'weekly':
                occurrenceDays = collectWeeklyOccurrenceDays(
                    anchorStart,
                    interval,
                    recurrence.byWeekday ?? [anchorStart.getDay()],
                    weekStartsOn,
                    rangeStart,
                    rangeEnd,
                    ends,
                );
                break;
            case 'monthly':
                occurrenceDays = collectMonthlyOccurrenceDays(
                    anchorStart,
                    interval,
                    rangeStart,
                    rangeEnd,
                    ends,
                );
                break;
            case 'yearly':
                occurrenceDays = collectYearlyOccurrenceDays(
                    anchorStart,
                    interval,
                    rangeStart,
                    rangeEnd,
                    ends,
                );
                break;
            default:
                break;
        }

        const seen = new Set<string>();
        const exceptions = Array.isArray(event.recurrenceExceptions)
            ? event.recurrenceExceptions
            : [];

        function exceptionForDay(
            dayKey: string,
        ): CalendarEventRecurrenceException | null {
            const matched = exceptions.find(
                (exception) => exception.date === dayKey,
            );
            return matched ?? null;
        }

        for (const day of occurrenceDays) {
            const { start, end } = shiftStartPreserveDuration(
                anchorStart,
                anchorEnd,
                day,
            );
            const dayKey = [
                day.getFullYear(),
                String(day.getMonth() + 1).padStart(2, '0'),
                String(day.getDate()).padStart(2, '0'),
            ].join('-');

            if (seen.has(dayKey)) {
                continue;
            }

            seen.add(dayKey);
            const exception = exceptionForDay(dayKey);
            if (exception?.action === 'skip') {
                continue;
            }

            const id = `${event.id}::${dayKey}`;
            const occurrence: CalendarEvent = {
                ...event,
                id,
                seriesId: event.id,
                startsAt: event.allDay
                    ? `${dayKey} 00:00`
                    : formatEventTimestamp(start),
                endsAt: event.allDay
                    ? `${dayKey} 23:59`
                    : formatEventTimestamp(end),
            };

            if (exception?.action === 'override') {
                occurrence.title = exception.title ?? occurrence.title;
                occurrence.startsAt = exception.startsAt ?? occurrence.startsAt;
                occurrence.endsAt = exception.endsAt ?? occurrence.endsAt;
                occurrence.location = exception.location ?? occurrence.location;
                occurrence.details = exception.details ?? occurrence.details;
                occurrence.category = exception.category ?? occurrence.category;
                occurrence.categoryId =
                    exception.categoryId ?? occurrence.categoryId;
                occurrence.allDay = exception.allDay ?? occurrence.allDay;
            }

            out.push(occurrence);
        }
    }

    return out.sort((a, b) => a.startsAt.localeCompare(b.startsAt));
}

export function formatRecurrenceSummary(r: CalendarEventRecurrence): string {
    const interval = Math.max(1, r.interval || 1);
    const freq = r.frequency;

    let pattern = '';
    if (freq === 'daily') {
        pattern = interval === 1 ? 'Every day' : `Every ${interval} days`;
    } else if (freq === 'weekly') {
        const days = (r.byWeekday ?? [])
            .slice()
            .sort((a, b) => a - b)
            .map((d) => WEEKDAY_LABELS_SHORT[d])
            .join(', ');
        pattern =
            interval === 1
                ? `Weekly${days ? ` on ${days}` : ''}`
                : `Every ${interval} weeks${days ? ` on ${days}` : ''}`;
    } else if (freq === 'monthly') {
        pattern = interval === 1 ? 'Every month' : `Every ${interval} months`;
    } else {
        pattern = interval === 1 ? 'Every year' : `Every ${interval} years`;
    }

    let endPart = '';
    if (r.ends.type === 'never') {
        endPart = ' · Forever';
    } else if (r.ends.type === 'until') {
        endPart = ` · Until ${r.ends.date}`;
    } else {
        endPart = ` · ${r.ends.count} times`;
    }

    return `${pattern}${endPart}`;
}
