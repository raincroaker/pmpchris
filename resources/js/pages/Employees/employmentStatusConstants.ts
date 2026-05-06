/** Mirrors {@see \App\Models\EmployeeEmployment::STATUSES} for client labels. */
export const EMPLOYMENT_STATUS_VALUES = [
    'active',
    'resigned',
    'terminated',
    'retired',
    'contract_ended',
] as const;

export type EmploymentStatusApi = (typeof EMPLOYMENT_STATUS_VALUES)[number];

/** Statuses allowed when recording separation (excludes Active). */
export const EMPLOYMENT_SEPARATION_STATUS_VALUES = [
    'resigned',
    'terminated',
    'retired',
    'contract_ended',
] as const;

export type EmploymentSeparationStatusApi =
    (typeof EMPLOYMENT_SEPARATION_STATUS_VALUES)[number];

export const EMPLOYMENT_STATUS_LABEL: Record<EmploymentStatusApi, string> = {
    active: 'Active',
    resigned: 'Resigned',
    terminated: 'Terminated',
    retired: 'Retired',
    contract_ended: 'Contract Ended',
};

export function employmentStatusBadgeClass(
    status: EmploymentStatusApi,
): string {
    const base = 'rounded-full border-transparent text-xs font-medium capitalize';
    switch (status) {
        case 'active':
            return `${base} bg-emerald-100 text-emerald-900 dark:bg-emerald-900/45 dark:text-emerald-100`;
        case 'resigned':
            return `${base} bg-slate-200 text-slate-900 dark:bg-slate-700/70 dark:text-slate-100`;
        case 'terminated':
            return `${base} bg-rose-100 text-rose-900 dark:bg-rose-900/45 dark:text-rose-100`;
        case 'retired':
            return `${base} bg-amber-100 text-amber-900 dark:bg-amber-900/45 dark:text-amber-100`;
        case 'contract_ended':
            return `${base} bg-orange-100 text-orange-900 dark:bg-orange-900/45 dark:text-orange-100`;
    }
}
