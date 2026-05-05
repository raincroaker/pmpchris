/** Mock-only Overtime Policy catalog types (UI prototype; no backend contract yet). */

export type OvertimePolicyContext =
    | 'ordinary_weekday'
    | 'rest_day'
    | 'regular_holiday'
    | 'special_holiday';

export interface OvertimePolicy {
    id: number;
    code: string;
    name: string;
    context: OvertimePolicyContext;
    /** Pay multiplier on the regular hourly rate (e.g. 1.25 = time-and-a-quarter). */
    rateMultiplier: number;
    /** Regular hours in a workday before hours count as overtime. */
    dailyThresholdHours: number;
    /** Optional max OT hours per day. */
    dailyCapHours: number | null;
    /** Optional max OT hours per week. */
    weeklyCapHours: number | null;
    requiresApproval: boolean;
    /** Minimum hours before start of OT that filing is required; null = none. */
    minimumLeadTimeHours: number | null;
    isActive: boolean;
    notes: string | null;
}

export type OvertimePolicyDraft = Omit<OvertimePolicy, 'id'> & {
    id?: number;
};
