<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import {
    FileSpreadsheet,
    Info,
    ListFilter,
    Plus,
    Search,
} from 'lucide-vue-next';
import { computed, h, onMounted, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
import TeamFormIsoDatePicker from '@/components/hris/TeamFormIsoDatePicker.vue';
import TeamHrEmployeeCombobox from '@/components/hris/TeamHrEmployeeCombobox.vue';
import TeamHrUnitCombobox from '@/components/hris/TeamHrUnitCombobox.vue';
import TeamIndexDateRangePickers from '@/components/hris/TeamIndexDateRangePickers.vue';
import TeamTableSortHeader from '@/components/hris/TeamTableSortHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
    isoTodayLocal,
} from '@/lib/calendarMonthRange';
import { fetchTeamHrFormUnits } from '@/lib/teamHrFormApi';
import type {
    TeamHrFormEmployeeHit,
    TeamHrFormUnit,
} from '@/lib/teamHrFormApi';
import AttendanceEntryViewContent from '@/pages/Attendance/AttendanceEntryViewContent.vue';
import AttendanceGrossNetColumnHeader from '@/pages/Attendance/AttendanceGrossNetColumnHeader.vue';
import { defaultThirdSessionSegment } from '@/pages/Attendance/attendanceRulesTypes';
import AttendanceTeamRowActionsMenu from '@/pages/Attendance/AttendanceTeamRowActionsMenu.vue';
import { segmentsFromWorkScheduleTemplatePayload } from '@/pages/Attendance/teamAttendanceFromTemplate';
import type {
    TeamAttendanceDraft,
    TeamAttendancePunctualityFilter,
    TeamAttendanceRecordingStyleFilter,
    TeamAttendanceRow,
    TeamAttendanceSegment,
    TeamAttendanceStatusFilter,
} from '@/pages/Attendance/teamAttendanceTypes';
import {
    attendanceNetWithinScheduledOverlapDisplay,
    attendanceStatusBadgeClass,
    attendanceStatusLabel,
    clockInOutDisplay,
    clockPatternBadgeLabel,
    deriveTeamAttendanceRecordStatus,
    punctualityBadgeClass,
    punctualityLabel,
    TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_LOCKED_TOOLTIP,
    TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_UNLOCKED_TOOLTIP,
    TEAM_ATTENDANCE_FORM_HEADER_TOOLTIP,
    TEAM_ATTENDANCE_FORM_PROFILE_ATTENDANCE_ID_TOOLTIP,
    TEAM_ATTENDANCE_FORM_RECORDING_STYLE_TOOLTIP,
    TEAM_ATTENDANCE_FORM_STATUS_TOOLTIP,
    TEAM_ATTENDANCE_FORM_WORK_SCHEDULE_TOOLTIP,
} from '@/pages/Attendance/teamAttendanceUi';
import { employeeSchedules, team as attendanceTeam } from '@/routes/attendance';
import teamAttendanceDayRoutes from '@/routes/attendance/team/attendance-days';
import type { BreadcrumbItem } from '@/types';

type AttendanceTeamFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    unit_id?: number | null;
    date_from: string;
    date_to: string;
    status: TeamAttendanceStatusFilter;
    punctuality: TeamAttendancePunctualityFilter;
    recording_style: TeamAttendanceRecordingStyleFilter;
    chart_half: 'first_half' | 'second_half';
    sort: 'work_date';
    direction: 'asc' | 'desc';
};

type AttendanceTeamKpis = {
    lates_today: number;
    absent_yesterday: number;
    on_leave_today: number;
    ot_yesterday: number;
};

type AttendanceTeamChartRow = {
    date: string;
    on_time: number;
    late: number;
    absent: number;
    on_leave: number;
};

type DummyChartEmployee = {
    id: number;
    display_name: string;
    id_number: string;
    avatar_url: string | null;
    unit_name: string;
    unit_code: string | null;
};

type DummyLateEntry = {
    employee: DummyChartEmployee;
    scheduled_in: string;
    actual_in: string;
    reason: string;
};

type DummyAbsentEntry = {
    employee: DummyChartEmployee;
    reason: string;
};

type DummyOnLeaveEntry = {
    employee: DummyChartEmployee;
    leave_type: string;
    reason: string;
};

type DummyChartDayDetail = {
    date: string;
    late: DummyLateEntry[];
    absent: DummyAbsentEntry[];
    on_leave: DummyOnLeaveEntry[];
};

type AttendanceTeamChartProp = {
    half: 'first_half' | 'second_half';
    rows: AttendanceTeamChartRow[];
};

type AttendanceKpiEmployee = {
    id: number;
    display_name: string;
    id_number: string;
    unit_name: string;
    unit_code: string | null;
};

type AttendanceTeamKpiEmployees = {
    lates_today: AttendanceKpiEmployee[];
    absent_yesterday: AttendanceKpiEmployee[];
    on_leave_today: AttendanceKpiEmployee[];
    ot_yesterday: AttendanceKpiEmployee[];
};

type KpiKey =
    | 'lates_today'
    | 'absent_yesterday'
    | 'on_leave_today'
    | 'ot_yesterday';

type TeamAttendancePaginator = {
    data: TeamAttendanceRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const page = usePage<{
    branchContext: { id: number; code: string; name: string } | null;
    can?: { canAddEmployeeTeamLeaveOvertimeEntry?: boolean };
}>();

const canAddTeamAttendanceRecords = computed(() =>
    Boolean(page.props.can?.canAddEmployeeTeamLeaveOvertimeEntry),
);

const props = withDefaults(
    defineProps<{
        teamAttendanceDays: TeamAttendancePaginator;
        attendanceTeamFilters: AttendanceTeamFiltersProp;
        attendanceTeamKpis: AttendanceTeamKpis;
        attendanceTeamKpiEmployees: AttendanceTeamKpiEmployees;
        attendanceTeamChart: AttendanceTeamChartProp;
    }>(),
    {
        teamAttendanceDays: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        attendanceTeamFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            punctuality: 'all',
            recording_style: 'all',
            chart_half: 'first_half',
            sort: 'work_date',
            direction: 'desc',
        }),
        attendanceTeamKpis: () => ({
            lates_today: 0,
            absent_yesterday: 0,
            on_leave_today: 0,
            ot_yesterday: 0,
        }),
        attendanceTeamChart: () => ({
            half: 'first_half',
            rows: [],
        }),
        attendanceTeamKpiEmployees: () => ({
            lates_today: [],
            absent_yesterday: [],
            on_leave_today: [],
            ot_yesterday: [],
        }),
    },
);

const chartBranchId = computed(() => page.props.branchContext?.id ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team Attendance', href: attendanceTeam() },
];

const tablePlainHeadClass = 'font-medium text-muted-foreground';
const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';
const dialogViewScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

function normalizedAttendanceFilters(): AttendanceTeamFiltersProp {
    const f = props.attendanceTeamFilters;

    return {
        page: f.page ?? 1,
        per_page: f.per_page ?? 10,
        q: f.q ?? '',
        unit_id: f.unit_id ?? null,
        date_from: f.date_from ?? isoFirstDayOfMonth(new Date()),
        date_to: f.date_to ?? isoLastDayOfMonth(new Date()),
        status: (f.status ?? 'all') as TeamAttendanceStatusFilter,
        punctuality: (f.punctuality ??
            'all') as TeamAttendancePunctualityFilter,
        recording_style: (f.recording_style ??
            'all') as TeamAttendanceRecordingStyleFilter,
        chart_half:
            (f.chart_half ?? 'first_half') === 'second_half'
                ? 'second_half'
                : 'first_half',
        sort: 'work_date',
        direction: (f.direction ?? 'desc') === 'asc' ? 'asc' : 'desc',
    };
}

function buildQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        unit_id: number | null;
        date_from: string;
        date_to: string;
        status: TeamAttendanceStatusFilter;
        punctuality: TeamAttendancePunctualityFilter;
        recording_style: TeamAttendanceRecordingStyleFilter;
        chart_half: 'first_half' | 'second_half';
        sort: 'work_date';
        direction: 'asc' | 'desc';
    }> = {},
): Record<string, string | number> {
    const merged = { ...normalizedAttendanceFilters(), ...overrides };
    const pageNum =
        overrides.page !== undefined
            ? overrides.page
            : props.teamAttendanceDays.current_page;

    const q: Record<string, string | number> = {
        page: pageNum,
        per_page: merged.per_page,
        date_from: merged.date_from,
        date_to: merged.date_to,
        status: merged.status,
        punctuality: merged.punctuality,
        recording_style: merged.recording_style,
        chart_half: merged.chart_half,
        sort: merged.sort,
        direction: merged.direction,
    };

    const trimmed = merged.q.trim();
    if (trimmed !== '') {
        q.q = trimmed;
    }

    if (merged.unit_id !== null && merged.unit_id !== undefined) {
        q.unit_id = merged.unit_id;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        unit_id: number | null;
        date_from: string;
        date_to: string;
        status: TeamAttendanceStatusFilter;
        punctuality: TeamAttendancePunctualityFilter;
        recording_style: TeamAttendanceRecordingStyleFilter;
        chart_half: 'first_half' | 'second_half';
        sort: 'work_date';
        direction: 'asc' | 'desc';
    }> = {},
): void {
    router.get(
        attendanceTeam.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const halfMonthOptions: Array<{
    value: 'first_half' | 'second_half';
    label: string;
}> = [
    { value: 'first_half', label: '1st half (1-15)' },
    { value: 'second_half', label: '2nd half (16-end)' },
];

const halfMonthSelectValue = computed(
    () => props.attendanceTeamFilters.chart_half ?? 'first_half',
);

const halfMonthLabel = computed(() =>
    props.attendanceTeamChart.half === 'second_half' ? '2nd half' : '1st half',
);

const useDummyChartData = true;
const dummyChartRows: AttendanceTeamChartRow[] = [
    { date: 'May 1', on_time: 18, late: 0, absent: 0, on_leave: 1 },
    { date: 'May 2', on_time: 16, late: 2, absent: 0, on_leave: 1 },
    { date: 'May 3', on_time: 15, late: 0, absent: 3, on_leave: 2 },
    { date: 'May 4', on_time: 17, late: 1, absent: 0, on_leave: 1 },
    { date: 'May 5', on_time: 14, late: 0, absent: 2, on_leave: 2 },
    { date: 'May 6', on_time: 19, late: 0, absent: 0, on_leave: 0 },
    { date: 'May 7', on_time: 13, late: 0, absent: 4, on_leave: 1 },
];
const dummyChartDayDetails: DummyChartDayDetail[] = [
    {
        date: 'May 1',
        late: [],
        absent: [],
        on_leave: [
            {
                employee: {
                    id: 701,
                    display_name: 'Arielle Gomez',
                    id_number: 'PMPC-11701',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                leave_type: 'Vacation leave',
                reason: 'Pre-approved family trip',
            },
        ],
    },
    {
        date: 'May 2',
        late: [
            {
                employee: {
                    id: 492,
                    display_name: 'Ana Morales',
                    id_number: 'PMPC-10492',
                    avatar_url: null,
                    unit_name: 'Panabo Branch',
                    unit_code: 'PNB',
                },
                scheduled_in: '08:30',
                actual_in: '08:41',
                reason: 'Heavy traffic on main highway',
            },
            {
                employee: {
                    id: 901,
                    display_name: 'Noah Ramos',
                    id_number: 'PMPC-19001',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                scheduled_in: '09:00',
                actual_in: '09:08',
                reason: 'Late bus arrival',
            },
        ],
        absent: [],
        on_leave: [
            {
                employee: {
                    id: 821,
                    display_name: 'Mia Salazar',
                    id_number: 'PMPC-11821',
                    avatar_url: null,
                    unit_name: 'Panabo Branch',
                    unit_code: 'PNB',
                },
                leave_type: 'Sick leave',
                reason: 'Medical rest',
            },
        ],
    },
    {
        date: 'May 3',
        late: [],
        absent: [
            {
                employee: {
                    id: 2,
                    display_name: 'Leo Villarin',
                    id_number: 'PMPC-10002',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                reason: 'Sick leave not filed yet',
            },
            {
                employee: {
                    id: 801,
                    display_name: 'Mira Fernandez',
                    id_number: 'PMPC-10801',
                    avatar_url: null,
                    unit_name: 'Panabo - Section A',
                    unit_code: 'PNB-A',
                },
                reason: 'No time-in record',
            },
            {
                employee: {
                    id: 177,
                    display_name: 'Jordan Cruz',
                    id_number: 'PMPC-10177',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                reason: 'Unexcused absence',
            },
        ],
        on_leave: [
            {
                employee: {
                    id: 735,
                    display_name: 'Luna Herrera',
                    id_number: 'PMPC-11735',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                leave_type: 'Maternity leave',
                reason: 'Approved long leave',
            },
            {
                employee: {
                    id: 766,
                    display_name: 'Rex Manalo',
                    id_number: 'PMPC-11766',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                leave_type: 'Emergency leave',
                reason: 'Family emergency',
            },
        ],
    },
    {
        date: 'May 4',
        late: [
            {
                employee: {
                    id: 220,
                    display_name: 'Sofia Delgado',
                    id_number: 'PMPC-11220',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                scheduled_in: '08:00',
                actual_in: '08:11',
                reason: 'School drop-off delay',
            },
        ],
        absent: [],
        on_leave: [
            {
                employee: {
                    id: 732,
                    display_name: 'Jen Cruz',
                    id_number: 'PMPC-11732',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                leave_type: 'Vacation leave',
                reason: 'Approved personal leave',
            },
        ],
    },
    {
        date: 'May 5',
        late: [],
        absent: [
            {
                employee: {
                    id: 612,
                    display_name: 'Carlo Reyes',
                    id_number: 'PMPC-11612',
                    avatar_url: null,
                    unit_name: 'Panabo Branch',
                    unit_code: 'PNB',
                },
                reason: 'Emergency personal matter',
            },
            {
                employee: {
                    id: 644,
                    display_name: 'Aira Santos',
                    id_number: 'PMPC-11644',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                reason: 'No attendance entry',
            },
        ],
        on_leave: [
            {
                employee: {
                    id: 722,
                    display_name: 'Paolo Rivas',
                    id_number: 'PMPC-11722',
                    avatar_url: null,
                    unit_name: 'Panabo Branch',
                    unit_code: 'PNB',
                },
                leave_type: 'Sick leave',
                reason: 'Fever and rest day',
            },
            {
                employee: {
                    id: 729,
                    display_name: 'April Nunez',
                    id_number: 'PMPC-11729',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                leave_type: 'Vacation leave',
                reason: 'Planned leave',
            },
        ],
    },
    { date: 'May 6', late: [], absent: [], on_leave: [] },
    {
        date: 'May 7',
        late: [],
        absent: [
            {
                employee: {
                    id: 660,
                    display_name: 'Ben Cruz',
                    id_number: 'PMPC-11660',
                    avatar_url: null,
                    unit_name: 'Tagum Branch',
                    unit_code: 'TAG',
                },
                reason: 'No show',
            },
            {
                employee: {
                    id: 677,
                    display_name: 'Jessa Ong',
                    id_number: 'PMPC-11677',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                reason: 'No attendance entry',
            },
            {
                employee: {
                    id: 683,
                    display_name: 'Ralph Diaz',
                    id_number: 'PMPC-11683',
                    avatar_url: null,
                    unit_name: 'Panabo Branch',
                    unit_code: 'PNB',
                },
                reason: 'Pending leave approval',
            },
            {
                employee: {
                    id: 699,
                    display_name: 'Mark Javier',
                    id_number: 'PMPC-11699',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                reason: 'Unexcused absence',
            },
        ],
        on_leave: [
            {
                employee: {
                    id: 744,
                    display_name: 'Toni Ramos',
                    id_number: 'PMPC-11744',
                    avatar_url: null,
                    unit_name: 'Head Office',
                    unit_code: 'HQ',
                },
                leave_type: 'Emergency leave',
                reason: 'Urgent family matter',
            },
        ],
    },
];

const chartData = computed(() => {
    const rows = useDummyChartData
        ? dummyChartRows
        : props.attendanceTeamChart.rows;

    return rows.map((row, index) => ({
        ...row,
        x_index: index,
    }));
});
const chartMaxTotal = computed(() =>
    Math.max(
        1,
        ...chartData.value.map(
            (row) => row.on_time + row.late + row.absent + row.on_leave,
        ),
    ),
);
function chartSegmentHeight(value: number): string {
    return `${(value / chartMaxTotal.value) * 100}%`;
}

function hmToMinutes(hm: string): number | null {
    const match = /^(\d{1,2}):(\d{2})$/.exec(hm.trim());
    if (!match) {
        return null;
    }

    const hours = Number.parseInt(match[1] ?? '', 10);
    const minutes = Number.parseInt(match[2] ?? '', 10);
    if (
        Number.isNaN(hours) ||
        Number.isNaN(minutes) ||
        hours < 0 ||
        hours > 23 ||
        minutes < 0 ||
        minutes > 59
    ) {
        return null;
    }

    return hours * 60 + minutes;
}

function lateMinutesWithGrace(
    actualIn: string,
    scheduledIn: string,
    graceMinutes = 10,
): number {
    const actual = hmToMinutes(actualIn);
    const scheduled = hmToMinutes(scheduledIn);
    if (actual === null || scheduled === null) {
        return 0;
    }

    return Math.max(0, actual - (scheduled + graceMinutes));
}
const chartMonthLabel = computed(() => {
    const date = new Date(`${props.attendanceTeamFilters.date_from}T12:00:00`);

    return date.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
});
const chartSeriesLegend = [
    { label: 'On time', colorClass: 'bg-emerald-500' },
    { label: 'Late', colorClass: 'bg-amber-500' },
    { label: 'Absent', colorClass: 'bg-red-500' },
    { label: 'On leave', colorClass: 'bg-sky-500' },
];

const chartTotals = computed(() =>
    chartData.value.reduce(
        (acc, row) => {
            acc.on_time += row.on_time;
            acc.late += row.late;
            acc.absent += row.absent;
            acc.on_leave += row.on_leave;

            return acc;
        },
        { on_time: 0, late: 0, absent: 0, on_leave: 0 },
    ),
);

const kpiDialogOpen = ref(false);
const kpiDialogKey = ref<KpiKey | null>(null);
const chartDetailDialogOpen = ref(false);
const selectedChartDate = ref<string | null>(null);

const kpiMeta: Record<
    KpiKey,
    {
        title: string;
        subtitle: string;
        accentClass: string;
        borderClass: string;
        valueClass: string;
    }
> = {
    lates_today: {
        title: 'Lates today',
        subtitle: 'Employees marked late today.',
        accentClass: 'text-amber-600 dark:text-amber-300',
        borderClass: 'border-amber-500/35',
        valueClass: 'text-amber-600 dark:text-amber-300',
    },
    absent_yesterday: {
        title: 'Absence yesterday',
        subtitle: 'Employees with no attendance/leave yesterday.',
        accentClass: 'text-red-600 dark:text-red-300',
        borderClass: 'border-red-500/35',
        valueClass: 'text-red-600 dark:text-red-300',
    },
    on_leave_today: {
        title: 'On leave today',
        subtitle: 'Employees on approved leave today.',
        accentClass: 'text-sky-600 dark:text-sky-300',
        borderClass: 'border-sky-500/35',
        valueClass: 'text-sky-600 dark:text-sky-300',
    },
    ot_yesterday: {
        title: 'OT yesterday',
        subtitle: 'Employees with approved overtime yesterday.',
        accentClass: 'text-violet-600 dark:text-violet-300',
        borderClass: 'border-violet-500/35',
        valueClass: 'text-violet-600 dark:text-violet-300',
    },
};

const kpiCards = computed(() => {
    return (Object.keys(kpiMeta) as KpiKey[]).map((key) => ({
        key,
        ...kpiMeta[key],
        value: props.attendanceTeamKpis[key],
        employees: props.attendanceTeamKpiEmployees[key] ?? [],
    }));
});

const activeKpiMeta = computed(() => {
    if (kpiDialogKey.value === null) {
        return null;
    }

    return kpiMeta[kpiDialogKey.value];
});

const activeKpiEmployees = computed(() => {
    if (kpiDialogKey.value === null) {
        return [];
    }

    return props.attendanceTeamKpiEmployees[kpiDialogKey.value] ?? [];
});

const selectedChartDetail = computed(() => {
    if (selectedChartDate.value === null) {
        return null;
    }

    return (
        dummyChartDayDetails.find((row) => row.date === selectedChartDate.value) ??
        null
    );
});

function openKpiDialog(key: KpiKey): void {
    kpiDialogKey.value = key;
    kpiDialogOpen.value = true;
}

function openChartDetail(date: string): void {
    selectedChartDate.value = date;
    chartDetailDialogOpen.value = true;
}

function toggleSort(column: AttendanceTeamFiltersProp['sort']): void {
    const same = props.attendanceTeamFilters.sort === column;
    const nextDir =
        same && props.attendanceTeamFilters.direction === 'asc'
            ? 'desc'
            : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function sortDirectionFor(
    column: AttendanceTeamFiltersProp['sort'],
): 'asc' | 'desc' | null {
    if (props.attendanceTeamFilters.sort !== column) {
        return null;
    }

    return props.attendanceTeamFilters.direction;
}

const {
    localSearch,
    syncFromServerSearch,
    onSearchUpdate,
    onSearchKeyup,
    onSearchCommit,
} = useDebouncedSearchInput({
    initialValue: props.attendanceTeamFilters.q ?? '',
    debounceMs: 300,
    onDebouncedSearch: (value) => applyQuery({ q: value, page: 1 }),
});

watch(
    () => props.attendanceTeamFilters.q,
    (s) => syncFromServerSearch(s ?? ''),
);

const branchUnits = ref<TeamHrFormUnit[]>([]);
const branchUnitsLoadError = ref<string | null>(null);
const branchUnitsLoading = ref(false);

const branchUnitFilterOptions = computed(() => {
    const base: Array<{
        value: string;
        label: string;
        code?: string | null;
    }> = [{ value: 'all', label: 'All units' }];

    if (branchUnits.value.length > 0) {
        return [
            ...base,
            ...branchUnits.value.map((u) => ({
                value: String(u.id),
                label: u.name,
                code: u.code,
            })),
        ];
    }

    return base;
});

const recordingStyleChipOptions: Array<{
    value: TeamAttendanceRecordingStyleFilter;
    label: string;
}> = [
    { value: 'all', label: 'All styles' },
    { value: 'simple', label: 'Simple session' },
    { value: 'split', label: 'Split sessions' },
    { value: 'overnight', label: 'Overnight' },
];

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const UNIT_FILTER_ALL = 'all' as const;

const unitSelectModelValue = computed(() =>
    props.attendanceTeamFilters.unit_id != null
        ? String(props.attendanceTeamFilters.unit_id)
        : UNIT_FILTER_ALL,
);

function onUnitFilterChange(value: unknown): void {
    if (value === undefined || value === null || value === '') {
        applyQuery({ unit_id: null, page: 1 });

        return;
    }

    if (typeof value !== 'string') {
        applyQuery({ unit_id: null, page: 1 });

        return;
    }

    const next = value === UNIT_FILTER_ALL ? null : Number.parseInt(value, 10);
    if (next === null || Number.isNaN(next)) {
        applyQuery({ unit_id: null, page: 1 });

        return;
    }

    applyQuery({ unit_id: next, page: 1 });
}

const toolbarDateFromModel = computed({
    get(): string {
        return (
            props.attendanceTeamFilters.date_from ??
            isoFirstDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_from: iso, page: 1 });
    },
});

const toolbarDateToModel = computed({
    get(): string {
        return (
            props.attendanceTeamFilters.date_to ?? isoLastDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_to: iso, page: 1 });
    },
});

const viewDialogOpen = ref(false);
const viewTarget = ref<TeamAttendanceRow | null>(null);

const mutateDialogOpen = ref(false);
const isEditing = ref(false);
const editId = ref<number | null>(null);
const mutatePrevRow = ref<TeamAttendanceRow | null>(null);
const activeDraft = ref<TeamAttendanceDraft | null>(null);
const formError = ref<string | null>(null);

function syncActiveDraftRecordStatus(): void {
    const d = activeDraft.value;
    if (!d) {
        return;
    }

    d.status = deriveTeamAttendanceRecordStatus(
        normalizeDraftSegments(d.segments),
    );
}

watch(
    () => activeDraft.value?.segments,
    () => {
        syncActiveDraftRecordStatus();
    },
    { deep: true },
);

function clearEmployeeAttendanceProfile(): void {
    if (!activeDraft.value) {
        return;
    }

    const d = activeDraft.value;
    d.attendance_id = '';
    d.work_schedule_template_id = null;
    d.work_schedule_name = '';
    d.segments = [];
    d.clock_pattern = 'single_pair';
    d.is_overnight_schedule = false;
}

function applyEmployeeAttendanceProfileFromHit(
    hit: TeamHrFormEmployeeHit,
): void {
    if (!activeDraft.value) {
        return;
    }

    const d = activeDraft.value;
    d.attendance_id = (hit.attendance_id ?? '').trim();

    const templateId = hit.work_schedule_template_id ?? null;
    const template = hit.work_schedule_template ?? null;

    if (template != null && templateId != null) {
        d.work_schedule_template_id = templateId;
        d.work_schedule_name = template.name;
        d.clock_pattern = template.clock_pattern;
        d.is_overnight_schedule = template.is_overnight;
        d.segments = segmentsFromWorkScheduleTemplatePayload(template);

        return;
    }

    d.work_schedule_template_id = null;
    d.work_schedule_name = '';
    d.segments = [];
    d.clock_pattern = 'single_pair';
    d.is_overnight_schedule = false;
}

const selectedEmployeeHitModel = computed({
    get(): TeamHrFormEmployeeHit | null {
        const d = activeDraft.value;
        if (!d?.employee_id) {
            return null;
        }

        return {
            id: d.employee_id,
            employee_id: d.employee_id,
            employee_number: d.employee_id_number || null,
            full_name: d.employee_name,
            active_position_title: null,
            avatar_url: null,
        };
    },
    set(value: TeamHrFormEmployeeHit | null) {
        if (!activeDraft.value) {
            return;
        }

        if (value === null) {
            activeDraft.value.employee_id = null;
            activeDraft.value.employee_name = '';
            activeDraft.value.employee_id_number = '';
            clearEmployeeAttendanceProfile();

            return;
        }

        activeDraft.value.employee_id = value.id;
        activeDraft.value.employee_name = value.full_name;
        activeDraft.value.employee_id_number = value.employee_number ?? '';
        applyEmployeeAttendanceProfileFromHit(value);
    },
});

const scheduleFieldsLocked = computed(
    () => activeDraft.value?.work_schedule_template_id != null,
);

const clockTimesMutateFormTooltip = computed((): string =>
    scheduleFieldsLocked.value
        ? TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_LOCKED_TOOLTIP
        : TEAM_ATTENDANCE_FORM_CLOCK_TIMES_SCHEDULE_UNLOCKED_TOOLTIP,
);

/** Shown after an employee is chosen but directory prerequisites are missing. */
const employeeAttendanceSetupHint = computed((): string | null => {
    const d = activeDraft.value;
    if (!d || d.employee_id === null) {
        return null;
    }

    if ((d.attendance_id ?? '').trim() === '') {
        return 'This employee has no attendance ID. Set it on Employee Schedules, then try again.';
    }

    if (d.segments.length === 0) {
        return 'This employee has no assigned work schedule template. Assign one on Employee Schedules, then try again.';
    }

    return null;
});

const deleteDialogOpen = ref(false);
const deleteTarget = ref<TeamAttendanceRow | null>(null);

async function ensureBranchUnitsLoaded(): Promise<void> {
    if (branchUnitsLoading.value) {
        return;
    }

    branchUnitsLoadError.value = null;
    branchUnitsLoading.value = true;

    try {
        const { units } = await fetchTeamHrFormUnits();
        branchUnits.value = units;
    } catch {
        branchUnitsLoadError.value =
            'Could not load units for this branch. Using demo unit list in the form.';
    } finally {
        branchUnitsLoading.value = false;
    }
}

watch(chartBranchId, () => {
    branchUnits.value = [];
    void ensureBranchUnitsLoaded();
});

onMounted(() => {
    void ensureBranchUnitsLoaded();
});

watch(mutateDialogOpen, (open) => {
    if (open) {
        void ensureBranchUnitsLoaded();
    }
});

function employeeInitials(name: string): string {
    const parts = name
        .trim()
        .split(/\s+/)
        .filter((part) => part.length > 0);
    if (parts.length === 0) {
        return 'E';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

function trimToNull(raw: string): string | null {
    const v = raw.trim();

    return v === '' ? null : v;
}

function normalizeDraftSegments(
    segments: TeamAttendanceSegment[],
): TeamAttendanceSegment[] {
    return segments.map((s) => ({
        label: s.label.trim(),
        scheduled_in: s.scheduled_in.trim(),
        scheduled_out: s.scheduled_out.trim(),
        actual_in: trimToNull(s.actual_in ?? ''),
        actual_out: trimToNull(s.actual_out ?? ''),
    }));
}

function syncDraftUnitLabels(draft: TeamAttendanceDraft): void {
    if (draft.organizational_unit_id === null) {
        draft.unit_filter_value = '';
        draft.unit_name = '';
        draft.unit_code = null;

        return;
    }

    const match = branchUnits.value.find(
        (u) => u.id === draft.organizational_unit_id,
    );

    if (match) {
        draft.unit_name = match.name;
        draft.unit_code = match.code;
        draft.unit_filter_value = `unit-${match.id}`;

        return;
    }

    draft.unit_filter_value = `unit-${draft.organizational_unit_id}`;
}

function blankSegmentFromTemplate(
    label: string,
    timeIn: string,
    timeOut: string,
): TeamAttendanceSegment {
    return {
        label,
        scheduled_in: timeIn,
        scheduled_out: timeOut,
        actual_in: null,
        actual_out: null,
    };
}

function defaultDraft(): TeamAttendanceDraft {
    return {
        employee_name: '',
        employee_id_number: '',
        employee_id: null,
        organizational_unit_id: null,
        unit_filter_value: '',
        unit_name: '',
        unit_code: null,
        work_date: isoTodayLocal(),
        attendance_id: '',
        work_schedule_template_id: null,
        clock_pattern: 'single_pair',
        is_overnight_schedule: false,
        work_schedule_name: '',
        segments: [],
        status: deriveTeamAttendanceRecordStatus([]),
    };
}

function cloneToDraft(row: TeamAttendanceRow): TeamAttendanceDraft {
    return {
        employee_name: row.employee.display_name,
        employee_id_number: row.employee.id_number,
        employee_id: row.employee_record_id ?? null,
        organizational_unit_id: row.organizational_unit_id ?? null,
        unit_filter_value: row.unit_filter_value,
        unit_name: row.unit_name,
        unit_code: row.unit_code,
        work_date: row.work_date,
        attendance_id: row.attendance_id ?? '',
        work_schedule_template_id: row.work_schedule_template_id,
        clock_pattern: row.clock_pattern,
        is_overnight_schedule: row.is_overnight_schedule,
        work_schedule_name: row.work_schedule_name,
        segments: row.segments.map((s) => ({
            ...s,
            actual_in: s.actual_in,
            actual_out: s.actual_out,
        })),
        status: row.status,
    };
}

function onFormUnitChange(unitId: number | null): void {
    if (!activeDraft.value) {
        return;
    }

    activeDraft.value.organizational_unit_id = unitId;
    activeDraft.value.employee_id = null;
    activeDraft.value.employee_name = '';
    activeDraft.value.employee_id_number = '';
    clearEmployeeAttendanceProfile();
    syncDraftUnitLabels(activeDraft.value);
}

function openView(row: TeamAttendanceRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

function openAdd(): void {
    if (!canAddTeamAttendanceRecords.value) {
        appToast.error(
            'You do not have permission to add team attendance entries.',
        );

        return;
    }
    isEditing.value = false;
    editId.value = null;
    mutatePrevRow.value = null;
    activeDraft.value = defaultDraft();
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openEdit(row: TeamAttendanceRow): void {
    if (!canAddTeamAttendanceRecords.value) {
        appToast.error(
            'You do not have permission to edit team attendance entries.',
        );

        return;
    }
    isEditing.value = true;
    editId.value = row.id;
    mutatePrevRow.value = row;
    activeDraft.value = cloneToDraft(row);
    formError.value = null;
    mutateDialogOpen.value = true;
}

function validateDraft(d: TeamAttendanceDraft): string | null {
    if (chartBranchId.value === null) {
        return 'Select a workspace branch (header) before saving.';
    }

    if (d.organizational_unit_id === null) {
        return 'Select a unit.';
    }

    if (d.employee_id === null) {
        return 'Select an employee.';
    }

    if (d.work_date.trim() === '') {
        return 'Work date is required.';
    }

    if ((d.attendance_id ?? '').trim() === '') {
        return 'This employee has no attendance ID. Set it on Employee Schedules before recording attendance.';
    }

    if (d.segments.length === 0) {
        return 'This employee has no assigned work schedule template. Assign one on Employee Schedules before recording attendance.';
    }

    const segs = normalizeDraftSegments(d.segments);
    if (segs.length === 0) {
        return 'Add at least one schedule segment.';
    }

    const pattern = d.clock_pattern;
    if (pattern === 'single_pair' && segs.length !== 1) {
        return 'Simple attendance uses exactly one segment.';
    }

    if (pattern === 'split_sessions' && (segs.length < 2 || segs.length > 8)) {
        return 'Split-session attendance uses at least two segments (and at most eight).';
    }

    if (!isEditing.value && d.work_schedule_template_id === null) {
        return 'This employee needs an assigned work schedule template before recording a day.';
    }

    for (const s of segs) {
        if (s.label.trim() === '') {
            return 'Each segment needs a label.';
        }

        if (s.scheduled_in === '' || s.scheduled_out === '') {
            return 'Scheduled in/out are required for every segment.';
        }
    }

    return null;
}

function buildTeamAttendancePayload(
    d: TeamAttendanceDraft,
    mode: 'create' | 'update',
): Record<string, unknown> {
    const segs = normalizeDraftSegments(d.segments);

    const payload: Record<string, unknown> = {
        employee_id: d.employee_id,
        organizational_unit_id: d.organizational_unit_id,
        work_date: d.work_date.trim().slice(0, 10),
        segments: segs.map((s) => ({
            label: s.label,
            scheduled_in: s.scheduled_in,
            scheduled_out: s.scheduled_out,
            actual_in: s.actual_in,
            actual_out: s.actual_out,
        })),
    };

    if (mode === 'create' && d.work_schedule_template_id != null) {
        payload.work_schedule_template_id = d.work_schedule_template_id;
    }

    return payload;
}

function applyMutate(): void {
    if (!canAddTeamAttendanceRecords.value) {
        formError.value =
            'You do not have permission to add or change team attendance entries.';

        return;
    }

    if (!activeDraft.value) {
        return;
    }

    const d = activeDraft.value;
    const err = validateDraft(d);
    if (err) {
        formError.value = err;

        return;
    }

    formError.value = null;
    syncDraftUnitLabels(d);

    const mode = isEditing.value ? 'update' : 'create';
    const payload = buildTeamAttendancePayload(d, mode) as RequestPayload;

    if (isEditing.value && editId.value != null) {
        router.patch(
            teamAttendanceDayRoutes.update({
                employeeAttendanceDay: editId.value,
            }).url,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    mutateDialogOpen.value = false;
                    activeDraft.value = null;
                    mutatePrevRow.value = null;
                    appToast.success('Attendance entry updated.');
                },
                onError: () => {
                    formError.value =
                        'Could not save changes. Check the form and try again.';
                },
            },
        );

        return;
    }

    router.post(teamAttendanceDayRoutes.store.url(), payload, {
        preserveScroll: true,
        onSuccess: () => {
            mutateDialogOpen.value = false;
            activeDraft.value = null;
            mutatePrevRow.value = null;
            appToast.success('Attendance entry added.');
        },
        onError: () => {
            formError.value =
                'Could not save changes. Check the form and try again.';
        },
    });
}

function openDeleteConfirm(row: TeamAttendanceRow): void {
    if (!canAddTeamAttendanceRecords.value) {
        appToast.error(
            'You do not have permission to delete team attendance entries.',
        );

        return;
    }
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
}

function confirmDelete(): void {
    if (!canAddTeamAttendanceRecords.value) {
        deleteDialogOpen.value = false;
        deleteTarget.value = null;

        return;
    }
    const row = deleteTarget.value;
    if (!row) {
        return;
    }

    const id = row.id;
    router.delete(
        teamAttendanceDayRoutes.destroy({ employeeAttendanceDay: id }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                if (viewTarget.value?.id === id) {
                    viewDialogOpen.value = false;
                    viewTarget.value = null;
                }

                appToast.success('Attendance entry removed.');
            },
            onError: () => {
                appToast.error('Could not delete this entry.');
            },
        },
    );
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

const attendanceStatusHeaderFilterOptions = computed(() =>
    (
        [
            'all',
            'complete',
            'ongoing',
            'incomplete',
        ] as TeamAttendanceStatusFilter[]
    ).map((value) => ({
        value,
        label: value === 'all' ? 'All' : attendanceStatusLabel(value),
    })),
);

const punctualityHeaderFilterOptions = computed(() =>
    (
        [
            'all',
            'on_time',
            'late',
            'not_applicable',
        ] as TeamAttendancePunctualityFilter[]
    ).map((value) => ({
        value,
        label: value === 'all' ? 'All' : punctualityLabel(value),
    })),
);

const punctualityColumnFilterAriaLabel = computed(() => {
    const cur = props.attendanceTeamFilters.punctuality ?? 'all';
    const summary = cur === 'all' ? 'All' : punctualityLabel(cur);

    return `Punctuality filter for team attendance entries: ${summary}. Open to choose on time, late, N/A, or all.`;
});

function onPunctualityHeaderFilterUpdate(v: string | number | null): void {
    const s = String(v ?? 'all');

    if (s === 'all') {
        applyQuery({ punctuality: 'all', page: 1 });

        return;
    }

    if (s === 'on_time' || s === 'late' || s === 'not_applicable') {
        applyQuery({ punctuality: s, page: 1 });
    }
}

const statusColumnFilterAriaLabel = computed(() => {
    const cur = props.attendanceTeamFilters.status ?? 'all';
    const summary = cur === 'all' ? 'All' : attendanceStatusLabel(cur);

    return `Status filter for team attendance entries: ${summary}. Open to choose complete, ongoing, incomplete, or all.`;
});

function onAttendanceStatusHeaderFilterUpdate(v: string | number | null): void {
    const s = String(v ?? 'all');

    if (s === 'all') {
        applyQuery({ status: 'all', page: 1 });

        return;
    }

    if (s === 'complete' || s === 'ongoing' || s === 'incomplete') {
        applyQuery({ status: s, page: 1 });
    }
}

const totalRows = computed(() => props.teamAttendanceDays.total);

const emptyMessage = computed(
    () =>
        'No attendance rows match your current filters (branch workspace, dates, and toolbar choices).',
);

const fromRow = computed(() => props.teamAttendanceDays.from);

const toRow = computed(() => props.teamAttendanceDays.to);

const lastPage = computed(() =>
    Math.max(1, props.teamAttendanceDays.last_page),
);

function generateTeamDtrExcel(): void {
    const query = new URLSearchParams({
        mode: 'team',
        date_from: toolbarDateFromModel.value,
        date_to: toolbarDateToModel.value,
    });

    if (props.attendanceTeamFilters.unit_id != null) {
        query.set('unit_id', String(props.attendanceTeamFilters.unit_id));
    }

    window.location.assign(
        `/attendance/reports/dtr-mock-sample?${query.toString()}`,
    );
}

function setActualIn(
    index: number,
    raw: string | number | null | undefined,
): void {
    if (!activeDraft.value) {
        return;
    }

    const s = activeDraft.value.segments[index];
    if (!s) {
        return;
    }

    s.actual_in =
        raw === undefined || raw === null || String(raw).trim() === ''
            ? null
            : String(raw).trim();
}

function setActualOut(
    index: number,
    raw: string | number | null | undefined,
): void {
    if (!activeDraft.value) {
        return;
    }

    const s = activeDraft.value.segments[index];
    if (!s) {
        return;
    }

    s.actual_out =
        raw === undefined || raw === null || String(raw).trim() === ''
            ? null
            : String(raw).trim();
}

function addSessionSegment(): void {
    if (
        !activeDraft.value ||
        activeDraft.value.work_schedule_template_id != null ||
        activeDraft.value.clock_pattern !== 'split_sessions'
    ) {
        return;
    }

    if (activeDraft.value.segments.length >= 3) {
        return;
    }

    const third = defaultThirdSessionSegment();
    activeDraft.value.segments.push(
        blankSegmentFromTemplate(third.label, third.time_in, third.time_out),
    );
}

function removeLastSessionSegment(): void {
    if (
        !activeDraft.value ||
        activeDraft.value.work_schedule_template_id != null ||
        activeDraft.value.clock_pattern !== 'split_sessions'
    ) {
        return;
    }

    if (activeDraft.value.segments.length <= 2) {
        return;
    }

    activeDraft.value.segments.pop();
}

const columns = computed((): ColumnDef<TeamAttendanceRow>[] => {
    const mut = canAddTeamAttendanceRecords.value;

    return [
        {
            id: 'employee',
            meta: { headClass: 'min-w-[11rem]', cellClass: 'align-middle' },
            header: () => h('span', { class: tablePlainHeadClass }, 'Employee'),
            cell: ({ row }) => {
                const r = row.original;

                return h('div', { class: 'flex items-center gap-3 py-0.5' }, [
                    h(
                        Avatar,
                        {
                            class: 'size-9 shrink-0 border border-border/70 bg-muted/30',
                        },
                        {
                            default: () => [
                                h(AvatarImage, {
                                    src: r.employee.avatar_url ?? '',
                                    alt: r.employee.display_name,
                                }),
                                h(
                                    AvatarFallback,
                                    {
                                        class: 'text-[11px] font-medium text-muted-foreground',
                                    },
                                    () =>
                                        employeeInitials(
                                            r.employee.display_name,
                                        ),
                                ),
                            ],
                        },
                    ),
                    h(
                        'div',
                        { class: 'min-w-0 flex-1 flex flex-col gap-0.5' },
                        [
                            h(
                                'span',
                                {
                                    class: 'truncate font-medium text-foreground',
                                },
                                r.employee.display_name,
                            ),
                            h(
                                'span',
                                {
                                    class: 'font-mono text-xs text-muted-foreground',
                                },
                                r.employee.id_number,
                            ),
                        ],
                    ),
                ]);
            },
        },
        {
            id: 'attendance_id',
            meta: {
                headClass: 'min-w-[7rem]',
                cellClass:
                    'align-middle font-mono text-sm tabular-nums text-muted-foreground',
            },
            header: () =>
                h('span', { class: tablePlainHeadClass }, 'Attendance ID'),
            cell: ({ row }) => {
                const r = row.original;
                const profile = r.attendance_id?.trim();
                const ingest = r.ingest_key?.trim();

                return h('span', { class: 'block py-0.5' }, [
                    h(
                        'span',
                        {
                            class: profile
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        },
                        profile || '—',
                    ),
                    ingest
                        ? h(
                              'span',
                              {
                                  class: 'mt-0.5 block text-[11px] leading-tight text-muted-foreground',
                              },
                              ingest,
                          )
                        : null,
                ]);
            },
        },
        {
            id: 'work_date',
            header: () =>
                h(TeamTableSortHeader, {
                    columnTitle: 'Work date',
                    sortDirection: sortDirectionFor('work_date'),
                    onToggleSort: () => toggleSort('work_date'),
                }),
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'text-sm tabular-nums text-foreground' },
                    new Date(
                        `${row.original.work_date}T12:00:00`,
                    ).toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    }),
                ),
        },
        {
            id: 'time_clock',
            meta: {
                headClass: 'min-w-[9rem]',
                cellClass:
                    'align-middle max-w-[16rem] font-mono text-sm tabular-nums whitespace-pre-line leading-snug text-foreground',
            },
            header: () =>
                h(
                    'span',
                    { class: tablePlainHeadClass },
                    'Time (clock in / out)',
                ),
            cell: ({ row }) => {
                const r = row.original;

                return h(
                    'span',
                    { class: 'block py-0.5' },
                    clockInOutDisplay(r.clock_pattern, r.segments),
                );
            },
        },
        {
            id: 'net_time',
            meta: {
                headClass: 'min-w-[6.5rem]',
                cellClass: 'align-middle text-sm tabular-nums text-foreground',
            },
            header: () => h(AttendanceGrossNetColumnHeader, { metric: 'net' }),
            cell: ({ row }) =>
                h(
                    'span',
                    { class: 'block py-0.5 font-mono' },
                    attendanceNetWithinScheduledOverlapDisplay(
                        row.original.segments,
                        row.original.punctuality,
                        {
                            clockPattern: row.original.clock_pattern,
                            unpaidBreakMinutesFromTemplate:
                                row.original.unpaid_break_minutes ?? 0,
                        },
                    ),
                ),
        },
        {
            id: 'punctuality',
            meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
            header: () =>
                h(HrisColumnFilterPopover, {
                    label: 'Punctuality',
                    triggerAriaLabel: punctualityColumnFilterAriaLabel.value,
                    modelValue: props.attendanceTeamFilters.punctuality,
                    options: punctualityHeaderFilterOptions.value,
                    isActive: props.attendanceTeamFilters.punctuality !== 'all',
                    searchable: false,
                    showAllOption: false,
                    showCheckIcon: false,
                    contentClass: 'w-auto min-w-48 p-2',
                    'onUpdate:modelValue': onPunctualityHeaderFilterUpdate,
                }),
            cell: ({ row }) =>
                h(
                    Badge,
                    {
                        variant: 'outline',
                        class: punctualityBadgeClass(row.original.punctuality),
                    },
                    () => punctualityLabel(row.original.punctuality),
                ),
        },
        {
            id: 'status',
            meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
            header: () =>
                h(HrisColumnFilterPopover, {
                    label: 'Status',
                    triggerAriaLabel: statusColumnFilterAriaLabel.value,
                    modelValue: props.attendanceTeamFilters.status,
                    options: attendanceStatusHeaderFilterOptions.value,
                    isActive: props.attendanceTeamFilters.status !== 'all',
                    searchable: false,
                    showAllOption: false,
                    showCheckIcon: false,
                    contentClass: 'w-auto min-w-48 p-2',
                    'onUpdate:modelValue': onAttendanceStatusHeaderFilterUpdate,
                }),
            cell: ({ row }) =>
                h(
                    Badge,
                    {
                        variant: 'outline',
                        class: attendanceStatusBadgeClass(row.original.status),
                    },
                    () => attendanceStatusLabel(row.original.status),
                ),
        },
        {
            id: 'actions',
            meta: {
                headClass: 'w-[72px] text-center',
                cellClass: 'text-center',
            },
            header: () =>
                h(
                    'div',
                    { class: `w-full text-center ${tablePlainHeadClass}` },
                    'Actions',
                ),
            cell: ({ row }) =>
                h(AttendanceTeamRowActionsMenu, {
                    row: row.original,
                    canMutate: mut,
                    onView: openView,
                    onEdit: openEdit,
                    onRemove: openDeleteConfirm,
                }),
        },
    ];
});

const table = useVueTable({
    get data() {
        return props.teamAttendanceDays.data;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
});
</script>

<template>
    <Head title="Team Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Team Attendance
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Monitor team attendance and daily workforce KPIs.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    v-for="card in kpiCards"
                    :key="card.key"
                    type="button"
                    class="group rounded-lg border bg-card p-4 text-left transition hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:outline-none"
                    :class="card.borderClass"
                    @click="openKpiDialog(card.key)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ card.title }}
                        </p>
                        <ListFilter
                            class="size-4 shrink-0 text-muted-foreground transition group-hover:text-foreground"
                        />
                    </div>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="card.valueClass"
                    >
                        {{ card.value }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Click to view employees.
                    </p>
                </button>
            </div>

            <div
                class="flex-none overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-border/60 bg-linear-to-r from-emerald-500/8 via-amber-500/6 to-red-500/8 px-4 py-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Attendance Trend
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ chartMonthLabel }} • {{ halfMonthLabel }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Stacked daily totals for on-time, late, and absent
                            employees.
                        </p>
                    </div>
                    <Select
                        :model-value="halfMonthSelectValue"
                        @update:model-value="
                            (v) =>
                                applyQuery({
                                    chart_half:
                                        v === 'second_half'
                                            ? 'second_half'
                                            : 'first_half',
                                    page: 1,
                                })
                        "
                    >
                        <SelectTrigger
                            class="h-9 w-full min-w-52 justify-between text-start font-normal sm:w-56"
                            aria-label="Filter chart by half month"
                        >
                            <SelectValue placeholder="Select half month" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opt in halfMonthOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="relative z-10 grid gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="font-normal">
                            Period: {{ chartMonthLabel }}
                        </Badge>
                        <Badge variant="outline" class="font-normal">
                            Range: {{ halfMonthLabel }}
                        </Badge>
                        <Badge variant="outline" class="font-normal">
                            On time {{ chartTotals.on_time }}
                        </Badge>
                        <Badge variant="outline" class="font-normal">
                            Late {{ chartTotals.late }}
                        </Badge>
                        <Badge variant="outline" class="font-normal">
                            Absent {{ chartTotals.absent }}
                        </Badge>
                        <Badge variant="outline" class="font-normal">
                            On leave {{ chartTotals.on_leave }}
                        </Badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div
                            v-for="item in chartSeriesLegend"
                            :key="item.label"
                            class="inline-flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :class="item.colorClass"
                            />
                            <span>{{ item.label }}</span>
                        </div>
                    </div>

                    <div
                        class="relative h-[320px] min-w-0 overflow-hidden rounded-lg border border-border/60 bg-background/40 px-2 py-2"
                    >
                        <div class="flex h-full items-end gap-3 px-2 pb-3">
                            <div
                                v-for="row in chartData"
                                :key="row.date"
                                role="button"
                                tabindex="0"
                                class="flex min-w-0 flex-1 cursor-pointer flex-col items-center gap-2 rounded-md px-1 transition hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:outline-none"
                                @click="openChartDetail(row.date)"
                                @keydown.enter.prevent="
                                    openChartDetail(row.date)
                                "
                                @keydown.space.prevent="
                                    openChartDetail(row.date)
                                "
                            >
                                <div
                                    class="flex h-60 w-full max-w-16 flex-col justify-end overflow-hidden rounded-md border border-border/70 bg-muted/15"
                                    :title="`On time: ${row.on_time}, Late: ${row.late}, Absent: ${row.absent}, On leave: ${row.on_leave}`"
                                >
                                    <div
                                        class="bg-red-500"
                                        :style="{
                                            height: chartSegmentHeight(
                                                row.absent,
                                            ),
                                        }"
                                    />
                                    <div
                                        class="bg-sky-500"
                                        :style="{
                                            height: chartSegmentHeight(
                                                row.on_leave,
                                            ),
                                        }"
                                    />
                                    <div
                                        class="bg-amber-500"
                                        :style="{
                                            height: chartSegmentHeight(row.late),
                                        }"
                                    />
                                    <div
                                        class="bg-emerald-500"
                                        :style="{
                                            height: chartSegmentHeight(
                                                row.on_time,
                                            ),
                                        }"
                                    />
                                </div>
                                <span class="text-[11px] text-muted-foreground">
                                    {{ row.date }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative z-0 flex flex-col gap-3">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <InputGroup>
                            <InputGroupAddon align="inline-start">
                                <Search
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="team-attendance-search"
                                :model-value="localSearch"
                                type="search"
                                autocomplete="off"
                                placeholder="Search employee, ID, punch times…"
                                aria-label="Search attendance rows"
                                @update:model-value="onSearchUpdate"
                                @keyup="onSearchKeyup"
                                @change="onSearchCommit"
                                @search="onSearchCommit"
                            />
                        </InputGroup>
                    </div>
                    <div
                        class="flex w-full shrink-0 flex-wrap items-center justify-start gap-2 sm:w-auto sm:justify-end"
                    >
                        <Select
                            :model-value="unitSelectModelValue"
                            @update:model-value="onUnitFilterChange"
                        >
                            <SelectTrigger
                                class="h-9 w-full min-w-56 justify-between text-start font-normal sm:w-56"
                                aria-label="Filter by unit"
                            >
                                <SelectValue placeholder="All units">
                                    <template #default="{ modelValue }">
                                        <HrisUnitSelectTriggerLabel
                                            :select-model-value="modelValue"
                                            :options="branchUnitFilterOptions"
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in branchUnitFilterOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    <div
                                        class="flex min-w-0 items-baseline gap-1"
                                    >
                                        <span class="truncate text-sm">{{
                                            opt.label
                                        }}</span>
                                        <span
                                            v-if="opt.code"
                                            class="shrink-0 font-mono text-xs text-muted-foreground"
                                            >{{ opt.code }}</span
                                        >
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 shrink-0 border-primary/60 text-primary hover:bg-primary/10 hover:text-primary dark:border-primary/70 dark:hover:bg-primary/15"
                            @click="generateTeamDtrExcel"
                        >
                            <FileSpreadsheet
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span class="mr-1">Generate DTR</span>
                        </Button>
                        <Button
                            v-if="canAddTeamAttendanceRecords"
                            type="button"
                            class="h-9 shrink-0"
                            @click="openAdd"
                        >
                            <Plus class="size-4" />
                            <span class="mr-1">Add Entry</span>
                        </Button>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                    <div class="flex min-w-0 flex-wrap gap-2">
                        <Button
                            v-for="opt in recordingStyleChipOptions"
                            :key="opt.value"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-full px-3"
                            :class="
                                attendanceTeamFilters.recording_style ===
                                opt.value
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                    : ''
                            "
                            :aria-pressed="
                                attendanceTeamFilters.recording_style ===
                                opt.value
                            "
                            @click="
                                applyQuery({
                                    recording_style: opt.value,
                                    page: 1,
                                })
                            "
                        >
                            {{ opt.label }}
                        </Button>
                    </div>
                    <div
                        class="flex w-full shrink-0 justify-start sm:w-auto sm:justify-end"
                    >
                        <TeamIndexDateRangePickers
                            v-model:date-from="toolbarDateFromModel"
                            v-model:date-to="toolbarDateToModel"
                        />
                    </div>
                </div>
            </div>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    :empty-message="emptyMessage"
                />
                <HrisServerTablePagination
                    :total="totalRows"
                    :from="fromRow"
                    :to="toRow"
                    :current-page="teamAttendanceDays.current_page"
                    :last-page="lastPage"
                    :per-page="teamAttendanceDays.per_page"
                    :can-previous-page="teamAttendanceDays.current_page > 1"
                    :can-next-page="teamAttendanceDays.current_page < lastPage"
                    @update:per-page="onPerPageChange"
                    @go-first="applyQuery({ page: 1 })"
                    @go-prev="
                        applyQuery({
                            page: Math.max(
                                1,
                                teamAttendanceDays.current_page - 1,
                            ),
                        })
                    "
                    @go-next="
                        applyQuery({
                            page: Math.min(
                                lastPage,
                                teamAttendanceDays.current_page + 1,
                            ),
                        })
                    "
                    @go-last="applyQuery({ page: lastPage })"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>View attendance entry</DialogTitle>
                <DialogDescription>
                    Read-only summary from saved data.
                </DialogDescription>
            </DialogHeader>
            <ScrollArea v-if="viewTarget" :class="dialogViewScrollAreaClass">
                <AttendanceEntryViewContent :row="viewTarget" />
            </ScrollArea>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="viewDialogOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="kpiDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ activeKpiMeta?.title ?? 'KPI employees' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        activeKpiMeta?.subtitle ??
                        'Employees for this KPI result.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="dialogViewScrollAreaClass">
                <div
                    v-if="activeKpiEmployees.length === 0"
                    class="rounded-lg border border-dashed border-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                >
                    No employees matched this KPI for the current workspace and
                    filters.
                </div>
                <div v-else class="grid gap-2">
                    <div
                        v-for="employee in activeKpiEmployees"
                        :key="employee.id"
                        class="flex items-center justify-between gap-3 rounded-lg border border-border/70 bg-muted/20 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ employee.display_name }}
                            </p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ employee.id_number }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="truncate text-xs text-foreground">
                                {{ employee.unit_name }}
                            </p>
                            <p
                                v-if="employee.unit_code"
                                class="font-mono text-[11px] text-muted-foreground"
                            >
                                {{ employee.unit_code }}
                            </p>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="kpiDialogOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="chartDetailDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    Attendance breakdown —
                    {{ selectedChartDetail?.date ?? 'Day' }}
                </DialogTitle>
                <DialogDescription>
                    Dummy drilldown for late time-ins, absences, and on-leave
                    employees.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="dialogViewScrollAreaClass">
                <div
                    v-if="!selectedChartDetail"
                    class="text-sm text-muted-foreground"
                >
                    No detail found.
                </div>

                <div v-else class="grid gap-4">
                    <div
                        class="rounded-lg border border-amber-500/35 bg-amber-500/10 p-3"
                    >
                        <p
                            class="text-sm font-semibold text-amber-700 dark:text-amber-300"
                        >
                            Late ({{ selectedChartDetail.late.length }})
                        </p>

                        <div
                            v-if="selectedChartDetail.late.length === 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            No late employees.
                        </div>

                        <div v-else class="mt-2 grid gap-2">
                            <div
                                v-for="entry in selectedChartDetail.late"
                                :key="`${selectedChartDetail.date}-late-${entry.employee.id}`"
                                class="flex items-center justify-between gap-3 rounded-md border border-amber-500/35 bg-background/60 px-3 py-2"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Avatar
                                        class="size-9 shrink-0 border border-border/70 bg-muted/30"
                                    >
                                        <AvatarImage
                                            :src="entry.employee.avatar_url ?? ''"
                                            :alt="entry.employee.display_name"
                                        />
                                        <AvatarFallback
                                            class="text-[11px] font-medium text-muted-foreground"
                                        >
                                            {{
                                                employeeInitials(
                                                    entry.employee.display_name,
                                                )
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ entry.employee.display_name }}
                                        </p>
                                        <p
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ entry.employee.id_number }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono text-xs text-foreground">
                                        Sched {{ entry.scheduled_in }} · In
                                        {{ entry.actual_in }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Grace: 10m · Late:
                                        {{
                                            lateMinutesWithGrace(
                                                entry.actual_in,
                                                entry.scheduled_in,
                                                10,
                                            )
                                        }}m
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ entry.reason }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-red-500/35 bg-red-500/10 p-3"
                    >
                        <p
                            class="text-sm font-semibold text-red-700 dark:text-red-300"
                        >
                            Absent ({{ selectedChartDetail.absent.length }})
                        </p>

                        <div
                            v-if="selectedChartDetail.absent.length === 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            No absent employees.
                        </div>

                        <div v-else class="mt-2 grid gap-2">
                            <div
                                v-for="entry in selectedChartDetail.absent"
                                :key="`${selectedChartDetail.date}-absent-${entry.employee.id}`"
                                class="flex items-center justify-between gap-3 rounded-md border border-red-500/35 bg-background/60 px-3 py-2"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Avatar
                                        class="size-9 shrink-0 border border-border/70 bg-muted/30"
                                    >
                                        <AvatarImage
                                            :src="entry.employee.avatar_url ?? ''"
                                            :alt="entry.employee.display_name"
                                        />
                                        <AvatarFallback
                                            class="text-[11px] font-medium text-muted-foreground"
                                        >
                                            {{
                                                employeeInitials(
                                                    entry.employee.display_name,
                                                )
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ entry.employee.display_name }}
                                        </p>
                                        <p
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ entry.employee.id_number }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-muted-foreground">
                                        {{ entry.reason }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-sky-500/35 bg-sky-500/10 p-3"
                    >
                        <p
                            class="text-sm font-semibold text-sky-700 dark:text-sky-300"
                        >
                            On leave ({{ selectedChartDetail.on_leave.length }})
                        </p>

                        <div
                            v-if="selectedChartDetail.on_leave.length === 0"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            No on-leave employees.
                        </div>

                        <div v-else class="mt-2 grid gap-2">
                            <div
                                v-for="entry in selectedChartDetail.on_leave"
                                :key="`${selectedChartDetail.date}-leave-${entry.employee.id}`"
                                class="flex items-center justify-between gap-3 rounded-md border border-sky-500/35 bg-background/60 px-3 py-2"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Avatar
                                        class="size-9 shrink-0 border border-border/70 bg-muted/30"
                                    >
                                        <AvatarImage
                                            :src="entry.employee.avatar_url ?? ''"
                                            :alt="entry.employee.display_name"
                                        />
                                        <AvatarFallback
                                            class="text-[11px] font-medium text-muted-foreground"
                                        >
                                            {{
                                                employeeInitials(
                                                    entry.employee.display_name,
                                                )
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ entry.employee.display_name }}
                                        </p>
                                        <p
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ entry.employee.id_number }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-foreground">
                                        {{ entry.leave_type }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ entry.reason }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="chartDetailDialogOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="mutateDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <TooltipProvider :delay-duration="200">
                <DialogHeader>
                    <DialogTitle>{{
                        isEditing
                            ? 'Edit attendance entry'
                            : 'Add attendance entry'
                    }}</DialogTitle>
                    <DialogDescription class="text-pretty">
                        {{ TEAM_ATTENDANCE_FORM_HEADER_TOOLTIP }}
                    </DialogDescription>
                </DialogHeader>
                <ScrollArea v-if="activeDraft" :class="dialogScrollAreaClass">
                    <div class="grid gap-4 px-1 py-1">
                        <p
                            v-if="branchUnitsLoadError"
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            {{ branchUnitsLoadError }}
                        </p>
                        <p
                            v-if="chartBranchId === null"
                            class="text-xs text-destructive"
                        >
                            Select a workspace branch (header) to load units and
                            search employees.
                        </p>
                        <div class="grid gap-2">
                            <Label for="att-unit">Unit</Label>
                            <TeamHrUnitCombobox
                                id="att-unit"
                                :units="branchUnits"
                                :model-value="
                                    activeDraft.organizational_unit_id
                                "
                                :loading="branchUnitsLoading"
                                :disabled="
                                    chartBranchId === null ||
                                    branchUnits.length === 0
                                "
                                placeholder="Search or choose unit…"
                                @update:model-value="onFormUnitChange"
                            />
                            <p
                                v-if="branchUnitsLoading"
                                class="text-xs text-muted-foreground"
                            >
                                Loading units…
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="att-emp">Employee</Label>
                            <TeamHrEmployeeCombobox
                                v-if="activeDraft !== null"
                                id="att-emp"
                                v-model="selectedEmployeeHitModel"
                                :chart-branch-id="chartBranchId"
                                :unit-id="activeDraft.organizational_unit_id"
                                :disabled="
                                    chartBranchId === null ||
                                    activeDraft.organizational_unit_id === null
                                "
                            />
                        </div>
                        <div
                            v-if="employeeAttendanceSetupHint"
                            class="rounded-lg border border-amber-500/35 bg-amber-500/10 px-3 py-2 text-sm text-amber-950 dark:border-amber-400/30 dark:bg-amber-500/12 dark:text-amber-50"
                            role="status"
                        >
                            <span>{{ employeeAttendanceSetupHint }}</span>
                            <Link
                                class="ms-1 font-medium underline underline-offset-2"
                                :href="employeeSchedules()"
                                >Open Employee Schedules</Link
                            >
                            <span class="text-muted-foreground">.</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="att-work-date">Work date</Label>
                                <TeamFormIsoDatePicker
                                    id="att-work-date"
                                    v-model="activeDraft.work_date"
                                    ariaLabel="Work date"
                                />
                            </div>
                            <div class="grid gap-2">
                                <div
                                    class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                                >
                                    <Label for="att-record-status"
                                        >Status</Label
                                    >
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                                aria-label="Explain status"
                                            >
                                                <Info
                                                    class="size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            side="top"
                                            class="max-w-xs text-pretty"
                                        >
                                            {{
                                                TEAM_ATTENDANCE_FORM_STATUS_TOOLTIP
                                            }}
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <div
                                    id="att-record-status"
                                    class="flex min-h-9 flex-col justify-center gap-1 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                                >
                                    <Badge
                                        v-if="activeDraft.employee_id !== null"
                                        variant="outline"
                                        class="w-fit font-normal"
                                        :class="
                                            attendanceStatusBadgeClass(
                                                activeDraft.status,
                                            )
                                        "
                                    >
                                        {{
                                            attendanceStatusLabel(
                                                activeDraft.status,
                                            )
                                        }}
                                    </Badge>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        —
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <div
                                class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                            >
                                <Label for="att-sched-name"
                                    >Work schedule</Label
                                >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                            aria-label="Explain work schedule field"
                                        >
                                            <Info
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="top"
                                        class="max-w-xs text-pretty"
                                    >
                                        {{
                                            TEAM_ATTENDANCE_FORM_WORK_SCHEDULE_TOOLTIP
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <p
                                id="att-sched-name"
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm"
                            >
                                {{
                                    activeDraft.work_schedule_name.trim() !== ''
                                        ? activeDraft.work_schedule_name
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <div
                                class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                            >
                                <Label for="att-external-id"
                                    >Attendance ID</Label
                                >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                            aria-label="Explain attendance ID field"
                                        >
                                            <Info
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="top"
                                        class="max-w-xs text-pretty"
                                    >
                                        {{
                                            TEAM_ATTENDANCE_FORM_PROFILE_ATTENDANCE_ID_TOOLTIP
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <p
                                id="att-external-id"
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono text-sm tabular-nums"
                            >
                                {{ activeDraft.attendance_id.trim() || '—' }}
                            </p>
                        </div>
                        <div
                            v-if="activeDraft.employee_id !== null"
                            class="grid gap-2"
                        >
                            <div
                                class="flex h-6 min-h-6 shrink-0 items-center gap-1.5"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Recording style
                                </p>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                            aria-label="Explain recording style"
                                        >
                                            <Info
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="top"
                                        class="max-w-xs text-pretty"
                                    >
                                        {{
                                            TEAM_ATTENDANCE_FORM_RECORDING_STYLE_TOOLTIP
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Badge variant="outline" class="font-normal">
                                    {{
                                        clockPatternBadgeLabel(
                                            activeDraft.clock_pattern,
                                        )
                                    }}
                                </Badge>
                                <Badge
                                    v-if="activeDraft.is_overnight_schedule"
                                    variant="outline"
                                    class="font-normal"
                                >
                                    Overnight
                                </Badge>
                            </div>
                        </div>
                        <Separator />
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="flex min-h-8 items-center gap-1.5">
                                <p class="text-sm font-medium text-foreground">
                                    Clock times
                                </p>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                            aria-label="Explain clock times fields"
                                        >
                                            <Info
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="top"
                                        class="max-w-xs text-pretty"
                                    >
                                        {{ clockTimesMutateFormTooltip }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <Button
                                v-if="
                                    activeDraft.clock_pattern ===
                                        'split_sessions' &&
                                    !scheduleFieldsLocked
                                "
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8"
                                :disabled="activeDraft.segments.length >= 3"
                                @click="addSessionSegment"
                            >
                                Add session
                            </Button>
                        </div>
                        <div
                            v-for="(seg, idx) in activeDraft.segments"
                            :key="`${seg.label}-${idx}`"
                            class="space-y-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <Label :for="`att-seg-${idx}-label`"
                                    >Segment label</Label
                                >
                                <Button
                                    v-if="
                                        activeDraft.clock_pattern ===
                                            'split_sessions' &&
                                        !scheduleFieldsLocked &&
                                        activeDraft.segments.length > 2 &&
                                        idx === activeDraft.segments.length - 1
                                    "
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-destructive"
                                    @click="removeLastSessionSegment"
                                >
                                    Remove
                                </Button>
                            </div>
                            <Input
                                :id="`att-seg-${idx}-label`"
                                v-model="seg.label"
                                class="h-9"
                                autocomplete="off"
                                :disabled="scheduleFieldsLocked"
                            />
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`att-seg-${idx}-si`"
                                        >Scheduled in</Label
                                    >
                                    <Input
                                        :id="`att-seg-${idx}-si`"
                                        v-model="seg.scheduled_in"
                                        class="h-9 font-mono tabular-nums"
                                        placeholder="08:30"
                                        autocomplete="off"
                                        :disabled="scheduleFieldsLocked"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`att-seg-${idx}-so`"
                                        >Scheduled out</Label
                                    >
                                    <Input
                                        :id="`att-seg-${idx}-so`"
                                        v-model="seg.scheduled_out"
                                        class="h-9 font-mono tabular-nums"
                                        placeholder="17:00"
                                        autocomplete="off"
                                        :disabled="scheduleFieldsLocked"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`att-seg-${idx}-ai`"
                                        >Actual in</Label
                                    >
                                    <Input
                                        :id="`att-seg-${idx}-ai`"
                                        class="h-9 font-mono tabular-nums"
                                        placeholder="Optional"
                                        autocomplete="off"
                                        :model-value="seg.actual_in ?? ''"
                                        @update:model-value="
                                            (v) => setActualIn(idx, v)
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`att-seg-${idx}-ao`"
                                        >Actual out</Label
                                    >
                                    <Input
                                        :id="`att-seg-${idx}-ao`"
                                        class="h-9 font-mono tabular-nums"
                                        placeholder="Optional"
                                        autocomplete="off"
                                        :model-value="seg.actual_out ?? ''"
                                        @update:model-value="
                                            (v) => setActualOut(idx, v)
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-if="formError" class="text-sm text-destructive">
                            {{ formError }}
                        </p>
                    </div>
                </ScrollArea>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="mutateDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        :disabled="employeeAttendanceSetupHint !== null"
                        @click="applyMutate"
                    >
                        {{ isEditing ? 'Save changes' : 'Add Entry' }}
                    </Button>
                </DialogFooter>
            </TooltipProvider>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete attendance entry?</AlertDialogTitle>
                <AlertDialogDescription>
                    This archives the attendance day (soft delete). The row will
                    disappear from this team list after the page reloads.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteTarget = null"
                    >Cancel</AlertDialogCancel
                >
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmDelete"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
