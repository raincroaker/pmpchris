import type { DateValue } from '@internationalized/date';
import { toCalendarDate } from '@internationalized/date';

/**
 * Serializes an `@internationalized/date` calendar value to `Y-m-d` for Laravel/DB date columns.
 *
 * Do not use `value.toDate(tz).toISOString().slice(0, 10)` for date-only fields: `toISOString()`
 * is UTC and shifts the calendar day for timezones ahead of UTC (e.g. Asia/Manila).
 */
export function calendarDateValueToIsoYmd(value: DateValue): string {
    const cd = toCalendarDate(value);
    const m = String(cd.month).padStart(2, '0');
    const d = String(cd.day).padStart(2, '0');

    return `${cd.year}-${m}-${d}`;
}
