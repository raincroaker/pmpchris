/**
 * Shared display types and mock builders for employee profile UIs.
 * Replace mocks with Inertia props when backend wiring is ready.
 */

export type EmployeeProfileAssignmentRow = {
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
};

/** Mirrors core `employees` demographic fields. */
export type EmployeeProfileDemographics = {
    birthdate_display: string;
    sex: string;
    civil_status: string;
    nationality: string;
    religion: string | null;
    birthday_visibility_label: string;
};

export type EmployeeProfileDisplay = {
    display_name: string;
    legal_name_line: string;
    id_number: string;
    avatar_url: string | null;
    attendance_id: string | null;
    status_label: string;
    branch_label: string;
    org_scope_label: string;
    schedule_label: string;
    schedule_template_code: string | null;
    /** Short banner copy; `null` hides the alert. */
    layout_notice: string | null;
    stats: EmployeeProfileStatTile[];
    demographics: EmployeeProfileDemographics;
    personal_contacts: EmployeeProfileContactRow[];
    emergency_contacts: EmployeeProfileContactRow[];
    current_address: EmployeeProfileAddressBlock | null;
    permanent_address: EmployeeProfileAddressBlock | null;
    employment_rows: EmployeeProfileEmploymentRow[];
    assignment_history: EmployeeProfileAssignmentRow[];
    positions: EmployeeProfilePositionRow[];
    attendance_summary: EmployeeProfileAttendanceSummary[];
};

export function mockAboutMeProfile(): EmployeeProfileDisplay {
    return {
        display_name: 'Alex Morgan',
        legal_name_line: 'Alexandra Quinn Morgan',
        id_number: 'EMP-2024-0148',
        avatar_url: null,
        attendance_id: 'ATT-881204',
        status_label: 'Active',
        branch_label: 'Metro Operations',
        org_scope_label: 'Branch-scoped',
        schedule_label: 'Standard weekday · Mon–Fri · 08:00–17:00',
        schedule_template_code: 'STD-WD-08',
        layout_notice: null,
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
            birthdate_display: 'April 12, 1994',
            sex: 'Female',
            civil_status: 'Single',
            nationality: 'Filipino',
            religion: 'Catholic',
            birthday_visibility_label:
                'Team (month & day visible to your team)',
        },
        personal_contacts: [
            {
                category: 'personal',
                channel_label: 'Mobile',
                contact_number: '+63 917 000 4488',
                email: 'alex.morgan@company.example',
                contact_person: null,
                relationship: null,
                is_primary: true,
            },
            {
                category: 'personal',
                channel_label: 'Work',
                contact_number: '+63 2 8888 0100',
                email: 'amorgan@internal.example',
                contact_person: null,
                relationship: null,
                is_primary: false,
            },
        ],
        emergency_contacts: [
            {
                category: 'emergency',
                channel_label: 'Mobile',
                contact_number: '+63 918 111 2299',
                email: null,
                contact_person: 'Jordan Morgan',
                relationship: 'Sibling',
                is_primary: true,
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
            { label: 'Hire date', value: 'March 4, 2022' },
            { label: 'Employment type', value: 'Regular · Full-time' },
            { label: 'Employment status', value: 'Active' },
            {
                label: 'Latest movement',
                value: 'Promotion to Lead Analyst · Jan 2025',
            },
        ],
        assignment_history: [
            {
                unit: 'Customer Success Pod',
                unit_type: 'Team',
                code: 'CS-POD-2',
                start_date: 'Jan 15, 2025',
                end_date: null,
            },
            {
                unit: 'Support Desk West',
                unit_type: 'Department',
                code: 'SUP-W',
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
        ],
        attendance_summary: [
            {
                label: 'This cut-off',
                value: 'Present 19 · Leave 1 · Rest day 11',
            },
            {
                label: 'Tardiness (rolling 90 days)',
                value: '2 occurrences',
                hint: 'Open Attendance for detailed logs.',
            },
        ],
    };
}

export function mockEmployeeShowProfile(
    employeeId: number,
): EmployeeProfileDisplay {
    return {
        display_name: `Jamie Dela Cruz`,
        legal_name_line: 'Jaime Santos Dela Cruz Jr.',
        id_number: `EMP-${String(employeeId).padStart(6, '0')}`,
        avatar_url: null,
        attendance_id: `ATT-${String(900000 + employeeId)}`,
        status_label: 'Active',
        branch_label: 'HQ · People Operations',
        org_scope_label: 'Organization-wide',
        schedule_label: 'Flexible · Core hours 09:00–15:00',
        schedule_template_code: 'FLX-CORE-09',
        layout_notice:
            'Sample data for layout review — replace with live employee payload when APIs are connected.',
        stats: [
            { label: 'Hire date', value: 'Jun 12, 2019' },
            { label: 'Tenure', value: '6y 11mo' },
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
            birthdate_display: 'February 3, 1991',
            sex: 'Male',
            civil_status: 'Married',
            nationality: 'Filipino',
            religion: 'Catholic',
            birthday_visibility_label: 'Private (full date HR-only)',
        },
        personal_contacts: [
            {
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
            { label: 'Hire date', value: 'June 12, 2019' },
            { label: 'Employment type', value: 'Regular · Full-time' },
            { label: 'Employment status', value: 'Active' },
            { label: 'HR notes', value: '—' },
        ],
        assignment_history: [
            {
                unit: 'People Operations',
                unit_type: 'Department',
                code: 'HR-OPS',
                start_date: 'Aug 1, 2023',
                end_date: null,
            },
            {
                unit: 'Talent Acquisition',
                unit_type: 'Team',
                code: 'TA-CORE',
                start_date: 'Jun 12, 2019',
                end_date: 'Jul 31, 2023',
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
