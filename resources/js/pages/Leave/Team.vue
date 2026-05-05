<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import {
    AlertTriangle,
    CalendarDays,
    CalendarPlus,
    CalendarRange,
    Info,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, h, onMounted, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisEmployeeDirectoryUnitAndPositions from '@/components/hris/HrisEmployeeDirectoryUnitAndPositions.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
import TeamFormIsoDatePicker from '@/components/hris/TeamFormIsoDatePicker.vue';
import TeamHrDecisionMakerCombobox from '@/components/hris/TeamHrDecisionMakerCombobox.vue';
import TeamHrEmployeeCombobox from '@/components/hris/TeamHrEmployeeCombobox.vue';
import TeamHrUnitCombobox from '@/components/hris/TeamHrUnitCombobox.vue';
import TeamIndexDateRangePickers from '@/components/hris/TeamIndexDateRangePickers.vue';
import TeamTableSortHeader from '@/components/hris/TeamTableSortHeader.vue';
import TeamTableSubmittedDateFilterHeader from '@/components/hris/TeamTableSubmittedDateFilterHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
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
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';
import {
    fetchTeamHrFormUnits,
    fetchTeamHrLeavePeriodExpansion,
    fetchTeamHrLeaveUsageSummary,
} from '@/lib/teamHrFormApi';
import type {
    TeamHrFormEmployeeHit,
    TeamHrFormUnit,
    TeamHrLeaveUsageSummaryPayload,
} from '@/lib/teamHrFormApi';
import { TEAM_HR_REQUEST_STATUS_LABELS } from '@/lib/teamHrRequestStatusFilter';
import type { TeamHrRequestStatusFilter } from '@/lib/teamHrRequestStatusFilter';
import { formatIsoCalendarDate } from '@/lib/teamRequestDateRange';
import type { SkippedCalendarDay } from '@/pages/Leave/leaveDaysDraftUtils';
import {
    deriveLegacyBoundsFromLeaveDays,
    resolveLeaveDaysForRow,
    formatLeaveUnitsHuman,
    formatLeaveUnitsLabel,
    formatStoredDecimalHuman,
    leaveUnitsTotal,
    sortLeaveDays,
} from '@/pages/Leave/leaveDaysDraftUtils';
import LeaveTeamRowActionsMenu from '@/pages/Leave/LeaveTeamRowActionsMenu.vue';
import type {
    TeamLeaveDayDraft,
    TeamLeaveDraft,
    TeamLeaveRow,
} from '@/pages/Leave/teamLeaveTypes';
import { team as leaveTeam } from '@/routes/leave';
import {
    destroy as teamEmployeeLeavesDestroy,
    store as teamEmployeeLeavesStore,
    update as teamEmployeeLeavesUpdate,
} from '@/routes/leave/team/employee-leaves';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{
    branchContext: { id: number; code: string; name: string } | null;
    can?: { canAddEmployeeTeamLeaveOvertimeEntry?: boolean };
}>();

type LeaveTeamFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    unit_id?: number | null;
    date_from: string;
    date_to: string;
    status: TeamHrRequestStatusFilter;
    employee_id_number: string | null;
    leave_type: string | null;
    approve_from: string | null;
    approve_to: string | null;
    sort: 'dates' | 'status' | 'approve_date';
    direction: 'asc' | 'desc';
};

type TeamLeavePaginator = {
    data: TeamLeaveRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = withDefaults(
    defineProps<{
        teamEmployeeLeaves: TeamLeavePaginator;
        leaveEmployeeFilterOptions: Array<{
            id_number: string;
            display_name: string;
        }>;
        leaveTeamFilters: LeaveTeamFiltersProp;
        leavePolicyOptions: Array<{ code: string; name: string }>;
    }>(),
    {
        teamEmployeeLeaves: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        leaveEmployeeFilterOptions: () => [],
        leaveTeamFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            employee_id_number: null,
            leave_type: null,
            approve_from: null,
            approve_to: null,
            sort: 'dates',
            direction: 'desc',
        }),
        leavePolicyOptions: () => [],
    },
);

const chartBranchId = computed(() => page.props.branchContext?.id ?? null);

const canAddTeamLeaveOvertimeRecords = computed(() =>
    Boolean(page.props.can?.canAddEmployeeTeamLeaveOvertimeEntry),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employee Leaves', href: leaveTeam() },
];

const tablePlainHeadClass = 'font-medium text-muted-foreground';

const statusChipOptions: Array<{
    value: TeamHrRequestStatusFilter;
    label: string;
}> = (['all', 'approved', 'rejected'] as const).map((value) => ({
    value,
    label: TEAM_HR_REQUEST_STATUS_LABELS[value],
}));

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';
const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';
const dialogViewScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

const leaveTypeOptions = computed(() =>
    props.leavePolicyOptions.map((p) => ({
        code: p.code,
        name: p.name,
    })),
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

function normalizedLeaveFilters(): LeaveTeamFiltersProp {
    const f = props.leaveTeamFilters;

    return {
        page: f.page ?? 1,
        per_page: f.per_page ?? 10,
        q: f.q ?? '',
        unit_id: f.unit_id ?? null,
        date_from: f.date_from ?? isoFirstDayOfMonth(new Date()),
        date_to: f.date_to ?? isoLastDayOfMonth(new Date()),
        status: (f.status ?? 'all') as TeamHrRequestStatusFilter,
        employee_id_number: f.employee_id_number ?? null,
        leave_type: f.leave_type ?? null,
        approve_from: f.approve_from ?? null,
        approve_to: f.approve_to ?? null,
        sort: (f.sort ?? 'dates') as LeaveTeamFiltersProp['sort'],
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
        status: TeamHrRequestStatusFilter;
        employee_id_number: string | null;
        leave_type: string | null;
        approve_from: string | null;
        approve_to: string | null;
        sort: LeaveTeamFiltersProp['sort'];
        direction: LeaveTeamFiltersProp['direction'];
    }> = {},
): Record<string, string | number> {
    const merged = { ...normalizedLeaveFilters(), ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.teamEmployeeLeaves.current_page;

    const q: Record<string, string | number> = {
        page,
        per_page: merged.per_page,
        date_from: merged.date_from,
        date_to: merged.date_to,
        status: merged.status,
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

    if (
        merged.employee_id_number !== null &&
        merged.employee_id_number !== ''
    ) {
        q.employee_id_number = merged.employee_id_number;
    }

    if (merged.leave_type !== null && merged.leave_type !== '') {
        q.leave_type = merged.leave_type;
    }

    if (
        merged.approve_from !== null &&
        merged.approve_to !== null &&
        merged.approve_from !== '' &&
        merged.approve_to !== ''
    ) {
        q.approve_from = merged.approve_from;
        q.approve_to = merged.approve_to;
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
        status: TeamHrRequestStatusFilter;
        employee_id_number: string | null;
        leave_type: string | null;
        approve_from: string | null;
        approve_to: string | null;
        sort: LeaveTeamFiltersProp['sort'];
        direction: LeaveTeamFiltersProp['direction'];
    }> = {},
): void {
    router.get(
        leaveTeam.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function toggleSort(column: LeaveTeamFiltersProp['sort']): void {
    const same = props.leaveTeamFilters.sort === column;
    const nextDirection =
        same && props.leaveTeamFilters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDirection, page: 1 });
}

function sortDirectionFor(
    column: LeaveTeamFiltersProp['sort'],
): 'asc' | 'desc' | null {
    if (props.leaveTeamFilters.sort !== column) {
        return null;
    }

    return props.leaveTeamFilters.direction;
}

const {
    localSearch,
    syncFromServerSearch,
    onSearchUpdate,
    onSearchKeyup,
    onSearchCommit,
} = useDebouncedSearchInput({
    initialValue: props.leaveTeamFilters.q ?? '',
    debounceMs: 300,
    onDebouncedSearch: (value) => applyQuery({ q: value, page: 1 }),
});

watch(
    () => props.leaveTeamFilters.q,
    (s) => syncFromServerSearch(s ?? ''),
);

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const UNIT_FILTER_ALL = 'all' as const;

const unitSelectModelValue = computed(() =>
    props.leaveTeamFilters.unit_id != null
        ? String(props.leaveTeamFilters.unit_id)
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
            props.leaveTeamFilters.date_from ?? isoFirstDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_from: iso, page: 1 });
    },
});

const toolbarDateToModel = computed({
    get(): string {
        return props.leaveTeamFilters.date_to ?? isoLastDayOfMonth(new Date());
    },
    set(iso: string): void {
        applyQuery({ date_to: iso, page: 1 });
    },
});

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.teamEmployeeLeaves.current_page - 1,
        pageSize: props.teamEmployeeLeaves.per_page,
    };
    const next =
        typeof updater === 'function'
            ? (updater as (old: PaginationState) => PaginationState)(prev)
            : updater;
    applyQuery({
        page: next.pageIndex + 1,
        per_page: next.pageSize,
    });
}

