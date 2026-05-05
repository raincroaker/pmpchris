function parseIsoDate(iso: string): Date | null {
    const trimmed = iso.trim().slice(0, 10);
    if (trimmed.length !== 10) {
        return null;
    }

    const date = new Date(`${trimmed}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date;
}

/**
 * Human-readable tenure shown as years, months, and days.
 * Interval is inclusive of both endpoints to match server `tenure_days`.
 */
export function formatTenureDays(
    hireIso: string,
    separationIso?: string | null,
): string {
    const start = parseIsoDate(hireIso);
    const end = separationIso ? parseIsoDate(separationIso) : new Date();
    if (start === null || end === null) {
        return '—';
    }

    const startDate = new Date(
        start.getFullYear(),
        start.getMonth(),
        start.getDate(),
    );
    const endDate = new Date(end.getFullYear(), end.getMonth(), end.getDate());
    endDate.setDate(endDate.getDate() + 1);

    if (endDate < startDate) {
        return '0d';
    }

    let years = endDate.getFullYear() - startDate.getFullYear();
    let months = endDate.getMonth() - startDate.getMonth();
    let days = endDate.getDate() - startDate.getDate();

    if (days < 0) {
        months -= 1;
        const prevMonthLastDay = new Date(
            endDate.getFullYear(),
            endDate.getMonth(),
            0,
        ).getDate();
        days += prevMonthLastDay;
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    const parts: string[] = [];
    if (years > 0) {
        parts.push(`${years}y`);
    }
    if (months > 0) {
        parts.push(`${months}m`);
    }
    if (days > 0 || parts.length === 0) {
        parts.push(`${days}d`);
    }

    return parts.join(' ');
}
