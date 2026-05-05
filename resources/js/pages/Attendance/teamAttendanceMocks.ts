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

/** Anchor mock `work_date` / `updated_at` to today so default “today” filters show samples. */
const TEAM_MIXED_DAY_OFFSETS = [0, 0, -1, -3, 0, 0, -1, -3, -2, -1] as const;

function anchorTeamRowsToToday(
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

const splitSeed: TeamAttendanceDaytimeRowSeed[] = [
    {
        id: 8801,
        employee: {
            display_name: 'Jordan Cruz',
            id_number: 'PMPC-10177',
            avatar_url: null,
        },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        placement_unit_type_color: '#0369a1',
        placement_is_primary: true,
        placement_unit_type: 'Branch',
        work_date: '2026-05-09',
        attendance_id: 'BIO-MOCK-08801',
        ingest_key: 'ATD-MOCK-08801',
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
        last_modified_source: 'manual',
        updated_at: '2026-05-09',
        updated_by: 'HR Manager (mock)',
    },
    {
        id: 8802,
        employee: {
            display_name: 'Ana Morales',
            id_number: 'PMPC-10492',
            avatar_url: null,
        },
        unit_filter_value: 'u-panabo',
        unit_name: 'Panabo Branch',
        unit_code: 'PNB',
        placement_unit_type_color: '#7c3aed',
        placement_is_primary: false,
        placement_unit_type: 'Branch',
        work_date: '2026-05-09',
        attendance_id: 'AC-88421',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'Split — morning / afternoon',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '08:30',
                scheduled_out: '12:00',
                actual_in: '08:41',
                actual_out: '12:00',
            },
            {
                label: 'Session 2',
                scheduled_in: '13:00',
                scheduled_out: '17:00',
                actual_in: '13:00',
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'ongoing',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-09',
        updated_by: 'System',
    },
    {
        id: 8803,
        employee: {
            display_name: 'Leo Villarin',
            id_number: 'PMPC-10002',
            avatar_url: null,
        },
        unit_filter_value: 'u-hq',
        unit_name: 'Head Office',
        unit_code: 'HQ',
        work_date: '2026-05-08',
        attendance_id: 'AC-08803',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'HQ split day',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '09:00',
                scheduled_out: '13:00',
                actual_in: null,
                actual_out: null,
            },
            {
                label: 'Session 2',
                scheduled_in: '14:00',
                scheduled_out: '18:00',
                actual_in: null,
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'incomplete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-08',
        updated_by: 'System',
    },
    {
        id: 8804,
        employee: {
            display_name: 'Sofia Delgado',
            id_number: 'PMPC-11220',
            avatar_url: null,
        },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        work_date: '2026-05-07',
        attendance_id: 'AC-66102',
        clock_pattern: 'split_sessions',
        work_schedule_name: 'Weekday 8-5 split + OT',
        segments: [
            {
                label: 'Session 1',
                scheduled_in: '08:00',
                scheduled_out: '12:00',
                actual_in: '07:58',
                actual_out: '12:02',
            },
            {
                label: 'Session 2',
                scheduled_in: '13:00',
                scheduled_out: '17:00',
                actual_in: '12:58',
                actual_out: '17:04',
            },
            {
                label: 'Overtime',
                scheduled_in: '17:00',
                scheduled_out: '19:00',
                actual_in: '17:05',
                actual_out: '19:06',
            },
        ],
        net_hours: 10,
        variance_label: '',
        status: 'complete',
        original_source: 'import',
        last_modified_source: 'import',
        updated_at: '2026-05-07',
        updated_by: 'Payroll CSV (mock)',
    },
];

const simpleSeed: TeamAttendanceDaytimeRowSeed[] = [
    {
        id: 8901,
        employee: {
            display_name: 'Ana Morales',
            id_number: 'PMPC-10492',
            avatar_url: null,
        },
        unit_filter_value: 'u-panabo',
        unit_name: 'Panabo Branch',
        unit_code: 'PNB',
        work_date: '2026-05-09',
        attendance_id: 'AC-08901',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '08:30',
                scheduled_out: '17:00',
                actual_in: '08:34',
                actual_out: '17:06',
            },
        ],
        net_hours: 8.53,
        variance_label: '',
        status: 'complete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-09',
        updated_by: 'Biometric ingest (mock)',
    },
    {
        id: 8902,
        employee: {
            display_name: 'Noah Ramos',
            id_number: 'PMPC-19001',
            avatar_url: null,
        },
        unit_filter_value: 'u-hq',
        unit_name: 'Head Office',
        unit_code: 'HQ',
        work_date: '2026-05-09',
        attendance_id: 'AC-08902',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '09:00',
                scheduled_out: '18:00',
                actual_in: '09:05',
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'ongoing',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-09',
        updated_by: 'System',
    },
    {
        id: 8903,
        employee: {
            display_name: 'Mira Fernandez',
            id_number: 'PMPC-10801',
            avatar_url: null,
        },
        unit_filter_value: 'u-section-a',
        unit_name: 'Panabo — Section A',
        unit_code: 'PNB-A',
        work_date: '2026-05-08',
        attendance_id: 'AC-08903',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '08:30',
                scheduled_out: '17:00',
                actual_in: null,
                actual_out: null,
            },
        ],
        net_hours: 0,
        variance_label: '',
        status: 'incomplete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-08',
        updated_by: 'System',
    },
    {
        id: 8904,
        employee: {
            display_name: 'Jordan Cruz',
            id_number: 'PMPC-10177',
            avatar_url: null,
        },
        unit_filter_value: 'u-tagum',
        unit_name: 'Tagum Branch',
        unit_code: 'TAG',
        work_date: '2026-05-07',
        attendance_id: 'AC-08904',
        clock_pattern: 'single_pair',
        work_schedule_name: 'Office — weekday default',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '08:30',
                scheduled_out: '17:00',
                actual_in: '08:31',
                actual_out: '16:52',
            },
        ],
        net_hours: 8.35,
        variance_label: '',
        status: 'ongoing',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-12',
        updated_by: 'System',
    },
];