const viewDialogOpen = ref(false);
const viewTarget = ref<TeamLeaveRow | null>(null);

/** Resolved leave-day list (`leave_days` from API when present; else legacy span expansion). */
const viewTargetDerivedLeaveDays = computed((): TeamLeaveDayDraft[] => {
    if (!viewTarget.value) {
        return [];
    }

    return sortLeaveDays(resolveLeaveDaysForRow(viewTarget.value));
});

const mutateDialogOpen = ref(false);
const confirmMutateOpen = ref(false);
const confirmSummaryLoading = ref(false);
const confirmSummaryError = ref<string | null>(null);
const confirmSummaryData = ref<TeamHrLeaveUsageSummaryPayload | null>(null);
const inlineSummaryLoading = ref(false);
const inlineSummaryError = ref<string | null>(null);
const inlineSummaryData = ref<TeamHrLeaveUsageSummaryPayload | null>(null);
const isEditing = ref(false);
const editId = ref<number | null>(null);
const activeDraft = ref<TeamLeaveDraft | null>(null);
const formError = ref<string | null>(null);
/** Range inputs for expanding counted leave days from work schedule + org holidays. */
const leaveExpandStart = ref('');
const leaveExpandEnd = ref('');
/** Last expand: days not counted (weekends / org holidays). */
const lastExpandSkipped = ref<SkippedCalendarDay[] | null>(null);
const leaveExpandApplying = ref(false);
/** True when directory search explicitly returned no assigned work schedule; blocks Apply before request. */
const employeePickMissingSchedule = ref(false);
/** Popover: pick any calendar day to append to `leave_days`. */
const addAnotherDayOpen = ref(false);
const addAnotherDayIso = ref(isoTodayLocal());

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
            employeePickMissingSchedule.value = false;
            activeDraft.value.employee_id = null;
            activeDraft.value.employee_name = '';
            activeDraft.value.employee_id_number = '';

            return;
        }

        employeePickMissingSchedule.value =
            (value.work_schedule_template_id ?? null) === null;

        activeDraft.value.employee_id = value.id;
        activeDraft.value.employee_name = value.full_name;
        activeDraft.value.employee_id_number = value.employee_number ?? '';
    },
});

const selectedDecisionMakerModel = computed({
    get(): TeamHrFormEmployeeHit | null {
        const d = activeDraft.value;
        if (!d?.approver_employee_id) {
            return null;
        }

        return {
            id: d.approver_employee_id,
            employee_id: d.approver_employee_id,
            employee_number:
                d.approver_id_number.trim() === ''
                    ? null
                    : d.approver_id_number,
            full_name: d.approver_name,
            active_position_title: null,
            avatar_url: null,
        };
    },
    set(value: TeamHrFormEmployeeHit | null) {
        if (!activeDraft.value) {
            return;
        }

        if (value === null) {
            activeDraft.value.approver_employee_id = null;
            activeDraft.value.approver_name = '';
            activeDraft.value.approver_id_number = '';

            return;
        }

        activeDraft.value.approver_employee_id = value.id;
        activeDraft.value.approver_name = value.full_name;
        activeDraft.value.approver_id_number = value.employee_number ?? '';
    },
});

const deleteDialogOpen = ref(false);
const deleteTarget = ref<TeamLeaveRow | null>(null);

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
            'Could not load units for this branch. Try another workspace branch or refresh.';
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
        leaveDaysListExpanded.value = false;
        addAnotherDayOpen.value = false;
        addAnotherDayIso.value = isoTodayLocal();
        if (activeDraft.value && activeDraft.value.leave_days.length > 0) {
            const s = sortLeaveDays(activeDraft.value.leave_days);
            leaveExpandStart.value = s[0].date;
            leaveExpandEnd.value = s[s.length - 1].date;
        } else {
            leaveExpandStart.value = '';
            leaveExpandEnd.value = '';
        }
        lastExpandSkipped.value = null;
    } else {
        confirmMutateOpen.value = false;
        confirmSummaryLoading.value = false;
        confirmSummaryError.value = null;
        confirmSummaryData.value = null;
        inlineSummaryLoading.value = false;
        inlineSummaryError.value = null;
        inlineSummaryData.value = null;
        leaveDaysListExpanded.value = false;
        addAnotherDayOpen.value = false;
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

function statusBadgeClass(status: TeamLeaveRow['status']): string {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300';
}

function statusLabel(status: TeamLeaveRow['status']): string {
    if (status === 'approved') {
        return 'Approved';
    }

    return 'Rejected';
}

function dateLineWithHalf(
    iso: string,
    isHalf: boolean,
    which: 'start' | 'end',
): string {
    const d = new Date(`${iso}T12:00:00`);
    const text = d.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
    if (!isHalf) {
        return text;
    }

    return `${text} (${which === 'start' ? '½ start' : '½ end'})`;
}

function syncDraftLeaveDerivedFields(draft: TeamLeaveDraft): void {
    const bounds = deriveLegacyBoundsFromLeaveDays(draft.leave_days);
    draft.start_date = bounds.start_date;
    draft.end_date = bounds.end_date;
    draft.is_half_day_start = bounds.is_half_day_start;
    draft.is_half_day_end = bounds.is_half_day_end;
    draft.duration_label = formatLeaveUnitsLabel(draft.leave_days);
}

function syncDraftUnitLabels(draft: TeamLeaveDraft): void {
    if (draft.organizational_unit_id === null) {
        if (draft.unit_filter_value.startsWith('u-')) {
            return;
        }

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

function leaveTypeName(code: string): string {
    return leaveTypeOptions.value.find((o) => o.code === code)?.name ?? code;
}

function defaultDraft(): TeamLeaveDraft {
    const today = isoTodayLocal();

    return {
        employee_name: '',
        employee_id_number: '',
        employee_id: null,
        organizational_unit_id: null,
        unit_filter_value: '',
        unit_name: '',
        unit_code: null,
        leave_type_code: '',
        leave_type_name: '',
        start_date: '',
        end_date: '',
        is_half_day_start: false,
        is_half_day_end: false,
        duration_label: formatLeaveUnitsLabel([]),
        status: 'approved',
        submitted_at: today,
        decided_at: today,
        approver_name: '',
        approver_employee_id: null,
        approver_id_number: '',
        reason: '',
        leave_days: [],
    };
}

function cloneToDraft(row: TeamLeaveRow): TeamLeaveDraft {
    return {
        employee_name: row.employee.display_name,
        employee_id_number: row.employee.id_number,
        employee_id: row.employee_record_id ?? null,
        organizational_unit_id: row.organizational_unit_id ?? null,
        unit_filter_value: row.unit_filter_value,
        unit_name: row.unit_name,
        unit_code: row.unit_code,
        leave_type_code: row.leave_type_code,
        leave_type_name: row.leave_type_name,
        start_date: row.start_date,
        end_date: row.end_date,
        is_half_day_start: row.is_half_day_start,
        is_half_day_end: row.is_half_day_end,
        duration_label: row.duration_label,
        status: row.status,
        submitted_at: row.submitted_at.slice(0, 10),
        decided_at: row.decided_at
            ? row.decided_at.slice(0, 10)
            : isoTodayLocal(),
        approver_name: row.approver_name ?? '',
        approver_employee_id: row.approver_employee_id ?? null,
        approver_id_number: row.approver_id_number ?? '',
        reason: row.reason ?? '',
        leave_days: resolveLeaveDaysForRow(row),
    };
}

function onFormUnitChange(unitId: number | null): void {
    if (!activeDraft.value) {
        return;
    }

    employeePickMissingSchedule.value = false;
    activeDraft.value.organizational_unit_id = unitId;
    activeDraft.value.employee_id = null;
    activeDraft.value.employee_name = '';
    activeDraft.value.employee_id_number = '';
    syncDraftUnitLabels(activeDraft.value);
}

function openView(row: TeamLeaveRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

function openAdd(): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error('You do not have permission to add leave records.');

        return;
    }
    isEditing.value = false;
    editId.value = null;
    employeePickMissingSchedule.value = false;
    activeDraft.value = defaultDraft();
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openEdit(row: TeamLeaveRow): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error('You do not have permission to edit leave records.');

        return;
    }
    isEditing.value = true;
    editId.value = row.id;
    employeePickMissingSchedule.value = false;
    activeDraft.value = cloneToDraft(row);
    formError.value = null;
    mutateDialogOpen.value = true;
}

function validateDraft(d: TeamLeaveDraft): string | null {
    if (chartBranchId.value === null) {
        return 'Select a workspace branch (header) before filing.';
    }

    if (d.organizational_unit_id === null) {
        return 'Select a unit.';
    }

    if (d.employee_id === null) {
        return 'Select an employee.';
    }

    if (d.leave_type_code.trim() === '') {
        return 'Select a leave type.';
    }

    if (d.submitted_at.trim() === '') {
        return 'Submitted date is required.';
    }

    if (d.decided_at.trim() === '') {
        return 'Approve date is required.';
    }

    if (d.approver_employee_id === null) {
        return 'Select a decision maker.';
    }

    if (d.leave_days.length === 0) {
        return 'Add at least one leave day.';
    }

    const isoDates = d.leave_days.map((x) => x.date);
    if (new Set(isoDates).size !== isoDates.length) {
        return 'Each calendar date can only appear once.';
    }

    if (
        d.start_date.trim().length >= 10 &&
        d.end_date.trim().length >= 10 &&
        d.start_date > d.end_date
    ) {
        return 'End date must be on or after start date.';
    }

    return null;
}

function buildTeamLeavePayload(d: TeamLeaveDraft): Record<string, unknown> {
    const decided = d.decided_at.trim().slice(0, 10);

    return {
        employee_id: d.employee_id,
        organizational_unit_id: d.organizational_unit_id,
        leave_type_code: d.leave_type_code,
        start_date: d.start_date,
        end_date: d.end_date,
        is_half_day_start: d.is_half_day_start,
        is_half_day_end: d.is_half_day_end,
        status: d.status,
        submitted_at: d.submitted_at.trim().slice(0, 10),
        decided_at: decided === '' ? null : decided,
        approver_employee_id: d.approver_employee_id,
        reason: d.reason?.trim() === '' ? null : (d.reason?.trim() ?? null),
        leave_days: sortLeaveDays(d.leave_days).map(({ date, is_half_day }) => ({
            date,
            is_half_day,
        })),
    };
}

function prepareDraftForSubmit(): boolean {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        formError.value =
            'You do not have permission to add or change leave records.';

        return false;
    }
    if (!activeDraft.value) {
        return false;
    }

    const d = activeDraft.value;
    syncDraftLeaveDerivedFields(d);
    const err = validateDraft(d);
    if (err) {
        formError.value = err;

        return false;
    }

    formError.value = null;
    d.leave_type_name = leaveTypeName(d.leave_type_code);
    syncDraftUnitLabels(d);
    d.submitted_at = d.submitted_at.trim().slice(0, 10);
    d.decided_at = d.decided_at.trim().slice(0, 10);

    return true;
}

