/** Options for calendar popover triggers (e.g. "Jan 2, 2026" in English locales). */
export const CALENDAR_TRIGGER_DATE_FORMAT_OPTIONS: Intl.DateTimeFormatOptions =
    {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    };

/**
 * Format a `Date` for display on a closed calendar trigger.
 */
export function formatCalendarTriggerFromDate(
    date: Date,
    locales?: Intl.LocalesArgument,
): string {
    return date.toLocaleDateString(
        locales,
        CALENDAR_TRIGGER_DATE_FORMAT_OPTIONS,
    );
}

/**
 * Format a calendar `YYYY-MM-DD` (or ISO string) for trigger labels; uses noon local to limit TZ edge cases.
 */
export function formatCalendarTriggerFromIsoYmd(
    iso: string,
    locales?: Intl.LocalesArgument,
): string {
    const datePart = iso.trim().slice(0, 10);
    if (datePart.length < 10) {
        return '';
    }

    return new Date(`${datePart}T12:00:00`).toLocaleDateString(
        locales,
        CALENDAR_TRIGGER_DATE_FORMAT_OPTIONS,
    );
}
