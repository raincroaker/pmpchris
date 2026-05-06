/**
 * Shared display types and mock builders for employee profile UIs.
 * Replace mocks with Inertia props when backend wiring is ready.
 */

import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';

/** HR affiliation placements (catalog / branch roots) — excludes org-chart unit placements. */
export type EmployeeProfileAffiliationHistoryRow = {
    id: number;
    root_unit_id: number | null;
    unit: string;
    unit_type: string;
    code: string | null;
    start_date: string;
    end_date: string | null;
};

/** Org chart {@link EmployeeAssignment} spans for current employment period. */
export type EmployeeProfileUnitAssignmentRow = {
    id: number;
    unit: string;
    unit_type: string;
    code: string | null;
    start_date: string;
    end_date: string | null;
};

export type EmployeeProfilePositionRow = {
    title: string;
    code: string;
    is_primary: boolean;
    start_date: string;
    end_date: string | null;
};

export type EmployeeProfileEmploymentRow = {
    label: string;
    value: string;
};

export type EmployeeProfileAttendanceSummary = {
    label: string;
    value: string;
    hint?: string;
};

/** Hero strip metrics — maps to employment / placement summaries. */
export type EmployeeProfileStatTile = {
    label: string;
    value: string;
    hint?: string;
};

/** Mirrors `employee_contacts` categories (display-ready strings). */
export type EmployeeProfileContactRow = {
    id?: number;
    category: 'personal' | 'emergency';
    channel_label: string;
    contact_number: string;
    email: string | null;
    contact_person: string | null;
    relationship: string | null;
    is_primary: boolean;
};

/** Mirrors `employee_addresses` — `lines` are preformatted for UI. */
export type EmployeeProfileAddressBlock = {
    type: 'current' | 'permanent';
    lines: string[];
    is_primary: boolean;
    /** When present (from API), editors round-trip PSGC + lines without parsing `lines`. */
    address_line_1?: string;
    address_line_2?: string | null;
    barangay?: string;
    barangay_code?: string | null;
    city?: string;
    city_code?: string | null;
    province?: string;
    province_code?: string | null;
    zip_code?: string;
    country?: string;
};

/** Mirrors core `employees` demographic fields. */
export type EmployeeProfileDemographics = {
    /** ISO `YYYY-MM-DD` for calendars and API (null if unknown). */
    birthdate_iso: string | null;
    /** Short English label for read-only profile (same style as calendar trigger). */
    birthdate_display: string;
    sex: string;
    civil_status: string;
    nationality: string;
    religion: string | null;
    /** When `religion === 'Other'`, free-text denomination. */
    religion_other: string | null;
};

export function formatEmployeeDisplayName(
    first_name: string,
    middle_name: string,
    last_name: string,
): string {
    return [first_name, middle_name, last_name]
        .map((part) => part.trim())
        .filter((part) => part !== '')
        .join(' ');
}

/** Lowercase keys matching {@link WorkScheduleTemplate} `days` (Mon–Sun chips on profile). */
export type ScheduleDayToken =
    | 'mon'
    | 'tue'
    | 'wed'
    | 'thu'
    | 'fri'
    | 'sat'
    | 'sun';

export type EmployeeProfileDisplay = {
    employee_id: number;
    display_name: string;
    /** Given / first name (edit forms; keep in sync with `display_name` on save). */
    first_name: string;
    middle_name: string;
    last_name: string;
    legal_name_line: string;
    id_number: string;
    avatar_url: string | null;
    attendance_id: string | null;
    status_label: string;
    branch_label: string;
    org_scope_label: string;
    schedule_label: string;
    schedule_template_code: string | null;
    /** From work schedule template `days` (serialized keys). */
    schedule_day_tokens: ScheduleDayToken[];
    /** Canonical Mon→Sun comma list from template `days` (PHP); single source for profile working-days line. */
    schedule_working_days: string;
    stats: EmployeeProfileStatTile[];
    demographics: EmployeeProfileDemographics;
    personal_contacts: EmployeeProfileContactRow[];
    emergency_contacts: EmployeeProfileContactRow[];
    current_address: EmployeeProfileAddressBlock | null;
    permanent_address: EmployeeProfileAddressBlock | null;
    employment_rows: EmployeeProfileEmploymentRow[];
    affiliation_history: EmployeeProfileAffiliationHistoryRow[];
    unit_assignment_history: EmployeeProfileUnitAssignmentRow[];
    positions: EmployeeProfilePositionRow[];
    attendance_summary: EmployeeProfileAttendanceSummary[];
};