function applyMutate(): void {
    if (!prepareDraftForSubmit()) {
        return;
    }

    confirmMutateOpen.value = true;
    void loadConfirmLeaveSummary();
}

function executeMutateSubmit(): void {
    if (!prepareDraftForSubmit()) {
        confirmMutateOpen.value = false;

        return;
    }

    if (!activeDraft.value) {
        confirmMutateOpen.value = false;

        return;
    }

    confirmMutateOpen.value = false;

    const d = activeDraft.value;
    const payload = buildTeamLeavePayload(d);

    if (isEditing.value && editId.value != null) {
        router.patch(
            teamEmployeeLeavesUpdate({ employeeLeave: editId.value }).url,
            payload as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    mutateDialogOpen.value = false;
                    activeDraft.value = null;
                    appToast.success('Leave record updated.');
                },
                onError: () => {
                    formError.value =
                        'Could not save changes. Check the form and try again.';
                },
            },
        );

        return;
    }

    router.post(teamEmployeeLeavesStore.url(), payload as RequestPayload, {
        preserveScroll: true,
        onSuccess: () => {
            mutateDialogOpen.value = false;
            activeDraft.value = null;
            appToast.success('Leave record added.');
        },
        onError: () => {
            formError.value =
                'Could not save changes. Check the form and try again.';
        },
    });
}

/**
 * Replace `leave_days` from the period using weekends off + organization holiday calendar.
 */
async function applyLeavePeriodRange(): Promise<void> {
    if (!activeDraft.value || leaveExpandApplying.value) {
        return;
    }

    const d = activeDraft.value;

    const from = leaveExpandStart.value.trim().slice(0, 10);
    const to = leaveExpandEnd.value.trim().slice(0, 10);
    if (from === '' || to === '') {
        formError.value =
            'Choose both period start and end, then click Apply.';

        return;
    }

    if (from > to) {
        formError.value = 'Period end must be on or after period start.';

        return;
    }

    if (d.organizational_unit_id === null || d.organizational_unit_id < 1) {
        formError.value = 'Choose a unit placement before expanding a leave period.';

        return;
    }

    if (d.employee_id === null) {
        formError.value =
            'Choose an employee before expanding a leave period.';

        return;
    }

    if (employeePickMissingSchedule.value) {
        formError.value =
            'Assign a work schedule to this employee before expanding a leave period.';
        appToast.message(
            'This employee does not have a work schedule assignment yet.',
        );

        return;
    }

    if (chartBranchId.value === null) {
        formError.value = 'Select a workspace branch, then try again.';

        return;
    }

    leaveExpandApplying.value = true;
    formError.value = null;

    try {
        const { counted, skipped } = await fetchTeamHrLeavePeriodExpansion({
            chartBranchId: chartBranchId.value,
            unitId: d.organizational_unit_id,
            employeeId: d.employee_id,
            dateFrom: from,
            dateTo: to,
        });

        activeDraft.value.leave_days = counted;
        syncDraftLeaveDerivedFields(activeDraft.value);
        lastExpandSkipped.value = skipped.length > 0 ? skipped : null;

        if (counted.length === 0) {
            appToast.message(
                "No counted working days in that period for this employee's schedule and organization holidays.",
            );
        }
    } catch (e) {
        const msg =
            e instanceof Error ? e.message : 'Unable to expand the leave period.';
        formError.value = msg;
        appToast.error(msg);
    } finally {
        leaveExpandApplying.value = false;
    }
}

function addPickedLeaveDay(): void {
    if (!activeDraft.value) {
        return;
    }

    const iso = addAnotherDayIso.value.trim().slice(0, 10);
    if (iso.length < 10) {
        return;
    }

    if (activeDraft.value.leave_days.some((row) => row.date === iso)) {
        appToast.error('That date is already in the leave list.');

        return;
    }

    activeDraft.value.leave_days.push({ date: iso, is_half_day: false });
    syncDraftLeaveDerivedFields(activeDraft.value);
    addAnotherDayOpen.value = false;
}

function removeLeaveDayByDate(dateIso: string): void {
    if (!activeDraft.value) {
        return;
    }

    activeDraft.value.leave_days = activeDraft.value.leave_days.filter(
        (row) => row.date !== dateIso,
    );
    syncDraftLeaveDerivedFields(activeDraft.value);
}

function toggleHalfDayForLeaveDate(dateIso: string): void {
    if (!activeDraft.value) {
        return;
    }

    const cur = activeDraft.value.leave_days.find(
        (d) => d.date === dateIso,
    );
    if (cur) {
        cur.is_half_day = !cur.is_half_day;
        syncDraftLeaveDerivedFields(activeDraft.value);
    }
}

/** Collapse long leave-day lists in the form for readability. */
const LEAVE_DAYS_COLLAPSE_AFTER = 8;
const leaveDaysListExpanded = ref(false);

const leaveDaysSorted = computed(() =>
    activeDraft.value
        ? sortLeaveDays(activeDraft.value.leave_days)
        : ([] as TeamLeaveDayDraft[]),
);

const leaveDaysSortedDisplay = computed(() => {
    const sorted = leaveDaysSorted.value;
    if (
        leaveDaysListExpanded.value ||
        sorted.length <= LEAVE_DAYS_COLLAPSE_AFTER
    ) {
        return sorted;
    }

    return sorted.slice(0, LEAVE_DAYS_COLLAPSE_AFTER);
});

const leaveDaysCollapsedMoreCount = computed(() => {
    const n = leaveDaysSorted.value.length;
    if (n <= LEAVE_DAYS_COLLAPSE_AFTER || leaveDaysListExpanded.value) {
        return 0;
    }

    return n - LEAVE_DAYS_COLLAPSE_AFTER;
});

const confirmSubmissionDayCounts = computed(() => {
    const rows = activeDraft.value?.leave_days ?? [];

    let wholeDays = 0;
    let halfDays = 0;

    for (const r of rows) {
        if (r.is_half_day) {
            halfDays++;

            continue;
        }

        wholeDays++;
    }

    return {
        wholeDays,
        halfDays,
        distinctCalendarDays: rows.length,
    };
});

