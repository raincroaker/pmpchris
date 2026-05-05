/** Today in the local calendar as `YYYY-MM-DD`. */
export function isoTodayLocal(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/** Shift a local calendar ISO date by `deltaDays` (positive = future). */
export function isoCalendarAddDays(isoYmd: string, deltaDays: number): string {
    const parts = isoYmd.split('-').map((s) => Number.parseInt(s, 10));
    const base = new Date(parts[0], parts[1] - 1, parts[2]);
    base.setDate(base.getDate() + deltaDays);
    const y = base.getFullYear();
    const m = String(base.getMonth() + 1).padStart(2, '0');
    const day = String(base.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

/** Local-calendar bounds as ISO `YYYY-MM-DD` for default filter ranges (mock UI). */
export function isoFirstDayOfMonth(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');

    return `${y}-${m}-01`;
}

export function isoLastDayOfMonth(d: Date): string {
    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const y = last.getFullYear();
    const m = String(last.getMonth() + 1).padStart(2, '0');
    const day = String(last.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}
