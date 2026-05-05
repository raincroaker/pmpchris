/** Client-side filters for Work Schedules table (`Shifts.vue`). */
export type ShiftsSchedulePatternFilter =
    | 'all'
    /** `single_pair` and not overnight */
    | 'single_day'
    /** `single_pair` and overnight */
    | 'single_overnight'
    | 'split_sessions';

export type ShiftsStatusFilter = 'all' | 'active' | 'inactive';