/** After save projection when status is approved (server snapshot excludes edited record when applicable). */
const confirmUsageProjection = computed(() => {
    const snapshot = confirmSummaryData.value?.same_leave_type_code;
    const d = activeDraft.value;

    if (!snapshot || !d || d.status !== 'approved') {
        return null;
    }

    const draftUnits = leaveUnitsTotal(d.leave_days);

    /** When editing an existing approved row, aggregates already omit that row — add back this draft only. */
    const recordBump = isEditing.value ? 0 : 1;

    return {
        unitsMonth: snapshot.approved_leave_units_month + draftUnits,
        unitsYear: snapshot.approved_leave_units_year + draftUnits,
        recordsMonth:
            snapshot.approved_records_distinct_month + recordBump,
        recordsYear:
            snapshot.approved_records_distinct_year + recordBump,
        draftUnitsLabel: formatLeaveUnitsLabel(d.leave_days),
    };
});

/** Policy entitlement line without SQL-style decimals ("15.0000"). */
const confirmPolicyEntitlementPhrase = computed((): string | null => {
    const p = confirmSummaryData.value?.leave_policy;

    if (!p) {
        return null;
    }

    const human = formatStoredDecimalHuman(p.annual_entitlement);

    if (human === null || human === '') {
        return null;
    }

    const unit = p.unit.trim() === '' ? 'units' : p.unit.trim();

    return `${human} ${unit}`;
});

async function loadConfirmLeaveSummary(): Promise<void> {
    const bid = chartBranchId.value;
    const d = activeDraft.value;

    if (bid === null || !d?.employee_id || !d.organizational_unit_id) {
        confirmSummaryData.value = null;
        confirmSummaryError.value =
            'Cannot load usage snapshot until employee and unit are set.';

        return;
    }

    confirmSummaryLoading.value = true;
    confirmSummaryError.value = null;

    try {
        const draftDates = sortLeaveDays(d.leave_days).map((r) => r.date);

        confirmSummaryData.value = await fetchTeamHrLeaveUsageSummary({
            chartBranchId: bid,
            unitId: d.organizational_unit_id,
            employeeId: d.employee_id,
            leaveTypeCode: d.leave_type_code,
            draftDates,
            excludeEmployeeLeaveId:
                isEditing.value && editId.value !== null
                    ? editId.value
                    : null,
        });
    } catch (e) {
        confirmSummaryData.value = null;
        confirmSummaryError.value =
            e instanceof Error ? e.message : 'Unable to load leave usage.';
    } finally {
        confirmSummaryLoading.value = false;
    }
}

async function loadInlineLeaveSummary(): Promise<void> {
    const bid = chartBranchId.value;
    const d = activeDraft.value;

    if (
        bid === null ||
        !d?.employee_id ||
        !d.organizational_unit_id ||
        d.leave_type_code.trim() === ''
    ) {
        inlineSummaryData.value = null;
        inlineSummaryError.value = null;

        return;
    }

    inlineSummaryLoading.value = true;
    inlineSummaryError.value = null;

    try {
        inlineSummaryData.value = await fetchTeamHrLeaveUsageSummary({
            chartBranchId: bid,
            unitId: d.organizational_unit_id,
            employeeId: d.employee_id,
            leaveTypeCode: d.leave_type_code,
            excludeEmployeeLeaveId:
                isEditing.value && editId.value !== null ? editId.value : null,
        });
    } catch (e) {
        inlineSummaryData.value = null;
        inlineSummaryError.value =
            e instanceof Error ? e.message : 'Unable to load leave usage.';
    } finally {
        inlineSummaryLoading.value = false;
    }
}

watch(
    () => [
        mutateDialogOpen.value,
        chartBranchId.value,
        activeDraft.value?.organizational_unit_id ?? null,
        activeDraft.value?.employee_id ?? null,
        activeDraft.value?.leave_type_code ?? '',
        isEditing.value,
        editId.value,
    ],
    () => {
        if (!mutateDialogOpen.value) {
            inlineSummaryLoading.value = false;
            inlineSummaryError.value = null;
            inlineSummaryData.value = null;

            return;
        }
        void loadInlineLeaveSummary();
    },
);

watch(
    () => activeDraft.value?.leave_days,
    () => {
        if (activeDraft.value) {
            syncDraftLeaveDerivedFields(activeDraft.value);
        }
    },
    { deep: true },
);

function openDeleteConfirm(row: TeamLeaveRow): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error('You do not have permission to delete leave records.');

        return;
    }
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
}

function confirmDelete(): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        deleteDialogOpen.value = false;
        deleteTarget.value = null;

        return;
    }
    const row = deleteTarget.value;
    if (!row) {
        return;
    }
    const id = row.id;
    router.delete(teamEmployeeLeavesDestroy({ employeeLeave: id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            if (viewTarget.value?.id === id) {
                viewDialogOpen.value = false;
                viewTarget.value = null;
            }
            appToast.success('Leave record removed.');
        },
        onError: () => {
            appToast.error('Could not delete this record.');
        },
    });
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

const employeeFilterOptions = computed(() =>
    props.leaveEmployeeFilterOptions.map((e) => ({
        value: e.id_number,
        label: e.display_name,
        secondary: e.id_number,
        searchText: `${e.display_name} ${e.id_number}`,
    })),
);

const leaveTypeFilterOptions = computed(() =>
    leaveTypeOptions.value.map((lt) => ({
        value: lt.code,
        label: lt.name,
        secondary: lt.code,
        searchText: `${lt.name} ${lt.code}`,
    })),
);

const totalRows = computed(() => props.teamEmployeeLeaves.total);

const emptyMessage = computed(() => {
    if (props.teamEmployeeLeaves.total === 0) {
        return 'No leave records for this workspace yet. Add a record to see it listed.';
    }

    return 'No leave records match your current filters.';
});

const fromRow = computed(() => props.teamEmployeeLeaves.from);

const toRow = computed(() => props.teamEmployeeLeaves.to);

const lastPage = computed(() => props.teamEmployeeLeaves.last_page);

function setPage(n: number): void {
    const page = Math.min(Math.max(n, 1), lastPage.value);
    applyQuery({ page });
}

const columns: ColumnDef<TeamLeaveRow>[] = [
    {
        id: 'employee',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
        header: () => {
            const raw = normalizedLeaveFilters().employee_id_number;
            const emp =
                raw === null || raw === undefined || raw === '' ? null : raw;

            return h(HrisColumnFilterPopover, {
                label: 'Employee',
                triggerAriaLabel:
                    emp == null
                        ? 'Employee filter: all. Open to choose an employee.'
                        : `Employee filter: ${employeeFilterOptions.value.find((o) => o.value === emp)?.label ?? 'selected'}. Open to change.`,
                modelValue: emp,
                options: employeeFilterOptions.value,
                isActive: emp != null,
                showClear: emp != null,
                clearAriaLabel: 'Clear employee filter',
                allLabel: 'All employees',
                searchPlaceholder: 'Search by name or ID…',
                emptyText: 'No matching employees.',
                'onUpdate:modelValue': (v: string | number | null) => {
                    applyQuery({
                        employee_id_number:
                            v === null || v === '' ? null : String(v),
                        page: 1,
                    });
                },
                onClear: () => {
                    applyQuery({ employee_id_number: null, page: 1 });
                },
            });
        },
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
                                () => employeeInitials(r.employee.display_name),
                            ),
                        ],
                    },
                ),
                h('div', { class: 'min-w-0 flex-1 flex flex-col gap-0.5' }, [
                    h(
                        'span',
                        { class: 'truncate font-medium text-foreground' },
                        r.employee.display_name,
                    ),
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        r.employee.id_number,
                    ),
                ]),
            ]);
        },
    },
    {
        id: 'leave_type',
        meta: { cellClass: 'whitespace-normal' },
        header: () => {
            const raw = normalizedLeaveFilters().leave_type;
            const lt =
                raw === null || raw === undefined || raw === '' ? null : raw;

            return h(HrisColumnFilterPopover, {
                label: 'Leave type',
                triggerAriaLabel:
                    lt == null
                        ? 'Leave type filter: all. Open to choose a type.'
                        : `Leave type filter: ${lt}. Open to change.`,
                modelValue: lt,
                options: leaveTypeFilterOptions.value,
                isActive: lt != null,
                showClear: lt != null,
                clearAriaLabel: 'Clear leave type filter',
                allLabel: 'All types',
                searchPlaceholder: 'Search leave type name or code…',
                emptyText: 'No matching leave types.',
                'onUpdate:modelValue': (v: string | number | null) => {
                    applyQuery({
                        leave_type: v === null || v === '' ? null : String(v),
                        page: 1,
                    });
                },
                onClear: () => {
                    applyQuery({ leave_type: null, page: 1 });
                },
            });
        },
        cell: ({ row }) => {
            const r = row.original;

            return h(
                'div',
                { class: 'flex max-w-[14rem] flex-col gap-0.5 py-0.5' },
                [
                    h(
                        'span',
                        { class: 'text-sm font-medium text-foreground' },
                        r.leave_type_name,
                    ),
                    h(
                        'span',
                        {
                            class: 'font-mono text-xs text-muted-foreground',
                        },
                        r.leave_type_code,
                    ),
                ],
            );
        },
    },
    {
        id: 'dates',
        meta: { cellClass: 'whitespace-normal' },
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Dates',
                sortDirection: sortDirectionFor('dates'),
                onToggleSort: () => toggleSort('dates'),
            }),
        cell: ({ row }) => {
            const r = row.original;

            return h(
                'div',
                { class: 'flex max-w-[14rem] flex-col gap-0.5 text-sm' },
                [
                    h('span', { class: 'tabular-nums text-foreground' }, [
                        dateLineWithHalf(
                            r.start_date,
                            r.is_half_day_start,
                            'start',
                        ),
                    ]),
                    h('span', { class: 'tabular-nums text-muted-foreground' }, [
                        '→ ',
                        dateLineWithHalf(r.end_date, r.is_half_day_end, 'end'),
                    ]),
                ],
            );
        },
    },
    {
        id: 'duration',
        header: () => h('span', { class: tablePlainHeadClass }, 'Duration'),
        cell: ({ row }) => {
            const r = row.original;
            const unitsLabel = formatLeaveUnitsLabel(
                resolveLeaveDaysForRow(r),
            );

            return h(
                'span',
                {
                    class: 'text-sm text-foreground',
                    title: `Span summary: ${r.duration_label}`,
                },
                unitsLabel,
            );
        },
    },
    {
        id: 'status',
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Status',
                sortDirection: sortDirectionFor('status'),
                onToggleSort: () => toggleSort('status'),
            }),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: statusBadgeClass(row.original.status),
                },
                () => statusLabel(row.original.status),
            ),
    },
    {
        id: 'approve_date',
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'Approve date',
                modelValue:
                    props.leaveTeamFilters.approve_from &&
                    props.leaveTeamFilters.approve_to
                        ? {
                              from: props.leaveTeamFilters.approve_from,
                              to: props.leaveTeamFilters.approve_to,
                          }
                        : null,
                enableSort: true,
                sortDirection: sortDirectionFor('approve_date'),
                onToggleSort: () => toggleSort('approve_date'),
                'onUpdate:modelValue': (
                    v: { from: string; to: string } | null,
                ) => {
                    if (v === null) {
                        applyQuery({
                            approve_from: null,
                            approve_to: null,
                            page: 1,
                        });

                        return;
                    }

                    applyQuery({
                        approve_from: v.from,
                        approve_to: v.to,
                        page: 1,
                    });
                },
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-muted-foreground' },
                row.original.decided_at
                    ? formatIsoCalendarDate(row.original.decided_at)
                    : '—',
            ),
    },
    {
        id: 'actions',
        meta: { headClass: 'w-[72px] text-center', cellClass: 'text-center' },
        header: () =>
            h(
                'div',
                { class: `w-full text-center ${tablePlainHeadClass}` },
                'Actions',
            ),
        cell: ({ row }) =>
            h(LeaveTeamRowActionsMenu, {
                row: row.original,
                canMutate: canAddTeamLeaveOvertimeRecords,
                onView: openView,
                onEdit: openEdit,
                onRemove: openDeleteConfirm,
            }),
    },
];

