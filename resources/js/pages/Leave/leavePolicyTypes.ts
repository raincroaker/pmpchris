/** Mock-only Leave Policy catalog types (UI prototype; no backend contract yet). */

export type LeavePolicyUnit = 'days' | 'hours';

export type LeavePolicyAccrualCadence = 'monthly' | 'pay_period';

export interface LeavePolicy {
    id: number;
    code: string;
    name: string;
    unit: LeavePolicyUnit;
    /** Annual entitlement in days or hours (see `unit`). */
    annualEntitlement: number;
    /** When false, full annual amount is granted per policy cycle (mock: described as annual grant). */
    useAccrual: boolean;
    /** Required when `useAccrual` is true. */
    accrualCadence: LeavePolicyAccrualCadence | null;
    /** Amount earned each period when accruing (same unit as annual entitlement / year). */
    accrualPerPeriod: number | null;
    maxBalance: number | null;
    carryoverAllowed: boolean;
    carryoverCap: number | null;
    paid: boolean;
    requiresApproval: boolean;
    /** Minimum months of service before employees may use this leave; null = none. */
    appliesAfterMonths: number | null;
    isActive: boolean;
    notes: string | null;
}

export type LeavePolicyDraft = Omit<LeavePolicy, 'id'> & {
    id?: number;
};
