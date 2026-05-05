import type { OvertimePolicyContext } from '@/pages/Overtime/overtimePolicyTypes';

/** Human labels for `OvertimePolicyContext` (Policies + team views). */
export const OVERTIME_CONTEXT_LABELS: Record<OvertimePolicyContext, string> = {
    ordinary_weekday: 'Ordinary weekday',
    rest_day: 'Rest day',
    regular_holiday: 'Regular holiday',
    special_holiday: 'Special holiday',
};

/** Shared display helper for OT rate multipliers (Policies + team views). */
export function formatOvertimeRateMultiplier(m: number): string {
    const rounded = Math.round(m * 100) / 100;

    return `${rounded}×`;
}
