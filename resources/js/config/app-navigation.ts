import {
    Briefcase,
    Calendar,
    CalendarClock,
    Clock,
    Timer,
    FileText,
    GitBranch,
    LayoutGrid,
    MessageCircle,
    ClipboardList,
    Settings,
    Users,
} from 'lucide-vue-next';
import {
    chat,
    dashboard,
    employees,
    organizationChart,
    positions,
} from '@/routes';
import { auditLogs, users } from '@/routes/admin';
import {
    employeeSchedules as attendanceEmployeeSchedules,
    my as attendanceMy,
    reports as attendanceReports,
    shifts as attendanceShifts,
    team as attendanceTeam,
} from '@/routes/attendance';
import {
    branch as calendarBranch,
    company as calendarCompany,
    holidays as calendarHolidays,
    team as calendarTeam,
} from '@/routes/calendar';
import {
    branch as documentsBranch,
    company as documentsCompany,
    my as documentsMy,
    team as documentsTeam,
    trash as documentsTrash,
} from '@/routes/documents';
import { aboutMe, employmentHistory } from '@/routes/employees';
import {
    my as leaveMy,
    policies as leavePolicies,
    team as leaveTeam,
} from '@/routes/leave';
import { edit as organizationChartEdit } from '@/routes/organization-chart';
import {
    my as overtimeMy,
    policies as overtimePolicies,
    team as overtimeTeam,
} from '@/routes/overtime';
import { jobHistory as positionsJobHistory } from '@/routes/positions';
import type { NavTreeEntry } from '@/types';

/**
 * Single source of truth for sidebar / mobile app navigation (HRIS tree).
 * Order: daily-first (most common at top); adjust here as needed.
 */
export const appNavigationTree: NavTreeEntry[] = [
    {
        kind: 'link',
        title: 'Dashboard',
        icon: LayoutGrid,
        href: () => dashboard(),
    },
    {
        kind: 'link',
        title: 'Chats',
        icon: MessageCircle,
        href: () => chat(),
    },
    {
        kind: 'collapsible',
        title: 'Employee',
        icon: Users,
        items: [
            { title: 'About Me', href: () => aboutMe() },
            { title: 'Employees', href: () => employees() },
            {
                title: 'Add Employee',
                href: () => '/employees/create',
                requiredPermission: 'canAddEmployee',
            },
            {
                title: 'Employment History',
                href: () => employmentHistory(),
            },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Leave',
        icon: ClipboardList,
        items: [
            { title: 'My Leaves', href: () => leaveMy() },
            {
                title: 'Employee Leaves',
                href: () => leaveTeam(),
                requiredPermission: 'canViewEmployeeTeamLeaveOvertime',
            },
            {
                title: 'Leave Policies',
                href: () => leavePolicies(),
                adminOnly: true,
            },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Overtime',
        icon: Timer,
        items: [
            { title: 'My Overtime', href: () => overtimeMy() },
            {
                title: 'Employee Overtime',
                href: () => overtimeTeam(),
                requiredPermission: 'canViewEmployeeTeamLeaveOvertime',
            },
            {
                title: 'Overtime Policies',
                href: () => overtimePolicies(),
                adminOnly: true,
            },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Attendance',
        icon: Clock,
        items: [
            { title: 'My Attendance', href: () => attendanceMy() },
            { title: 'Team Attendance', href: () => attendanceTeam() },
            { title: 'Attendance Reports', href: () => attendanceReports() },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Scheduling',
        icon: CalendarClock,
        items: [
            {
                title: 'Employee Schedules',
                href: () => attendanceEmployeeSchedules(),
                requiredPermission: 'canManageScheduleAssignments',
            },
            {
                title: 'Work Schedules',
                href: () => attendanceShifts(),
                requiredPermission: 'canViewWorkSchedules',
            },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Calendar',
        icon: Calendar,
        items: [
            { title: 'Company Calendar', href: () => calendarCompany() },
            { title: 'Branch Calendar', href: () => calendarBranch() },
            { title: 'Team Calendar', href: () => calendarTeam() },
            { title: 'Holiday Calendar', href: () => calendarHolidays() },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Documents',
        icon: FileText,
        items: [
            { title: 'My Documents', href: () => documentsMy() },
            { title: 'Team Documents', href: () => documentsTeam() },
            { title: 'Branch Documents', href: () => documentsBranch() },
            { title: 'Company Documents', href: () => documentsCompany() },
            { title: 'Trash', href: () => documentsTrash() },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Positions',
        icon: Briefcase,
        adminOnly: true,
        items: [
            { title: 'Position List', href: () => positions() },
            { title: 'Job History', href: () => positionsJobHistory() },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Organization Chart',
        icon: GitBranch,
        items: [
            { title: 'View Chart', href: () => organizationChart() },
            {
                title: 'Edit Structure',
                href: () => organizationChartEdit(),
                requiredPermission: 'canEditOrganizationStructure',
            },
        ],
    },
    {
        kind: 'collapsible',
        title: 'Administration',
        icon: Settings,
        adminOnly: true,
        items: [
            { title: 'Users & Roles', href: () => users() },
            { title: 'Audit Logs', href: () => auditLogs() },
        ],
    },
];