const table = useVueTable({
    get data() {
        return props.teamEmployeeLeaves.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.teamEmployeeLeaves.last_page,
    rowCount: props.teamEmployeeLeaves.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.teamEmployeeLeaves.current_page - 1,
                pageSize: props.teamEmployeeLeaves.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="Employee Leaves" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Employee Leaves
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    HR-entered leave records (not an employee request queue).
                    The date range shows rows whose leave dates
                    <span class="font-medium text-foreground">overlap</span>
                    the range (inclusive). Rows load from the server for your
                    workspace branch.
                </p>
            </div>

            <div class="flex flex-col gap-3">
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
                                id="employee-leaves-search"
                                :model-value="localSearch"
                                type="search"
                                autocomplete="off"
                                placeholder="Search name, ID, type, or notes…"
                                aria-label="Search leave requests"
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
                            v-if="canAddTeamLeaveOvertimeRecords"
                            type="button"
                            class="h-9 shrink-0"
                            @click="openAdd"
                        >
                            <Plus class="size-4" />
                            <span class="mr-1">Add Record</span>
                        </Button>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                >
                    <div class="flex min-w-0 flex-wrap gap-2">
                        <Button
                            v-for="opt in statusChipOptions"
                            :key="opt.value"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-full px-3"
                            :class="
                                leaveTeamFilters.status === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                    : ''
                            "
                            :aria-pressed="leaveTeamFilters.status === opt.value"
                            @click="
                                applyQuery({ status: opt.value, page: 1 })
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
                    :current-page="teamEmployeeLeaves.current_page"
                    :last-page="lastPage"
                    :per-page="teamEmployeeLeaves.per_page"
                    :can-previous-page="teamEmployeeLeaves.current_page > 1"
                    :can-next-page="teamEmployeeLeaves.current_page < lastPage"
                    @update:per-page="onPerPageChange"
                    @go-first="setPage(1)"
                    @go-prev="setPage(teamEmployeeLeaves.current_page - 1)"
                    @go-next="setPage(teamEmployeeLeaves.current_page + 1)"
                    @go-last="setPage(lastPage)"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>View Leave Record</DialogTitle>
                <DialogDescription>
                    Read-only summary from saved data.
                </DialogDescription>
            </DialogHeader>
            <TooltipProvider :delay-duration="200">
                <ScrollArea :class="dialogViewScrollAreaClass">
                <div
                    v-if="viewTarget"
                    class="grid gap-3 px-2 py-2 text-sm sm:grid-cols-2"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Employee
                        </p>
                        <div
                            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <span class="text-foreground">{{
                                viewTarget.employee.display_name
                            }}</span>
                            <span
                                class="font-mono text-xs text-muted-foreground tabular-nums"
                            >
                                {{ viewTarget.employee.id_number }}
                            </span>
                        </div>
                    </div>
                    <HrisEmployeeDirectoryUnitAndPositions
                        :unit-name="viewTarget.unit_name"
                        :unit-code="viewTarget.unit_code"
                        :unit-directory-type="
                            viewTarget.unit_type ?? 'Organizational unit'
                        "
                        :unit-directory-color="
                            viewTarget.unit_type_color ?? null
                        "
                        :placement-is-primary="
                            viewTarget.unit_is_primary ?? false
                        "
                        :positions="viewTarget.positions ?? []"
                    />
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Leave type
                        </p>
                        <div
                            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <span class="text-foreground">{{
                                viewTarget.leave_type_name
                            }}</span>
                            <span
                                class="font-mono text-xs text-muted-foreground tabular-nums"
                            >
                                {{ viewTarget.leave_type_code }}
                            </span>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Start
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                        >
                            {{
                                dateLineWithHalf(
                                    viewTarget.start_date,
                                    viewTarget.is_half_day_start,
                                    'start',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            End
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                        >
                            {{
                                dateLineWithHalf(
                                    viewTarget.end_date,
                                    viewTarget.is_half_day_end,
                                    'end',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <div class="flex items-center gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Leave units
                            </p>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-6 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                                        aria-label="How leave units are shown"
                                    >
                                        <Info
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="top"
                                    class="max-w-sm text-pretty"
                                >
                                    <p>
                                        <span class="font-medium">
                                            Span summary (saved):
                                        </span>
                                        {{ viewTarget.duration_label }}.
                                    </p>
                                    <p class="mt-2">
                                        Units here are reconstructed from the
                                        saved start and end (every calendar day
                                        in range). When per-day rows are stored
                                        and returned by the API, this will match
                                        exactly.
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                        <div
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <p class="font-medium text-foreground">
                                {{
                                    formatLeaveUnitsLabel(
                                        viewTargetDerivedLeaveDays,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Counted days
                        </p>
                        <div
                            class="flex flex-wrap gap-1.5 rounded-md border border-border/50 bg-muted/15 px-3 py-2.5"
                        >
                            <Badge
                                v-for="row in viewTargetDerivedLeaveDays"
                                :key="`view-day-${row.date}`"
                                variant="secondary"
                                class="max-w-full gap-1 tabular-nums font-normal"
                            >
                                <span class="truncate">{{
                                    formatCalendarTriggerFromIsoYmd(row.date)
                                }}</span>
                                <span
                                    v-if="row.is_half_day"
                                    class="shrink-0 text-muted-foreground"
                                    >· ½</span
                                >
                            </Badge>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Decision maker
                        </p>
                        <div
                            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm"
                        >
                            <template
                                v-if="
                                    viewTarget.approver_name ||
                                    (
                                        viewTarget.approver_id_number ?? ''
                                    ).trim() !== ''
                                "
                            >
                                <span
                                    v-if="viewTarget.approver_name"
                                    class="text-foreground"
                                >
                                    {{ viewTarget.approver_name }}
                                </span>
                                <span
                                    v-if="
                                        (
                                            viewTarget.approver_id_number ?? ''
                                        ).trim() !== ''
                                    "
                                    class="font-mono text-xs text-muted-foreground tabular-nums"
                                >
                                    {{
                                        (
                                            viewTarget.approver_id_number ?? ''
                                        ).trim()
                                    }}
                                </span>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Status
                        </p>
                        <div
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <Badge
                                variant="outline"
                                :class="statusBadgeClass(viewTarget.status)"
                            >
                                {{ statusLabel(viewTarget.status) }}
                            </Badge>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Submitted
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                            >
                                {{
                                    formatIsoCalendarDate(
                                        viewTarget.submitted_at,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Approve date
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                            >
                                {{
                                    viewTarget.decided_at
                                        ? formatIsoCalendarDate(
                                              viewTarget.decided_at,
                                          )
                                        : '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Reason
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 whitespace-pre-wrap"
                        >
                            {{ viewTarget.reason ?? '—' }}
                        </p>
                    </div>
                </div>
                </ScrollArea>
            </TooltipProvider>
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

    <Dialog v-model:open="mutateDialogOpen">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    isEditing ? 'Edit Leave Record' : 'Add Leave Record'
                }}</DialogTitle>
                <DialogDescription>
                    Pick explicit leave days (with optional half-days). Expand a
                    date range using weekends off and your organization&apos;s
                    holiday calendar—skipped days appear under Not counted below.
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
                    <p
                        v-if="isEditing"
                        class="rounded-md border border-border/60 bg-muted/20 px-3 py-2 text-xs text-muted-foreground"
                    >
                        Days below are filled from the saved start/end range
                        (every calendar day). Adjust with
                        <span class="font-medium text-foreground">Apply</span> or
                        <span class="font-medium text-foreground">Add date</span
                        >.
                    </p>
                    <div class="grid gap-2">
                        <Label for="lt-unit">Unit</Label>
                        <TeamHrUnitCombobox
                            id="lt-unit"
                            :units="branchUnits"
                            :model-value="activeDraft.organizational_unit_id"
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
                        <Label for="lt-emp">Employee</Label>
                        <TeamHrEmployeeCombobox
                            v-if="activeDraft !== null"
                            id="lt-emp"
                            v-model="selectedEmployeeHitModel"
                            :chart-branch-id="chartBranchId"
                            :unit-id="activeDraft.organizational_unit_id"
                            :disabled="
                                chartBranchId === null ||
                                activeDraft.organizational_unit_id === null
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="lt-type">Leave type</Label>
                        <Select
                            :model-value="activeDraft.leave_type_code"
                            @update:model-value="
                                (v: AcceptableValue) => {
                                    if (
                                        !activeDraft ||
                                        v == null ||
                                        typeof v === 'object'
                                    ) {
                                        return;
                                    }

                                    activeDraft.leave_type_code = String(v);
                                    activeDraft.leave_type_name = leaveTypeName(
                                        activeDraft.leave_type_code,
                                    );
                                }
                            "
                        >
                            <SelectTrigger id="lt-type" class="h-9 w-full">
                                <SelectValue placeholder="Select leave type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="lt in leaveTypeOptions"
                                    :key="lt.code"
                                    :value="lt.code"
                                >
                                    {{ lt.code }} — {{ lt.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="inlineSummaryLoading"
                            class="text-xs text-muted-foreground"
                        >
                            Loading approved usage…
                        </p>
                        <p
                            v-else-if="inlineSummaryError"
                            class="text-xs text-destructive"
                        >
                            {{ inlineSummaryError }}
                        </p>
                        <div
                            v-else-if="inlineSummaryData && activeDraft.leave_type_code.trim() !== ''"
                            class="grid gap-3 rounded-md border border-border/60 bg-muted/20 p-3 sm:grid-cols-3"
                        >
                            <div class="grid gap-1.5">
                                <div
                                    class="flex h-5 items-center gap-1"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        This Year
                                    </p>
                                    <TooltipProvider :delay-duration="200">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="inline-flex size-4 shrink-0 items-center justify-center text-muted-foreground hover:text-foreground"
                                                    aria-label="Entitlement compared with approved leave usage"
                                                >
                                                    <Info class="size-3.5" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                side="top"
                                                align="end"
                                                class="max-w-xs text-xs leading-relaxed"
                                            >
                                                <p>
                                                    Year view compares the
                                                    policy annual entitlement
                                                    against approved rendered
                                                    leave.
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <div
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium tabular-nums text-foreground"
                                >
                                    {{
                                        formatStoredDecimalHuman(
                                            inlineSummaryData.leave_policy
                                                ?.annual_entitlement,
                                        ) ?? '—'
                                    }}
                                    &gt;
                                    {{
                                        formatLeaveUnitsHuman(
                                            inlineSummaryData.same_leave_type_code
                                                .approved_leave_units_year,
                                        )
                                    }}
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div
                                    class="flex h-5 items-center gap-1"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        This Month
                                    </p>
                                    <span
                                        aria-hidden="true"
                                        class="inline-flex size-4 shrink-0 opacity-0"
                                    />
                                </div>
                                <div
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium tabular-nums text-foreground"
                                >
                                    {{
                                        formatLeaveUnitsHuman(
                                            inlineSummaryData.same_leave_type_code
                                                .approved_leave_units_month,
                                        )
                                    }}
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <div
                                    class="flex h-5 items-center gap-1"
                                >
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        This Week
                                    </p>
                                    <span
                                        aria-hidden="true"
                                        class="inline-flex size-4 shrink-0 opacity-0"
                                    />
                                </div>
                                <div
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium tabular-nums text-foreground"
                                >
                                    {{
                                        formatLeaveUnitsHuman(
                                            inlineSummaryData.same_leave_type_code
                                                .approved_leave_units_week,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4">
                        <div>
                            <p class="text-sm font-medium text-foreground">
                                Leave days counted
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Total:
                                <span class="font-medium text-foreground">{{
                                    formatLeaveUnitsLabel(
                                        activeDraft.leave_days,
                                    )
                                }}</span>
                                <span class="text-muted-foreground">
                                    <template
                                        v-if="
                                            activeDraft.start_date.trim()
                                                .length >= 10 &&
                                            activeDraft.end_date.trim()
                                                .length >= 10
                                        "
                                    >
                                        (legacy span
                                        {{
                                            formatIsoCalendarDate(
                                                activeDraft.start_date,
                                            )
                                        }}
                                        –
                                        {{
                                            formatIsoCalendarDate(
                                                activeDraft.end_date,
                                            )
                                        }})
                                    </template>
                                    <template v-else>
                                        Choose a period and Apply or add dates
                                        - min/max dates appear here.
                                    </template></span
                                >
                            </p>
                        </div>
                        <div
                            class="space-y-3 rounded-lg border border-border/50 bg-muted/20 p-3 sm:p-4 dark:bg-muted/15"
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="lt-expand-from"
                                        >Period start</Label
                                    >
                                    <TeamFormIsoDatePicker
                                        id="lt-expand-from"
                                        v-model="leaveExpandStart"
                                        :max-iso="leaveExpandEnd"
                                        ariaLabel="Leave period start"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="lt-expand-to">Period end</Label>
                                    <TeamFormIsoDatePicker
                                        id="lt-expand-to"
                                        v-model="leaveExpandEnd"
                                        :min-iso="leaveExpandStart"
                                        ariaLabel="Leave period end"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                class="w-full gap-2"
                                :disabled="
                                    leaveExpandApplying ||
                                    chartBranchId === null ||
                                    !activeDraft ||
                                    activeDraft.organizational_unit_id ===
                                        null ||
                                    activeDraft.organizational_unit_id < 1
                                "
                                @click="applyLeavePeriodRange"
                            >
                                <Spinner
                                    v-if="leaveExpandApplying"
                                    class="size-4 shrink-0"
                                />
                                <CalendarRange
                                    v-else
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{
                                    leaveExpandApplying
                                        ? 'Expanding period…'
                                        : 'Apply'
                                }}
                            </Button>
                            <p
                                v-if="
                                    lastExpandSkipped &&
                                    lastExpandSkipped.length > 0
                                "
                                class="text-xs text-muted-foreground"
                            >
                                Last Apply excluded
                                {{ lastExpandSkipped.length }} date(s).
                            </p>
                            <div
                                v-if="
                                    lastExpandSkipped &&
                                    lastExpandSkipped.length
                                "
                                class="grid gap-2 border-t border-border/50 pt-3"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Not counted (last expand preview)
                                </p>
                                <div
                                    class="grid max-h-40 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
                                >
                                    <Badge
                                        v-for="sk in lastExpandSkipped"
                                        :key="sk.date"
                                        variant="outline"
                                        class="h-auto w-full min-w-0 max-w-full items-start justify-start gap-2 overflow-visible whitespace-normal rounded-md border-border/60 bg-background/80 py-2 pl-2.5 pr-2.5 text-left text-sm font-normal text-foreground text-pretty shadow-xs dark:bg-input/20"
                                    >
                                        <CalendarDays
                                            class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 leading-snug">
                                            <span
                                                class="font-medium tabular-nums text-foreground"
                                            >
                                                {{
                                                    formatCalendarTriggerFromIsoYmd(
                                                        sk.date,
                                                    )
                                                }}
                                            </span>
                                            <span class="text-muted-foreground">
                                                - {{ sk.reason }}
                                            </span>
                                        </span>
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-border/50 bg-muted/20 p-3 sm:p-4 dark:bg-muted/15"
                        >
                            <div class="grid gap-2">
                                <div
                                    class="flex w-full flex-wrap items-center justify-between gap-2"
                                >
                                    <p
                                        class="text-sm font-medium text-foreground"
                                    >
                                        Counted dates
                                    </p>
                                    <div
                                        class="flex flex-wrap items-center justify-end gap-2"
                                    >
                                        <Button
                                            v-if="
                                                leaveDaysCollapsedMoreCount > 0
                                            "
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-muted-foreground"
                                            @click="
                                                leaveDaysListExpanded =
                                                    !leaveDaysListExpanded
                                            "
                                        >
                                            {{
                                                leaveDaysListExpanded
                                                    ? 'Show fewer'
                                                    : `Show ${leaveDaysCollapsedMoreCount} more`
                                            }}
                                        </Button>
                                        <Popover v-model:open="addAnotherDayOpen">
                                            <PopoverTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="shrink-0 gap-1.5 border-primary/45 bg-primary/5 text-primary hover:border-primary/70 hover:bg-primary/10 hover:text-primary"
                                                >
                                                    <CalendarPlus
                                                        class="size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    Add date
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                class="w-[min(100vw-2rem,20rem)] p-3"
                                                align="end"
                                            >
                                                <div class="grid gap-3">
                                                    <div class="grid gap-1.5">
                                                        <Label
                                                            for="lt-add-another-day"
                                                            class="text-sm"
                                                            >Pick a date</Label
                                                        >
                                                        <TeamFormIsoDatePicker
                                                            id="lt-add-another-day"
                                                            v-model="
                                                                addAnotherDayIso
                                                            "
                                                            ariaLabel="Date to add to leave list"
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        class="w-full"
                                                        @click="
                                                            addPickedLeaveDay
                                                        "
                                                    >
                                                        Add to list
                                                    </Button>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                </div>
                                <div
                                    v-if="leaveDaysSorted.length === 0"
                                    class="rounded-md border border-dashed border-border/70 bg-background/50 px-3 py-6 text-center text-sm text-muted-foreground dark:bg-background/30"
                                >
                                    No days yet. Set the period above and click
                                    Apply, or use Add date.
                                </div>
                                <div
                                    v-else
                                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="row in leaveDaysSortedDisplay"
                                        :key="row.date"
                                        class="flex min-w-0 items-center gap-2 rounded-md border border-input bg-background px-3 py-2 shadow-xs dark:bg-input/30"
                                    >
                                        <CalendarDays
                                            class="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm tabular-nums text-foreground"
                                        >
                                            {{
                                                formatCalendarTriggerFromIsoYmd(
                                                    row.date,
                                                )
                                            }}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="h-8 min-w-8 shrink-0 px-2 font-medium tabular-nums"
                                            :aria-pressed="row.is_half_day"
                                            :aria-label="`Half day on ${row.date}`"
                                            title="Half day"
                                            :class="
                                                row.is_half_day
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : ''
                                            "
                                            @click="
                                                toggleHalfDayForLeaveDate(
                                                    row.date,
                                                )
                                            "
                                        >
                                            ½
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                            :aria-label="`Remove ${row.date}`"
                                            @click="
                                                removeLeaveDayByDate(row.date)
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="lt-decision-maker">Decision maker</Label>
                        <TeamHrDecisionMakerCombobox
                            id="lt-decision-maker"
                            v-model="selectedDecisionMakerModel"
                            :chart-branch-id="chartBranchId"
                            :disabled="chartBranchId === null"
                            placeholder="Search by name or employee ID…"
                        />
                        <p class="text-xs text-muted-foreground">
                            Type at least 2 letters to search active employees
                            in this branch.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="lt-submitted">Submitted</Label>
                            <TeamFormIsoDatePicker
                                id="lt-submitted"
                                v-model="activeDraft.submitted_at"
                                ariaLabel="Submitted date"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lt-approve">Approve date</Label>
                            <TeamFormIsoDatePicker
                                id="lt-approve"
                                v-model="activeDraft.decided_at"
                                ariaLabel="Approve date"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="lt-status">Status</Label>
                        <Select
                            :model-value="activeDraft.status"
                            @update:model-value="
                                (v: unknown) => {
                                    if (!activeDraft) {
                                        return;
                                    }

                                    activeDraft.status =
                                        v as TeamLeaveRow['status'];
                                }
                            "
                        >
                            <SelectTrigger id="lt-status" class="h-9 w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="approved"
                                    >Approved</SelectItem
                                >
                                <SelectItem value="rejected"
                                    >Rejected</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="lt-reason" :class="optionalLabelRowClass">
                            Reason
                            <Badge variant="outline">Optional</Badge>
                        </Label>
                        <Textarea
                            id="lt-reason"
                            :model-value="activeDraft.reason ?? ''"
                            rows="3"
                            class="resize-y"
                            @update:model-value="
                                (v: string | number) => {
                                    if (!activeDraft) {
                                        return;
                                    }

                                    const s = String(v);
                                    activeDraft.reason =
                                        s.trim() === '' ? null : s;
                                }
                            "
                        />
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
                    v-if="canAddTeamLeaveOvertimeRecords"
                    type="button"
                    @click="applyMutate"
                    >Review &amp; confirm</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="confirmMutateOpen">
        <DialogContent class="gap-5 sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Confirm leave record</DialogTitle>
                <DialogDescription class="text-sm leading-relaxed">
                    <span>
                        Quick check before saving. Totals below use approved leave
                        counted by half or full working days only.
                        &nbsp;<TooltipProvider
                            v-if="confirmSummaryData"
                            :delay-duration="200"
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-block align-middle text-muted-foreground [-webkit-tap-highlight-color:transparent] hover:text-foreground focus-visible:ring-ring rounded-sm p-0.5 leading-none focus-visible:ring-2 focus-visible:outline-none"
                                        aria-label="Snapshot details"
                                    >
                                        <Info
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="bottom"
                                    align="start"
                                    class="max-w-xs text-xs leading-relaxed"
                                >
                                    <p>
                                        Snapshot date
                                        {{ confirmSummaryData.reference.as_of }}
                                        ({{ confirmSummaryData.reference.tz }}).
                                        This draft is excluded from the “approved”
                                        totals until you save; “after save”
                                        appears only when status is Approved.
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </span>
                </DialogDescription>
            </DialogHeader>
            <div
                v-if="confirmSummaryLoading"
                class="flex items-center justify-center gap-3 py-10 text-muted-foreground"
            >
                <Spinner class="size-5 shrink-0" aria-hidden="true" />
                <span class="text-sm">Loading usage…</span>
            </div>
            <Alert v-else-if="confirmSummaryError" variant="destructive">
                <AlertTriangle aria-hidden="true" class="size-4" />
                <AlertTitle>Could not load usage</AlertTitle>
                <AlertDescription>{{ confirmSummaryError }}</AlertDescription>
            </Alert>
            <ScrollArea
                v-if="activeDraft && !confirmSummaryLoading"
                class="max-h-[min(68vh,440px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0"
            >
                <div class="grid gap-5 pb-1 text-sm leading-relaxed">
                    <Alert
                        v-if="
                            confirmSummaryData &&
                                confirmSummaryData.overlaps.length > 0
                        "
                        variant="destructive"
                    >
                        <AlertTriangle
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <AlertTitle>Overlapping approved leave</AlertTitle>
                        <AlertDescription class="space-y-2">
                            <p>
                                Some dates already have approved counted leave.
                                Fix the days or resolve the conflict before saving.
                            </p>
                            <ul class="max-h-32 list-disc gap-1 overflow-y-auto ps-4 text-xs">
                                <li
                                    v-for="hit in confirmSummaryData.overlaps"
                                    :key="`${hit.date}-${hit.employee_leave_id}`"
                                    class="tabular-nums"
                                >
                                    <span class="font-medium">{{ hit.date }}</span>
                                    — {{ formatIsoCalendarDate(hit.date) }} ·
                                    {{ hit.leave_type_code }} ({{
                                        hit.leave_type_name
                                    }}) · #{{ hit.employee_leave_id }}
                                </li>
                            </ul>
                        </AlertDescription>
                    </Alert>

                    <dl
                        class="divide-y divide-border/60 rounded-lg border border-border/70"
                    >
                        <div
                            class="flex justify-between gap-3 px-4 py-3 first:pt-3"
                        >
                            <dt class="text-muted-foreground">Employee</dt>
                            <dd class="text-end font-medium">
                                {{ activeDraft.employee_name || '—' }}
                                <span
                                    v-if="
                                        activeDraft.employee_id_number.trim() !== ''
                                    "
                                    class="mt-0.5 block text-xs font-normal tabular-nums text-muted-foreground"
                                    >{{ activeDraft.employee_id_number }}</span
                                >
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-4 py-3">
                            <dt class="text-muted-foreground">Unit</dt>
                            <dd class="text-end">
                                {{
                                    activeDraft.unit_name ||
                                    (activeDraft.organizational_unit_id !== null
                                        ? `Unit #${activeDraft.organizational_unit_id}`
                                        : '—')
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-4 py-3">
                            <dt class="text-muted-foreground">Leave type</dt>
                            <dd class="text-end font-medium">
                                {{ activeDraft.leave_type_code }} —
                                {{ activeDraft.leave_type_name }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-4 py-3">
                            <dt class="text-muted-foreground">Status</dt>
                            <dd class="text-end">
                                {{ statusLabel(activeDraft.status) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 px-4 py-3">
                            <dt class="text-muted-foreground">This filing</dt>
                            <dd class="font-semibold tabular-nums text-end">
                                {{
                                    formatLeaveUnitsLabel(activeDraft.leave_days)
                                }}
                            </dd>
                        </div>
                        <div
                            class="px-4 py-3 text-xs text-muted-foreground"
                        >
                            <span class="text-foreground">{{
                                confirmSubmissionDayCounts.wholeDays
                            }}</span>
                            full day(s),
                            <span class="text-foreground">{{
                                confirmSubmissionDayCounts.halfDays
                            }}</span>
                            half ·
                            <span class="text-foreground">{{
                                confirmSubmissionDayCounts.distinctCalendarDays
                            }}</span>
                            dated row(s)
                        </div>
                    </dl>

                    <section
                        v-if="confirmSummaryData"
                        class="space-y-3 rounded-lg border border-border/70 bg-muted/20 px-4 py-4"
                        aria-label="Approved usage snapshot"
                    >
                        <h3 class="text-foreground font-medium leading-snug">
                            Approved usage
                            {{
                                confirmSummaryData.leave_policy?.code ??
                                activeDraft.leave_type_code
                            }}
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-muted-foreground">
                                    This month —
                                    {{
                                        confirmSummaryData.reference.month_label
                                    }}
                                </dt>
                                <dd class="text-foreground">
                                    <span class="tabular-nums font-medium">{{
                                        formatLeaveUnitsHuman(
                                            confirmSummaryData
                                                .same_leave_type_code
                                                .approved_leave_units_month,
                                        )
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        days</span
                                    >
                                    <span
                                        v-if="confirmUsageProjection"
                                        class="text-muted-foreground"
                                    >
                                        →
                                        <span class="text-foreground font-medium">{{
                                            formatLeaveUnitsHuman(
                                                confirmUsageProjection.unitsMonth,
                                            )
                                        }}</span>
                                        after save
                                    </span>
                                </dd>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-muted-foreground">
                                    Calendar year
                                    {{ confirmSummaryData.reference.year }}
                                </dt>
                                <dd class="text-foreground">
                                    <span class="tabular-nums font-medium">{{
                                        formatLeaveUnitsHuman(
                                            confirmSummaryData
                                                .same_leave_type_code
                                                .approved_leave_units_year,
                                        )
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        days</span
                                    >
                                    <span
                                        v-if="confirmUsageProjection"
                                        class="text-muted-foreground"
                                    >
                                        →
                                        <span class="text-foreground font-medium">{{
                                            formatLeaveUnitsHuman(
                                                confirmUsageProjection.unitsYear,
                                            )
                                        }}</span>
                                        after save
                                    </span>
                                </dd>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Filings counted:
                                {{
                                    confirmSummaryData.same_leave_type_code
                                        .approved_records_distinct_month
                                }}
                                this month ·
                                {{
                                    confirmSummaryData.same_leave_type_code
                                        .approved_records_distinct_year
                                }}
                                this year (distinct approvals).
                                <template v-if="confirmUsageProjection">
                                    This filing adds
                                    <span class="text-foreground font-medium">{{
                                        confirmUsageProjection.draftUnitsLabel
                                    }}</span
                                    >.
                                </template>
                            </p>
                            <template v-if="confirmSummaryData.leave_policy">
                                <div
                                    class="border-border/60 border-t pt-3 text-xs text-muted-foreground"
                                >
                                    <span class="text-foreground font-medium">{{
                                        confirmSummaryData.leave_policy.name
                                    }}</span>
                                    ({{
                                        confirmSummaryData.leave_policy.code
                                    }})
                                    <template
                                        v-if="confirmPolicyEntitlementPhrase !== null"
                                    >
                                        · Annual entitlement (reference):
                                        <span class="text-foreground font-medium">{{
                                            confirmPolicyEntitlementPhrase
                                        }}</span>.
                                    </template>
                                    <template v-else>
                                        · No annual entitlement on policy for
                                        reference balances.
                                    </template>
                                    Accruals and live balances are not shown here.
                                </div>
                            </template>
                            <p
                                v-if="activeDraft.status !== 'approved'"
                                class="border-border/60 text-muted-foreground border-t pt-3 text-xs"
                            >
                                Status is not Approved: “after save” figures
                                won’t apply until you choose Approved.
                            </p>
                        </dl>
                    </section>

                    <div>
                        <p class="text-muted-foreground mb-2 text-xs font-medium">
                            Counted dates
                        </p>
                        <div
                            class="flex flex-wrap gap-1.5 rounded-lg border border-border/70 bg-card px-3 py-2.5"
                        >
                            <Badge
                                v-for="row in leaveDaysSorted"
                                :key="`confirm-${row.date}`"
                                variant="secondary"
                                class="max-w-full gap-1 tabular-nums font-normal"
                            >
                                <span class="truncate">{{
                                    formatIsoCalendarDate(row.date)
                                }}</span>
                                <span
                                    v-if="row.is_half_day"
                                    class="text-muted-foreground shrink-0"
                                    >· ½</span
                                >
                            </Badge>
                        </div>
                    </div>

                    <div
                        v-if="
                            confirmSummaryData &&
                                confirmSummaryData.approved_by_type.length >
                                    1
                        "
                        class="space-y-2"
                    >
                        <p class="text-muted-foreground text-xs font-medium">
                            All approved types,
                            {{ confirmSummaryData.reference.year }} (counted days)
                        </p>
                        <div
                            class="max-h-36 overflow-y-auto rounded-lg border border-border/70 bg-card px-3 py-2 text-xs"
                        >
                            <table class="w-full border-collapse tabular-nums">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted-foreground">
                                        <th class="py-2 pe-2 font-normal">Code</th>
                                        <th class="py-2 pe-2 font-normal">
                                            {{ confirmSummaryData.reference.month_label }}
                                        </th>
                                        <th class="py-2 pe-2 font-normal">
                                            {{ confirmSummaryData.reference.year }}
                                        </th>
                                        <th class="py-2 font-normal">Filings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in confirmSummaryData.approved_by_type"
                                        :key="row.code"
                                        class="border-border/70 border-t"
                                    >
                                        <td class="py-1.5 pe-2 align-top font-medium">{{ row.code }}</td>
                                        <td class="py-1.5 pe-2 align-top">
                                            {{
                                                formatLeaveUnitsHuman(
                                                    row.leave_units_month,
                                                )
                                            }}
                                        </td>
                                        <td class="py-1.5 pe-2 align-top">
                                            {{
                                                formatLeaveUnitsHuman(
                                                    row.leave_units_year,
                                                )
                                            }}
                                        </td>
                                        <td class="py-1.5 align-top">{{ row.records_year }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </ScrollArea>
            <DialogFooter class="gap-2 border-t pt-4">
                <Button
                    type="button"
                    variant="outline"
                    @click="confirmMutateOpen = false"
                    >Back to edit</Button
                >
                <Button
                    v-if="canAddTeamLeaveOvertimeRecords"
                    type="button"
                    @click="executeMutateSubmit"
                    >{{ isEditing ? 'Save changes' : 'Add leave record' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete leave record?</AlertDialogTitle>
                <AlertDialogDescription>
                    This soft-deletes the leave record. This action cannot be
                    undone in the UI.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteTarget = null"
                    >Cancel</AlertDialogCancel
                >
                <AlertDialogAction
                    v-if="canAddTeamLeaveOvertimeRecords"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmDelete"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
