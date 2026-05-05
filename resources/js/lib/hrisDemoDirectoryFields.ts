import type { EmployeeIndexPosition } from '@/pages/Employees/employeeIndexTypes';

/** Mock-only fields aligned with Employee schedule placement badges until API persists them. */

export type HrisDemoDirectoryFields = {
    unit_type: string;
    unit_type_color: string | null;
    unit_is_primary: boolean;
    positions: EmployeeIndexPosition[];
};

const DEMO_POS_JORDAN: EmployeeIndexPosition[] = [
    {
        id: 10101,
        code: 'SLS-RP',
        title: 'Sales Representative',
        is_primary: true,
    },
    {
        id: 10102,
        code: 'KEY-ACC',
        title: 'Key Accounts Associate',
        is_primary: false,
    },
];

const DEMO_POS_ANA: EmployeeIndexPosition[] = [
    { id: 10201, code: 'INV-CLK', title: 'Inventory Clerk', is_primary: true },
];

const DEMO_POS_MIRA: EmployeeIndexPosition[] = [
    { id: 10301, code: 'TEAM-LD', title: 'Team Lead', is_primary: true },
    { id: 10302, code: 'SLS-SP', title: 'Senior Processor', is_primary: false },
];

const DEMO_POS_LEO: EmployeeIndexPosition[] = [
    {
        id: 10401,
        code: 'FIN-ANA',
        title: 'Financial Analyst',
        is_primary: true,
    },
];

const DEMO_POS_SOFIA: EmployeeIndexPosition[] = [
    { id: 10501, code: 'HR-ASST', title: 'HR Assistant', is_primary: true },
];

const DEMO_POS_ETHAN: EmployeeIndexPosition[] = [
    { id: 10601, code: 'WH-OP', title: 'Warehouse Operator', is_primary: true },
];

const DEMO_POS_PAULO: EmployeeIndexPosition[] = [
    {
        id: 10701,
        code: 'OPS-SPV',
        title: 'Operations Supervisor',
        is_primary: true,
    },
];

const DEMO_POS_INA: EmployeeIndexPosition[] = [
    { id: 10801, code: 'PLN-ANA', title: 'Planning Analyst', is_primary: true },
];

const DEMO_POS_CARLOS: EmployeeIndexPosition[] = [
    {
        id: 10901,
        code: 'DIR-OPS',
        title: 'Director of Operations',
        is_primary: true,
    },
];

const DEMO_POS_BEA: EmployeeIndexPosition[] = [
    { id: 11001, code: 'CSH-CLK', title: 'Cashroom Clerk', is_primary: true },
];

const POSITIONS_BY_EMPLOYEE_ID: Record<string, EmployeeIndexPosition[]> = {
    'PMPC-10177': DEMO_POS_JORDAN,
    'PMPC-10492': DEMO_POS_ANA,
    'PMPC-10801': DEMO_POS_MIRA,
    'PMPC-10002': DEMO_POS_LEO,
    'PMPC-11220': DEMO_POS_SOFIA,
    'PMPC-11544': DEMO_POS_ETHAN,
    'PMPC-10331': DEMO_POS_PAULO,
    'PMPC-11998': DEMO_POS_INA,
    'PMPC-10765': DEMO_POS_CARLOS,
    'PMPC-12001': DEMO_POS_BEA,
};

const UNIT_SNAPSHOT: Record<
    string,
    Pick<
        HrisDemoDirectoryFields,
        'unit_type' | 'unit_type_color' | 'unit_is_primary'
    >
> = {
    'u-tagum': {
        unit_type: 'Branch office',
        unit_type_color: '#22c55e',
        unit_is_primary: true,
    },
    'u-panabo': {
        unit_type: 'Branch office',
        unit_type_color: '#0ea5e9',
        unit_is_primary: true,
    },
    'u-section-a': {
        unit_type: 'Section',
        unit_type_color: '#a855f7',
        unit_is_primary: false,
    },
    'u-hq': {
        unit_type: 'Head office',
        unit_type_color: '#ea580c',
        unit_is_primary: true,
    },
};

const FALLBACK_POSITION: EmployeeIndexPosition[] = [
    { id: 90001, code: 'GEN', title: 'General assignment', is_primary: true },
];

/**
 * Derive mock directory badges for Leave/Overtime team rows keyed by toolbar unit + employee ID.
 */
export function hrisDemoDirectoryFieldsForTeamRow(input: {
    unit_filter_value: string;
    employee_id_number: string;
}): HrisDemoDirectoryFields {
    const base =
        UNIT_SNAPSHOT[input.unit_filter_value] ??
        ({
            unit_type: 'Organizational unit',
            unit_type_color: null,
            unit_is_primary: true,
        } satisfies Pick<
            HrisDemoDirectoryFields,
            'unit_type' | 'unit_type_color' | 'unit_is_primary'
        >);

    const positions =
        POSITIONS_BY_EMPLOYEE_ID[input.employee_id_number] ?? FALLBACK_POSITION;

    return {
        ...base,
        positions,
    };
}

/** Overlay demo badge fields onto a leave/overtime-style row without persisting duplicates in seed literals. */
export function hrisAugmentTeamHrRowWithDemoDirectory<
    T extends { unit_filter_value: string; employee: { id_number: string } },
>(row: T): T & HrisDemoDirectoryFields {
    return {
        ...row,
        ...hrisDemoDirectoryFieldsForTeamRow({
            unit_filter_value: row.unit_filter_value,
            employee_id_number: row.employee.id_number,
        }),
    };
}

/** Attendance row naming for placement chip + job positions (mock UI). */
export type HrisAttendanceDirectoryOverlayFields = {
    placement_unit_type: string;
    placement_unit_type_color: string | null;
    placement_is_primary: boolean;
    positions: EmployeeIndexPosition[];
};

export function hrisAttendanceDirectoryDemoFields(input: {
    unit_filter_value: string;
    employee_id_number: string;
}): HrisAttendanceDirectoryOverlayFields {
    const d = hrisDemoDirectoryFieldsForTeamRow(input);

    return {
        placement_unit_type: d.unit_type,
        placement_unit_type_color: d.unit_type_color,
        placement_is_primary: d.unit_is_primary,
        positions: d.positions,
    };
}

export function hrisAugmentAttendanceRowWithDemoDirectory<
    T extends { unit_filter_value: string; employee: { id_number: string } },
>(row: T): T & HrisAttendanceDirectoryOverlayFields {
    return {
        ...row,
        ...hrisAttendanceDirectoryDemoFields({
            unit_filter_value: row.unit_filter_value,
            employee_id_number: row.employee.id_number,
        }),
    };
}
