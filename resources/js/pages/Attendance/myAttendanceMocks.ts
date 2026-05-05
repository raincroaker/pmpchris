import { isoCalendarAddDays, isoTodayLocal } from '@/lib/calendarMonthRange';
import { hrisAugmentAttendanceRowWithDemoDirectory } from '@/lib/hrisDemoDirectoryFields';
import type { TeamAttendanceRow } from '@/pages/Attendance/teamAttendanceTypes';
import { deriveTeamAttendancePunctuality } from '@/pages/Attendance/teamAttendanceUi';

type TeamAttendanceRowSeed = Omit<
    TeamAttendanceRow,
    'punctuality' | 'created_at' | 'created_by' | 'ingest_key' | 'work_schedule_template_id'
> & {
    created_at?: string;
    created_by?: string | null;
    ingest_key?: string | null;
};

type TeamAttendanceDaytimeRowSeed = Omit<
    TeamAttendanceRow,
    | 'is_overnight_schedule'
    | 'punctuality'
    | 'created_at'
    | 'created_by'
    | 'ingest_key'
    | 'work_schedule_template_id'
> & {
    created_at?: string;
    created_by?: string | null;
    ingest_key?: string | null;
};

const MY_MIXED_DAY_OFFSETS = [0, -1, -3, 0, -2, -1] as const;

function anchorMyRowsToToday(
    rows: readonly TeamAttendanceRowSeed[],
    offsets: readonly number[],
): TeamAttendanceRowSeed[] {
    const today = isoTodayLocal();

    return rows.map((row, i) => {
        const wd = isoCalendarAddDays(today, offsets[i] ?? 0);
        const noonIso = `${wd}T12:00:00.000Z`;

        return {
            ...row,
            work_date: wd,
            created_at: noonIso,
            updated_at: noonIso,
            created_by: row.created_by ?? null,
        };
    });
}

function withDaytimeScheduleFlag(
    rows: readonly TeamAttendanceDaytimeRowSeed[],
): TeamAttendanceRowSeed[] {
    return rows.map((r) => ({
        ...r,
        is_overnight_schedule: false,
    }));
}

/** Same persona as My overtime mock. */
export const MY_ATTENDANCE_MOCK_PERSONA = {
    display_name: 'Jordan Cruz',
    id_number: 'PMPC-10177',
    avatar_url: null as string | null,
} as const;

const e = MY_ATTENDANCE_MOCK_PERSONA;

const splitMine: TeamAttendanceDaytimeRowSeed[] = [
    {
        id: 98201,
        employee: { ...e },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        placement_unit_type_color: '#0369a1',
        placement_is_primary: true,
        placement_unit_type: 'Branch',
        work_date: '2026-05-09',
        attendance_id: 'AC-98201',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'Split — morning / afternoon',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '08:30',
                scheduled_out: '12:00',
                actual_in: '08:28',
                actual_out: '12:02',
            },
            {
                label: 'Session 2',
                scheduled_in: '13:00',
                scheduled_out: '17:00',
                actual_in: '13:03',
                actual_out: '17:05',
            },
        ],
        net_hours: 8.55,
        variance_label: '',
        status: 'complete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-09',
        updated_by: 'Biometric ingest (mock)',
    },
    {
        id: 98202,
        employee: { ...e },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        work_date: '2026-05-08',
        attendance_id: 'AC-JC-582',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'Split — morning / afternoon',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '08:30',
                scheduled_out: '12:00',
                actual_in: '08:45',
                actual_out: '11:58',
            },
            {
                label: 'Session 2',
                scheduled_in: '13:00',
                scheduled_out: '17:00',
                actual_in: '13:00',
                actual_out: '17:12',
            },
        ],
        net_hours: 8.38,
        variance_label: '',
        status: 'complete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-08',
        updated_by: 'Biometric ingest (mock)',
    },
    {
        id: 98203,
        employee: { ...e },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        work_date: '2026-05-06',
        attendance_id: 'AC-98203',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'Split — morning / afternoon',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '08:30',
                scheduled_out: '12:00',
                actual_in: '08:34',
                actual_out: null,
            },
            {
                label: 'Session 2',
                scheduled_in: '13:00',
                scheduled_out: '17:00',
                actual_in: null,
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'ongoing',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-06',
        updated_by: 'System',
    },
];

const simpleMine: TeamAttendanceDaytimeRowSeed[] = [
    {
        id: 98301,
        employee: { ...e },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        work_date: '2026-05-09',
        attendance_id: 'AC-98301',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '08:30',
                scheduled_out: '17:00',
                actual_in: '08:28',
                actual_out: '17:06',
            },
        ],
        net_hours: 8.6,
        variance_label: '',
        status: 'complete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-09',
        updated_by: 'Biometric ingest (mock)',
    },
    {
        id: 98302,
        employee: { ...e },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        placement_unit_type_color: '#9333ea',
        placement_is_primary: false,
        placement_unit_type: 'Temporary assignment',
        work_date: '2026-05-07',
        attendance_id: 'AC-98302',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '08:30',
                scheduled_out: '17:00',
                actual_in: '08:35',
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'ongoing',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-07',
        updated_by: 'System',
    },
];

const overnightMine: TeamAttendanceRowSeed = {
    id: 98401,
    employee: { ...e },
    unit_filter_value: 'u-tagum',
    unit_name: 'Tagum Branch',
    unit_code: 'TAG',
    placement_unit_type_color: '#0d9488',
    placement_is_primary: true,
    placement_unit_type: 'Branch',
    work_date: '2026-05-05',
    attendance_id: 'AC-JC-N1',
    clock_pattern: 'single_pair',
    is_overnight_schedule: true,
    work_schedule_name: 'After-hours support',
    segments: [
        {
            label: 'Shift',
            scheduled_in: '22:00',
            scheduled_out: '06:00',
            actual_in: '21:55',
            actual_out: '06:02',
        },
    ],
    net_hours: 8.12,
    variance_label: '',
    status: 'complete',
    original_source: 'device',
    last_modified_source: 'device',
    updated_at: '2026-05-05',
    updated_by: 'Biometric ingest (mock)',
};

/** Mixed schedule styles including one overnight sample. */
export function seedMyAttendanceRows(): TeamAttendanceRow[] {
    const combined: TeamAttendanceRowSeed[] = [
        ...withDaytimeScheduleFlag(splitMine),
        ...withDaytimeScheduleFlag(simpleMine),
        overnightMine,
    ];

    return anchorMyRowsToToday(combined, MY_MIXED_DAY_OFFSETS).map(
        (r): TeamAttendanceRow => {
            const augmented = hrisAugmentAttendanceRowWithDemoDirectory(r);
            return {
                ...augmented,
                work_schedule_template_id: 1,
                punctuality: deriveTeamAttendancePunctuality(r.segments),
                ingest_key: r.ingest_key ?? null,
                created_at:
                    augmented.created_at ?? `${augmented.work_date}T12:00:00.000Z`,
                created_by: augmented.created_by ?? null,
            };
        },
    );
}
