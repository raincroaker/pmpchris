import type { ShiftRule } from '@/pages/Attendance/attendanceRulesTypes';

/** Reference data (former UI seed); real data is loaded from `workScheduleTemplates` Inertia prop. */
export const workScheduleMocks: ShiftRule[] = [
    {
        id: 1,
        name: 'Weekday 8-5 split + OT',
        clock_pattern: 'split_sessions',
        segments: [
            {
                label: 'Session 1',
                time_in: '08:00',
                time_out: '12:00',
                is_overnight: false,
            },
            {
                label: 'Session 2',
                time_in: '13:00',
                time_out: '17:00',
                is_overnight: false,
            },
            {
                label: 'Overtime',
                time_in: '17:00',
                time_out: '19:00',
                is_overnight: false,
            },
        ],
        days: ['mon', 'tue', 'wed', 'thu', 'fri'],
        time_in: '08:00',
        time_out: '19:00',
        is_overnight: false,
        is_active: true,
        unpaid_break_minutes: 60,
        grace_late_arrival_minutes: 15,
        notes: 'Two regular sessions 08:00–17:00 (lunch gap unpaid); OT as a third session.',
    },
    {
        id: 2,
        name: 'Mon-Sat 8-5 split + OT',
        clock_pattern: 'split_sessions',
        segments: [
            {
                label: 'Session 1',
                time_in: '08:00',
                time_out: '12:00',
                is_overnight: false,
            },
            {
                label: 'Session 2',
                time_in: '13:00',
                time_out: '17:00',
                is_overnight: false,
            },
            {
                label: 'Overtime',
                time_in: '17:00',
                time_out: '19:00',
                is_overnight: false,
            },
        ],
        days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
        time_in: '08:00',
        time_out: '19:00',
        is_overnight: false,
        is_active: true,
        unpaid_break_minutes: 60,
        grace_late_arrival_minutes: 12,
        notes: 'Same session layout as weekday template; Saturday included.',
    },
];