export function mockAboutMeProfile(): EmployeeProfileDisplay {
    const first_name = 'Alex';
    const middle_name = 'Quinn';
    const last_name = 'Morgan';

    return {
        employee_id: 90_001,
        display_name: formatEmployeeDisplayName(
            first_name,
            middle_name,
            last_name,
        ),
        first_name,
        middle_name,
        last_name,
        legal_name_line: 'Alexandra Quinn Morgan',
        id_number: 'EMP-2024-0148',
        avatar_url: null,
        attendance_id: 'ATT-881204',
        status_label: 'Active',
        branch_label: 'Metro Operations',
        org_scope_label: 'Branch-scoped',
        schedule_label: 'Standard weekday · Mon–Sat · 08:00–17:00',
        schedule_template_code: 'STD-WD-08',
        schedule_day_tokens: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
        schedule_working_days: 'Mon, Tue, Wed, Thu, Fri, Sat',
        stats: [
            { label: 'Hire date', value: 'Mar 4, 2022' },
            {
                label: 'Tenure',
                value: '3y 2mo',
                hint: 'Approximate from hire date',
            },
            {
                label: 'Primary position',
                value: 'Lead Analyst',
                hint: 'ANL-L3',
            },
            {
                label: 'Primary unit',
                value: 'Customer Success Pod',
                hint: 'Current assignment',
            },
        ],
        demographics: {
            birthdate_iso: '1994-04-12',
            birthdate_display: formatCalendarTriggerFromIsoYmd('1994-04-12'),
            sex: 'Female',
            civil_status: 'Single',
            nationality: 'Filipino',
            religion: 'Catholic',
            religion_other: null,
        },
        personal_contacts: [
            {
                id: 1,
                category: 'personal',
                channel_label: 'Mobile',
                contact_number: '+63 917 000 4488',
                email: 'alex.morgan@company.example',
                contact_person: null,
                relationship: null,
                is_primary: true,
            },
            {
                id: 2,
                category: 'personal',
                channel_label: 'Work',
                contact_number: '+63 2 8888 0100',
                email: 'amorgan@internal.example',
                contact_person: null,
                relationship: null,
                is_primary: false,
            },
            {
                id: 3,
                category: 'personal',
                channel_label: 'Home',
                contact_number: '+63 2 8712 4456',
                email: null,
                contact_person: null,
                relationship: null,
                is_primary: false,
            },
        ],
        emergency_contacts: [
            {
                id: 11,
                category: 'emergency',
                channel_label: 'Mobile',
                contact_number: '+63 918 111 2299',
                email: null,
                contact_person: 'Jordan Morgan',
                relationship: 'Sibling',
                is_primary: true,
            },
            {
                id: 12,
                category: 'emergency',
                channel_label: 'Mobile',
                contact_number: '+63 919 882 4421',
                email: 'pat.morgan@example.com',
                contact_person: 'Patricia Morgan',
                relationship: 'Parent',
                is_primary: false,
            },
        ],
        current_address: {
            type: 'current',
            is_primary: true,
            lines: [
                'Unit 1204, Harbor View Residences',
                '123 Bay Boulevard, Brgy. San Antonio',
                'Pasig City 1600, Metro Manila',
                'Philippines',
            ],
        },
        permanent_address: {
            type: 'permanent',
            is_primary: false,
            lines: [
                '45 Rizal Street, Brgy. Poblacion',
                'Los Baños 4030, Laguna',
                'Philippines',
            ],
        },
        employment_rows: [
            { label: 'Start date', value: 'March 4, 2022' },
            { label: 'Separation date', value: '—' },
            { label: 'Status', value: 'Active' },
        ],
        affiliation_history: [
            {
                id: 1,
                root_unit_id: 10,
                unit: 'Customer Success Pod',
                unit_type: 'Team',
                code: 'CS-POD-2',
                start_date: 'Jan 15, 2025',
                end_date: null,
            },
            {
                id: 2,
                root_unit_id: 11,
                unit: 'Support Desk West',
                unit_type: 'Department',
                code: 'SUP-W',
                start_date: 'Mar 4, 2022',
                end_date: 'Jan 14, 2025',
            },
            {
                id: 3,
                root_unit_id: 12,
                unit: 'Operations Hub',
                unit_type: 'Division',
                code: 'OPS-HQ',
                start_date: 'Jun 1, 2020',
                end_date: 'Mar 3, 2022',
            },
        ],
        unit_assignment_history: [
            {
                id: 101,
                unit: 'Metro Support Line',
                unit_type: 'Unit',
                code: 'STL-001',
                start_date: 'Jan 15, 2025',
                end_date: null,
            },
            {
                id: 102,
                unit: 'CS Escalations',
                unit_type: 'Department',
                code: 'CS-ESC',
                start_date: 'Mar 4, 2022',
                end_date: 'Jan 14, 2025',
            },
        ],
        positions: [
            {
                title: 'Lead Analyst',
                code: 'ANL-L3',
                is_primary: true,
                start_date: 'Jan 15, 2025',
                end_date: null,
            },
            {
                title: 'Analyst II',
                code: 'ANL-2',
                is_primary: false,
                start_date: 'Mar 4, 2022',
                end_date: 'Jan 14, 2025',
            },
            {
                title: 'Analyst I',
                code: 'ANL-1',
                is_primary: false,
                start_date: 'Jun 1, 2020',
                end_date: 'Mar 3, 2022',
            },
        ],
        attendance_summary: [
            {
                label: 'This cut-off',
                value: 'Present 21 · Leave 1 · Rest day 9',
            },
            {
                label: 'Tardiness (rolling 90 days)',
                value: '2 occurrences',
                hint: 'Open Attendance for detailed logs.',
            },
            {
                label: 'Undertime (rolling 90 days)',
                value: '1 occurrence',
            },
            {
                label: 'Overtime hours (this month)',
                value: '14h 30m',
            },
        ],
    };
}

