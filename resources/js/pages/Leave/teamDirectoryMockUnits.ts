/** Fallback mock unit rows when the branch API returns none (Leave / Overtime / Attendance team toolbars). */
export type TeamDirectoryUnitOption = {
    value: string;
    label: string;
    code: string | null;
};

/** Branches/units only; pages prepend `{ value: 'all', label: 'All units' }` separately. */
export const TEAM_DIRECTORY_UNIT_FILTER_OPTIONS: TeamDirectoryUnitOption[] = [
    {
        value: 'u-tagum',
        label: 'Tagum Branch',
        code: 'TAG',
    },
    {
        value: 'u-panabo',
        label: 'Panabo Branch',
        code: 'PNB',
    },
    {
        value: 'u-section-a',
        label: 'Panabo — Section A',
        code: 'PNB-A',
    },
    {
        value: 'u-hq',
        label: 'Head Office',
        code: 'HQ',
    },
];
