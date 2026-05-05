import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';

/** Inclusive overlap for ISO calendar dates `YYYY-MM-DD`. */
export function leaveIntervalOverlapsFilter(
    rowStart: string,
    rowEnd: string,
    filterFrom: string,
    filterTo: string,
): boolean {
    return rowStart <= filterTo && rowEnd >= filterFrom;
}

/** `otDate` is `YYYY-MM-DD`. */
export function otDateInRange(
    otDate: string,
    filterFrom: string,
    filterTo: string,
): boolean {
    return otDate >= filterFrom && otDate <= filterTo;
}

/** Inclusive range on the calendar date of an approval / decision field. Null never matches. */
export function decidedAtInInclusiveRange(
    decidedAt: string | null,
    rangeFrom: string,
    rangeTo: string,
): boolean {
    if (decidedAt === null || decidedAt === '') {
        return false;
    }

    const d = decidedAt.slice(0, 10);

    return d >= rangeFrom && d <= rangeTo;
}

/** Format `YYYY-MM-DD` or ISO datetime using only the date portion (local display). */
export function formatIsoCalendarDate(iso: string): string {
    return formatCalendarTriggerFromIsoYmd(iso);
}