const overnightSeed: TeamAttendanceRowSeed[] = [
    {
        id: 8921,
        employee: {
            display_name: 'Noah Ramos',
            id_number: 'PMPC-19001',
            avatar_url: null,
        },
        unit_filter_value: 'u-hq',
        unit_name: 'Head Office',
        unit_code: 'HQ',
        placement_unit_type_color: '#047857',
        placement_is_primary: true,
        placement_unit_type: 'Regional office',
        work_date: '2026-05-08',
        attendance_id: 'AC-NR-901',
        clock_pattern: 'single_pair',
        is_overnight_schedule: true,
        work_schedule_name: 'Security — night shift',
        segments: [
            {
                label: 'Shift',
                scheduled_in: '22:00',
                scheduled_out: '06:00',
                actual_in: '21:58',
                actual_out: '06:04',
            },
        ],
        net_hours: 8.1,
        variance_label: '',
        status: 'complete',
        original_source: 'device',
        last_modified_source: 'device',
        updated_at: '2026-05-08',
        updated_by: 'Biometric ingest (mock)',
    },
    {
        id: 8822,
        employee: {
            display_name: 'Leo Villarin',
            id_number: 'PMPC-10002',
            avatar_url: null,
        },
        unit_filter_value: 'u-hq',
        unit_name: 'Head Office',
        unit_code: 'HQ',
        placement_unit_type_color: '#b45309',
        placement_is_primary: true,
        placement_unit_type: 'Regional office',
        work_date: '2026-05-07',
        attendance_id: 'AC-08822',
        clock_pattern: 'split_sessions',
        is_overnight_schedule: true,
        work_schedule_name: 'Ops — split night',
        segments: [
            {
                label: 'Block 1',
                scheduled_in: '22:00',
                scheduled_out: '02:00',
                actual_in: '22:02',
                actual_out: '02:01',
            },
            {
                label: 'Block 2',
                scheduled_in: '03:00',
                scheduled_out: '07:00',
                actual_in: '03:00',
                actual_out: '07:05',
            },
        ],
        net_hours: 8.05,
        variance_label: '',
        status: 'complete',
        original_source: 'manual',
        last_modified_source: 'manual',
        created_by: 'HR entry (mock)',
        updated_at: '2026-05-07',
        updated_by: 'HR entry (mock)',
    },
];

/** Mixed simple / split / overnight samples; dates anchored to today. */
export function seedTeamAttendanceRows(): TeamAttendanceRow[] {
    const combined: TeamAttendanceRowSeed[] = [
        ...withDaytimeScheduleFlag(splitSeed),
        ...withDaytimeScheduleFlag(simpleSeed),
        ...overnightSeed,
    ];

    return anchorTeamRowsToToday(combined, TEAM_MIXED_DAY_OFFSETS).map(
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
