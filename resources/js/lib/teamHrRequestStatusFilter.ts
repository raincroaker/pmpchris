/** Status filter for Employee Leaves / Employee Overtime team tables (mock UI). */
export type TeamHrRequestStatusFilter = 'all' | 'approved' | 'rejected';

export const TEAM_HR_REQUEST_STATUS_LABELS: Record<
    TeamHrRequestStatusFilter,
    string
> = {
    all: 'All',
    approved: 'Approved',
    rejected: 'Rejected',
};