export function mockEmployeeShowProfile(
    employeeId: number,
): EmployeeProfileDisplay {
    const first_name = 'Jamie';
    const middle_name = '';
    const last_name = 'Dela Cruz';

    return {
        employee_id: employeeId,
        display_name: formatEmployeeDisplayName(
            first_name,
            middle_name,
            last_name,
        ),
        first_name,
        middle_name,
        last_name,
        legal_name_line: 'Jaime Santos Dela Cruz Jr.',
        id_number: `EMP-${String(employeeId).padStart(6, '0')}`,
        avatar_url: null,
        attendance_id: `ATT-${String(900000 + employeeId)}`,
        status_label: 'Active',
        branch_label: 'HQ · People Operations',
        org_scope_label: 'Organization-wide',
        schedule_label: 'Flexible · Core hours 09:00–15:00',
        schedule_template_code: 'FLX-CORE-09',
        schedule_day_tokens: [],
        schedule_working_days: '',
        stats: [
            { label: 'Hire date', value: 'Jun 12, 2019' },
            {
                label: 'Tenure',
                value: '6y 11mo',
                hint: 'Approximate from hire date',
            },
            {
                label: 'Primary position',
                value: 'HR Business Partner',
                hint: 'HRBP-2',
            },
            {
                label: 'Primary unit',
                value: 'People Operations',
                hint: 'Current assignment',
            },
        ],
        demographics: {
            birthdate_iso: '1991-02-03',
            birthdate_display: formatCalendarTriggerFromIsoYmd('1991-02-03'),
            sex: 'Male',
            civil_status: 'Married',
            nationality: 'Filipino',
            religion: 'Catholic',
            religion_other: null,
        },
        personal_contacts: [
            {
                id: 1,
                category: 'personal',
                channel_label: 'Mobile',
                contact_number: '+63 917 555 2211',
                email: 'jamie.delacruz@company.example',
                contact_person: null,
                relationship: null,
                is_primary: true,
            },
        ],
        emergency_contacts: [
            {
                id: 11,
                category: 'emergency',
                channel_label: 'Mobile',
                contact_number: '+63 919 444 8877',
                email: null,
                contact_person: 'Rina Dela Cruz',
                relationship: 'Spouse',
                is_primary: true,
            },
        ],
        current_address: {
            type: 'current',
            is_primary: true,
            lines: [
                'Tower B, 18/F',
                '88 Corporate Avenue, Brgy. Ugong',
                'Pasig City 1604, Metro Manila',
                'Philippines',
            ],
        },
        permanent_address: {
            type: 'permanent',
            is_primary: true,
            lines: [
                '12 Molave Street, Brgy. San Roque',
                'Antipolo City 1870, Rizal',
                'Philippines',
            ],
        },
        employment_rows: [
            { label: 'Start date', value: 'June 12, 2019' },
            { label: 'Separation date', value: '—' },
            { label: 'Status', value: 'Active' },
        ],
        affiliation_history: [
            {
                id: 21,
                root_unit_id: null,
                unit: 'Organization-wide',
                unit_type: 'Organization',
                code: null,
                start_date: 'Aug 1, 2023',
                end_date: null,
            },
            {
                id: 22,
                root_unit_id: 30,
                unit: 'Talent Acquisition',
                unit_type: 'Team',
                code: 'TA-CORE',
                start_date: 'Jun 12, 2019',
                end_date: 'Jul 31, 2023',
            },
        ],
        unit_assignment_history: [
            {
                id: 201,
                unit: 'HR Shared Services Floor',
                unit_type: 'Unit',
                code: 'HR-FL2',
                start_date: 'Aug 1, 2023',
                end_date: null,
            },
        ],
        positions: [
            {
                title: 'HR Business Partner',
                code: 'HRBP-2',
                is_primary: true,
                start_date: 'Aug 1, 2023',
                end_date: null,
            },
            {
                title: 'Recruiter',
                code: 'REC-1',
                is_primary: false,
                start_date: 'Jun 12, 2019',
                end_date: 'Jul 31, 2023',
            },
        ],
        attendance_summary: [
            {
                label: 'Last integrated sync',
                value: '—',
                hint: 'Connect attendance feeds to populate.',
            },
            {
                label: 'Exceptions (preview)',
                value: 'No rows',
                hint: 'Schedule overrides & incidents surface here.',
            },
        ],
    };
}
