<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { watchDebounced } from '@vueuse/core';
import { Plus, Search } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, h, nextTick, onMounted, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
    isoTodayLocal,
} from '@/lib/calendarMonthRange';
import {
    fetchTeamHrFormUnits,
    fetchTeamHrOvertimeUsageSummary,
} from '@/lib/teamHrFormApi';
import type {
    TeamHrFormEmployeeHit,
    TeamHrFormUnit,
    TeamHrOvertimeUsageSummaryPayload,
} from '@/lib/teamHrFormApi';
import { TEAM_HR_REQUEST_STATUS_LABELS } from '@/lib/teamHrRequestStatusFilter';
import type { TeamHrRequestStatusFilter } from '@/lib/teamHrRequestStatusFilter';
import { formatIsoCalendarDate } from '@/lib/teamRequestDateRange';
import {
    OVERTIME_CONTEXT_LABELS,
    formatOvertimeRateMultiplier,
} from '@/pages/Overtime/overtimePolicyFormat';
import type { OvertimePolicyContext } from '@/pages/Overtime/overtimePolicyTypes';
import OvertimeTeamRowActionsMenu from '@/pages/Overtime/OvertimeTeamRowActionsMenu.vue';
import type {
    TeamOvertimeDraft,
    TeamOvertimeRow,
} from '@/pages/Overtime/teamOvertimeTypes';
import { team as overtimeTeam } from '@/routes/overtime';
import {
    destroy as teamEmployeeOvertimesDestroy,
    store as teamEmployeeOvertimesStore,
    update as teamEmployeeOvertimesUpdate,
} from '@/routes/overtime/team/employee-overtimes';
import type { BreadcrumbItem } from '@/types';

const page = usePage<{
    branchContext: { id: number; code: string; name: string } | null;
    can?: { canAddEmployeeTeamLeaveOvertimeEntry?: boolean };
}>();

type OvertimeTeamFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    unit_id?: number | null;
    date_from: string;
    date_to: string;
    status: TeamHrRequestStatusFilter;
    policy_code: string | null;
    approve_from: string | null;
    approve_to: string | null;
    sort: 'ot_date' | 'status' | 'approve_date';
    direction: 'asc' | 'desc';
};

type TeamOvertimePaginator = {
    data: TeamOvertimeRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const props = withDefaults(
    defineProps<{
        teamEmployeeOvertimes: TeamOvertimePaginator;
        overtimeTeamFilters: OvertimeTeamFiltersProp;
        overtimePolicyOptions: Array<{
            code: string;
            name: string;
            context: OvertimePolicyContext;
            rateMultiplier: number;
        }>;
    }>(),
    {
        teamEmployeeOvertimes: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        overtimeTeamFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            policy_code: null,
            approve_from: null,
            approve_to: null,
            sort: 'ot_date',
            direction: 'desc',
        }),
        overtimePolicyOptions: () => [],
    },
);

const chartBranchId = computed(() => page.props.branchContext?.id ?? null);

const canAddTeamLeaveOvertimeRecords = computed(() =>
    Boolean(page.props.can?.canAddEmployeeTeamLeaveOvertimeEntry),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employee Overtime', href: overtimeTeam() },
];

const policyCatalog = computed(() => props.overtimePolicyOptions);

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

const today = new Date();
const defaultDateFrom = isoFirstDayOfMonth(today);

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

const rows = computed(() => props.teamEmployeeOvertimes.data);

const localSearch = ref(props.overtimeTeamFilters.q);
const unitFilter = ref<string>(
    props.overtimeTeamFilters.unit_id != null
        ? String(props.overtimeTeamFilters.unit_id)
        : 'all',
);
const dateFrom = ref(props.overtimeTeamFilters.date_from);
const dateTo = ref(props.overtimeTeamFilters.date_to);
const statusFilter = ref<TeamHrRequestStatusFilter>(
    props.overtimeTeamFilters.status,
);

function onOvertimeStatusChipSelect(v: TeamHrRequestStatusFilter): void {
    statusFilter.value = v;
}

/** Policy code from catalog, null = all */
const policyColumnFilter = ref<string | null>(
    props.overtimeTeamFilters.policy_code ?? null,
);

/** Approve / decision date range (local calendar date), null = no extra filter */
const approveDateRange = ref<{ from: string; to: string } | null>(
    props.overtimeTeamFilters.approve_from &&
        props.overtimeTeamFilters.approve_to
        ? {
              from: props.overtimeTeamFilters.approve_from,
              to: props.overtimeTeamFilters.approve_to,
          }
        : null,
);

const perPage = ref(props.overtimeTeamFilters.per_page);
const sort = ref<OvertimeTeamFiltersProp['sort']>(
    props.overtimeTeamFilters.sort ?? 'ot_date',
);
const direction = ref<OvertimeTeamFiltersProp['direction']>(
    (props.overtimeTeamFilters.direction ?? 'desc') === 'asc' ? 'asc' : 'desc',
);

const suppressOvertimeTeamVisit = ref(false);

watch(
    () => props.overtimeTeamFilters,
    (f) => {
        suppressOvertimeTeamVisit.value = true;
        localSearch.value = f.q ?? '';
        unitFilter.value =
            f.unit_id != null && f.unit_id !== undefined
                ? String(f.unit_id)
                : 'all';
        dateFrom.value = f.date_from;
        dateTo.value = f.date_to;
        statusFilter.value = f.status;
        policyColumnFilter.value = f.policy_code ?? null;
        approveDateRange.value =
            f.approve_from && f.approve_to
                ? { from: f.approve_from, to: f.approve_to }
                : null;
        perPage.value = f.per_page;
        sort.value = f.sort ?? 'ot_date';
        direction.value = (f.direction ?? 'desc') === 'asc' ? 'asc' : 'desc';
        void nextTick(() => {
            suppressOvertimeTeamVisit.value = false;
        });
    },
    { deep: true },
);

function overtimeTeamQuery(): Record<string, string | number> {
    const q: Record<string, string | number> = {
        page: 1,
        per_page: perPage.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
        status: statusFilter.value,
        q: localSearch.value.trim(),
        sort: sort.value,
        direction: direction.value,
    };

    if (unitFilter.value !== 'all') {
        q.unit_id = Number(unitFilter.value);
    }

    if (policyColumnFilter.value !== null && policyColumnFilter.value !== '') {
        q.policy_code = policyColumnFilter.value;
    }

    if (approveDateRange.value !== null) {
        q.approve_from = approveDateRange.value.from;
        q.approve_to = approveDateRange.value.to;
    }

    return q;
}

function visitOvertimeTeam(extra?: { page?: number }): void {
    const query = overtimeTeamQuery();
    if (extra?.page !== undefined) {
        query.page = extra.page;
    }

    router.get(
        overtimeTeam.url({ query }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function scheduleOvertimeTeamVisit(extra?: { page?: number }): void {
    if (suppressOvertimeTeamVisit.value) {
        return;
    }

    visitOvertimeTeam(extra);
}

function toggleSort(column: OvertimeTeamFiltersProp['sort']): void {
    const same = sort.value === column;
    direction.value = same && direction.value === 'asc' ? 'desc' : 'asc';
    sort.value = column;
    scheduleOvertimeTeamVisit({ page: 1 });
}

function sortDirectionFor(
    column: OvertimeTeamFiltersProp['sort'],
): 'asc' | 'desc' | null {
    if (sort.value !== column) {
        return null;
    }

    return direction.value;
}

const overtimeTeamFilterWatchReady = ref(false);

watch(
    [
        unitFilter,
        dateFrom,
        dateTo,
        statusFilter,
        policyColumnFilter,
        approveDateRange,
        perPage,
    ],
    () => {
        if (!overtimeTeamFilterWatchReady.value) {
            return;
        }

        scheduleOvertimeTeamVisit({ page: 1 });
    },
);

watchDebounced(
    localSearch,
    () => {
        if (!overtimeTeamFilterWatchReady.value) {
            return;
        }

        scheduleOvertimeTeamVisit({ page: 1 });
    },
    { debounce: 350 },
);

const viewDialogOpen = ref(false);
const viewTarget = ref<TeamOvertimeRow | null>(null);

const mutateDialogOpen = ref(false);
const isEditing = ref(false);
const editId = ref<number | null>(null);
const activeDraft = ref<TeamOvertimeDraft | null>(null);
const formError = ref<string | null>(null);
const inlineSummaryLoading = ref(false);
const inlineSummaryError = ref<string | null>(null);
const inlineSummaryData = ref<TeamHrOvertimeUsageSummaryPayload | null>(null);

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

            return;
        }

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
const deleteTarget = ref<TeamOvertimeRow | null>(null);

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
    requestAnimationFrame(() => {
        overtimeTeamFilterWatchReady.value = true;
    });
});

watch(mutateDialogOpen, (open) => {
    if (open) {
        void ensureBranchUnitsLoaded();
    } else {
        inlineSummaryLoading.value = false;
        inlineSummaryError.value = null;
        inlineSummaryData.value = null;
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

function statusBadgeClass(status: TeamOvertimeRow['status']): string {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300';
}

function statusLabel(status: TeamOvertimeRow['status']): string {
    if (status === 'approved') {
        return 'Approved';
    }

    return 'Rejected';
}

function syncDraftUnitLabels(draft: TeamOvertimeDraft): void {
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

function policyByCode(code: string) {
    return policyCatalog.value.find((p) => p.code === code);
}

function defaultDraft(): TeamOvertimeDraft {
    const today = isoTodayLocal();

    return {
        employee_name: '',
        employee_id_number: '',
        employee_id: null,
        organizational_unit_id: null,
        unit_filter_value: '',
        unit_name: '',
        unit_code: null,
        ot_date: defaultDateFrom,
        hours: 2,
        context: 'ordinary_weekday' as TeamOvertimeDraft['context'],
        policy_code: '',
        status: 'approved',
        submitted_at: today,
        decided_at: today,
        approver_name: '',
        approver_employee_id: null,
        approver_id_number: '',
        reason: '',
    };
}

function syncPolicyFromCode(draft: TeamOvertimeDraft): void {
    const pol = policyByCode(draft.policy_code);
    if (pol) {
        draft.context = pol.context;
    }
}

function cloneToDraft(row: TeamOvertimeRow): TeamOvertimeDraft {
    return {
        employee_name: row.employee.display_name,
        employee_id_number: row.employee.id_number,
        employee_id: row.employee_record_id ?? null,
        organizational_unit_id: row.organizational_unit_id ?? null,
        unit_filter_value: row.unit_filter_value,
        unit_name: row.unit_name,
        unit_code: row.unit_code,
        ot_date: row.ot_date,
        hours: row.hours,
        context: row.context,
        policy_code: row.policy_code,
        status: row.status,
        submitted_at: row.submitted_at.slice(0, 10),
        decided_at: row.decided_at
            ? row.decided_at.slice(0, 10)
            : isoTodayLocal(),
        approver_name: row.approver_name ?? '',
        approver_employee_id: row.approver_employee_id ?? null,
        approver_id_number: row.approver_id_number ?? '',
        reason: row.reason ?? '',
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
    syncDraftUnitLabels(activeDraft.value);
}

function openView(row: TeamOvertimeRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

function openAdd(): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error('You do not have permission to add overtime records.');

        return;
    }
    isEditing.value = false;
    editId.value = null;
    activeDraft.value = defaultDraft();
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openEdit(row: TeamOvertimeRow): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error('You do not have permission to edit overtime records.');

        return;
    }
    isEditing.value = true;
    editId.value = row.id;
    activeDraft.value = cloneToDraft(row);
    formError.value = null;
    mutateDialogOpen.value = true;
}

function validateDraft(d: TeamOvertimeDraft): string | null {
    if (chartBranchId.value === null) {
        return 'Select a workspace branch (header) before filing.';
    }

    if (d.organizational_unit_id === null) {
        return 'Select a unit.';
    }

    if (d.employee_id === null) {
        return 'Select an employee.';
    }

    if (d.policy_code.trim() === '') {
        return 'Select an overtime policy.';
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

    if (d.hours <= 0) {
        return 'Hours must be greater than zero.';
    }

    return null;
}

async function loadInlineOvertimeSummary(): Promise<void> {
    const bid = chartBranchId.value;
    const d = activeDraft.value;

    if (
        bid === null ||
        !d?.employee_id ||
        !d.organizational_unit_id ||
        d.policy_code.trim() === ''
    ) {
        inlineSummaryData.value = null;
        inlineSummaryError.value = null;

        return;
    }

    inlineSummaryLoading.value = true;
    inlineSummaryError.value = null;

    try {
        inlineSummaryData.value = await fetchTeamHrOvertimeUsageSummary({
            chartBranchId: bid,
            unitId: d.organizational_unit_id,
            employeeId: d.employee_id,
            policyCode: d.policy_code,
            excludeEmployeeOvertimeId:
                isEditing.value && editId.value !== null ? editId.value : null,
        });
    } catch (e) {
        inlineSummaryData.value = null;
        inlineSummaryError.value =
            e instanceof Error ? e.message : 'Unable to load overtime usage.';
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
        activeDraft.value?.policy_code ?? '',
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
        void loadInlineOvertimeSummary();
    },
);

function resolveRateForDraft(d: TeamOvertimeDraft): number {
    return policyByCode(d.policy_code)?.rateMultiplier ?? 1.25;
}

function buildTeamOvertimePayload(
    d: TeamOvertimeDraft,
): Record<string, unknown> {
    const decided = d.decided_at.trim().slice(0, 10);

    return {
        employee_id: d.employee_id,
        organizational_unit_id: d.organizational_unit_id,
        policy_code: d.policy_code,
        ot_date: d.ot_date,
        hours: Number(d.hours),
        status: d.status,
        submitted_at: d.submitted_at.trim().slice(0, 10),
        decided_at: decided === '' ? null : decided,
        approver_employee_id: d.approver_employee_id,
        reason: d.reason?.trim() === '' ? null : (d.reason?.trim() ?? null),
    };
}

function applyMutate(): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        formError.value =
            'You do not have permission to add or change overtime records.';

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
    syncPolicyFromCode(d);
    syncDraftUnitLabels(d);

    d.submitted_at = d.submitted_at.trim().slice(0, 10);
    d.decided_at = d.decided_at.trim().slice(0, 10);

    const payload = buildTeamOvertimePayload(d);

    if (isEditing.value && editId.value != null) {
        router.patch(
            teamEmployeeOvertimesUpdate({
                employeeOvertime: editId.value,
            }).url,
            payload as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    mutateDialogOpen.value = false;
                    activeDraft.value = null;
                    appToast.success('Overtime record updated.');
                },
                onError: () => {
                    formError.value =
                        'Could not save changes. Check the form and try again.';
                },
            },
        );

        return;
    }

    router.post(teamEmployeeOvertimesStore.url(), payload as RequestPayload, {
        preserveScroll: true,
        onSuccess: () => {
            mutateDialogOpen.value = false;
            activeDraft.value = null;
            appToast.success('Overtime record added.');
        },
        onError: () => {
            formError.value =
                'Could not save changes. Check the form and try again.';
        },
    });
}

function openDeleteConfirm(row: TeamOvertimeRow): void {
    if (!canAddTeamLeaveOvertimeRecords.value) {
        appToast.error(
            'You do not have permission to delete overtime records.',
        );

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
    router.delete(teamEmployeeOvertimesDestroy({ employeeOvertime: id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            if (viewTarget.value?.id === id) {
                viewDialogOpen.value = false;
                viewTarget.value = null;
            }
            appToast.success('Overtime record removed.');
        },
        onError: () => {
            appToast.error('Could not delete this record.');
        },
    });
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

const policyFilterOptions = computed(() =>
    policyCatalog.value.map((p) => ({
        value: p.code,
        label: p.name,
        secondary: `${p.code} · ${formatOvertimeRateMultiplier(p.rateMultiplier)}`,
        searchText: `${p.name} ${p.code} ${formatOvertimeRateMultiplier(p.rateMultiplier)}`,
    })),
);

const totalRows = computed(() => props.teamEmployeeOvertimes.total);

const emptyMessage = computed(() => {
    if (props.teamEmployeeOvertimes.total === 0) {
        return 'No overtime records for this workspace yet. Add a record to see it listed.';
    }

    return 'No overtime records match your current filters.';
});

const fromRow = computed(() => props.teamEmployeeOvertimes.from);

const toRow = computed(() => props.teamEmployeeOvertimes.to);

const lastPage = computed(() => props.teamEmployeeOvertimes.last_page);

function setPage(n: number): void {
    visitOvertimeTeam({
        page: Math.min(Math.max(n, 1), lastPage.value),
    });
}

const columns = computed((): ColumnDef<TeamOvertimeRow>[] => [
    {
        id: 'employee',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
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
        id: 'ot_date',
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'OT date',
                sortDirection: sortDirectionFor('ot_date'),
                onToggleSort: () => toggleSort('ot_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-foreground' },
                new Date(`${row.original.ot_date}T12:00:00`).toLocaleDateString(
                    undefined,
                    {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    },
                ),
            ),
    },
    {
        id: 'hours',
        header: () => h('span', { class: tablePlainHeadClass }, 'Hours'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'font-mono text-sm tabular-nums text-foreground' },
                String(row.original.hours),
            ),
    },
    {
        id: 'policy',
        meta: { cellClass: 'whitespace-normal' },
        header: () =>
            h(HrisColumnFilterPopover, {
                label: 'Policy / rate',
                triggerAriaLabel:
                    policyColumnFilter.value === null
                        ? 'Policy filter: all. Open to choose a policy.'
                        : `Policy filter: ${policyColumnFilter.value}. Open to change.`,
                modelValue: policyColumnFilter.value ?? null,
                options: policyFilterOptions.value,
                isActive: (policyColumnFilter.value ?? null) !== null,
                showClear: (policyColumnFilter.value ?? null) !== null,
                clearAriaLabel: 'Clear policy filter',
                allLabel: 'All policies',
                searchPlaceholder: 'Search policy name or code…',
                emptyText: 'No matching policies.',
                'onUpdate:modelValue': (v: string | number | null) => {
                    policyColumnFilter.value = v === null ? null : String(v);
                },
                onClear: () => {
                    policyColumnFilter.value = null;
                },
            }),
        cell: ({ row }) => {
            const r = row.original;
            const pol = policyByCode(r.policy_code);

            return h(
                'div',
                { class: 'flex max-w-[15rem] flex-col gap-0.5 py-0.5' },
                [
                    h(
                        'span',
                        { class: 'text-sm font-medium text-foreground' },
                        pol?.name ?? r.policy_code,
                    ),
                    h(
                        'span',
                        {
                            class: 'font-mono text-xs tabular-nums text-muted-foreground',
                        },
                        `${r.policy_code} · ${formatOvertimeRateMultiplier(r.rate_multiplier)}`,
                    ),
                ],
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
                modelValue: approveDateRange.value,
                enableSort: true,
                sortDirection: sortDirectionFor('approve_date'),
                onToggleSort: () => toggleSort('approve_date'),
                'onUpdate:modelValue': (
                    v: { from: string; to: string } | null,
                ) => {
                    approveDateRange.value = v;
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
            h(OvertimeTeamRowActionsMenu, {
                row: row.original,
                canMutate: canAddTeamLeaveOvertimeRecords,
                onView: openView,
                onEdit: openEdit,
                onRemove: openDeleteConfirm,
            }),
    },
]);

const table = useVueTable({
    get data() {
        return rows.value;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
});

function onUnitSelectModelValue(v: unknown): void {
    unitFilter.value = String(v ?? 'all');
}

const unitSelectValue = computed(() => unitFilter.value);
</script>

<template>
    <Head title="Employee Overtime" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Employee Overtime
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    HR-entered overtime records (not an employee request queue).
                    The date range filters rows whose
                    <span class="font-medium text-foreground">OT date</span>
                    falls inside it (inclusive). Rows are loaded for your
                    default organization and workspace branch.
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
                                id="employee-overtime-search"
                                :model-value="localSearch"
                                type="search"
                                autocomplete="off"
                                placeholder="Search name, ID, policy, context, or notes…"
                                aria-label="Search overtime requests"
                                @update:model-value="
                                    (v) => (localSearch = String(v ?? ''))
                                "
                            />
                        </InputGroup>
                    </div>
                    <div
                        class="flex w-full shrink-0 flex-wrap items-center justify-start gap-2 sm:w-auto sm:justify-end"
                    >
                        <Select
                            :model-value="unitSelectValue"
                            @update:model-value="onUnitSelectModelValue"
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
                                statusFilter === opt.value
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
                                    : ''
                            "
                            :aria-pressed="statusFilter === opt.value"
                            @click="onOvertimeStatusChipSelect(opt.value)"
                        >
                            {{ opt.label }}
                        </Button>
                    </div>
                    <div
                        class="flex w-full shrink-0 justify-start sm:w-auto sm:justify-end"
                    >
                        <TeamIndexDateRangePickers
                            v-model:date-from="dateFrom"
                            v-model:date-to="dateTo"
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
                    :current-page="teamEmployeeOvertimes.current_page"
                    :last-page="lastPage"
                    :per-page="perPage"
                    :can-previous-page="teamEmployeeOvertimes.current_page > 1"
                    :can-next-page="
                        teamEmployeeOvertimes.current_page < lastPage
                    "
                    @update:per-page="
                        (n) => {
                            perPage = n;
                            setPage(1);
                        }
                    "
                    @go-first="setPage(1)"
                    @go-prev="setPage(teamEmployeeOvertimes.current_page - 1)"
                    @go-next="setPage(teamEmployeeOvertimes.current_page + 1)"
                    @go-last="setPage(lastPage)"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>View Overtime Record</DialogTitle>
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
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                OT date
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                            >
                                {{
                                    new Date(
                                        `${viewTarget.ot_date}T12:00:00`,
                                    ).toLocaleDateString(undefined, {
                                        dateStyle: 'long',
                                    })
                                }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Hours
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-mono tabular-nums"
                            >
                                {{ viewTarget.hours }}
                            </p>
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Policy
                            </p>
                            <div
                                class="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <span
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{
                                        policyByCode(viewTarget.policy_code)
                                            ?.name ?? viewTarget.policy_code
                                    }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ viewTarget.policy_code }}
                                    ·
                                    {{
                                        formatOvertimeRateMultiplier(
                                            viewTarget.rate_multiplier,
                                        )
                                    }}
                                </span>
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
                                                viewTarget.approver_id_number ??
                                                ''
                                            ).trim() !== ''
                                        "
                                        class="font-mono text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            (
                                                viewTarget.approver_id_number ??
                                                ''
                                            ).trim()
                                        }}
                                    </span>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
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
                    isEditing ? 'Edit Overtime Record' : 'Add Overtime Record'
                }}</DialogTitle>
                <DialogDescription>
                    Units and employees load from your workspace branch. Saving
                    creates or updates records in the database.
                </DialogDescription>
            </DialogHeader>
            <TooltipProvider :delay-duration="200">
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
                            <Label for="ot-unit">Unit</Label>
                            <TeamHrUnitCombobox
                                id="ot-unit"
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
                            <Label for="ot-emp">Employee</Label>
                            <TeamHrEmployeeCombobox
                                v-if="activeDraft !== null"
                                id="ot-emp"
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
                            <Label for="ot-pol">Overtime policy</Label>
                            <Select
                                :model-value="activeDraft.policy_code"
                                @update:model-value="
                                    (v: AcceptableValue) => {
                                        if (
                                            !activeDraft ||
                                            v == null ||
                                            typeof v === 'object'
                                        ) {
                                            return;
                                        }

                                        activeDraft.policy_code = String(v);
                                        syncPolicyFromCode(activeDraft);
                                    }
                                "
                            >
                                <SelectTrigger id="ot-pol" class="h-9 w-full">
                                    <SelectValue
                                        placeholder="Select overtime policy"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="pol in policyCatalog"
                                        :key="pol.code"
                                        :value="pol.code"
                                    >
                                        {{ pol.code }} — {{ pol.name }} ({{
                                            formatOvertimeRateMultiplier(
                                                pol.rateMultiplier,
                                            )
                                        }})
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
                                v-else-if="
                                    inlineSummaryData &&
                                    activeDraft.policy_code.trim() !== ''
                                "
                                class="grid gap-3 rounded-md border border-border/60 bg-muted/20 p-3 sm:grid-cols-3"
                            >
                                <div class="grid gap-1.5">
                                    <div class="flex h-5 items-center gap-1">
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            This Year
                                        </p>
                                        <span
                                            aria-hidden="true"
                                            class="inline-flex size-4 shrink-0 opacity-0"
                                        />
                                    </div>
                                    <div
                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            inlineSummaryData.same_policy_code
                                                .approved_hours_year
                                        }}
                                    </div>
                                </div>
                                <div class="grid gap-1.5">
                                    <div class="flex h-5 items-center gap-1">
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
                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            inlineSummaryData.same_policy_code
                                                .approved_hours_month
                                        }}
                                    </div>
                                </div>
                                <div class="grid gap-1.5">
                                    <div class="flex h-5 items-center gap-1">
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
                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm font-medium text-foreground tabular-nums"
                                    >
                                        {{
                                            inlineSummaryData.same_policy_code
                                                .approved_hours_week
                                        }}
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Context:
                                {{
                                    OVERTIME_CONTEXT_LABELS[activeDraft.context]
                                }}
                                · Rate applied:
                                {{
                                    formatOvertimeRateMultiplier(
                                        resolveRateForDraft(activeDraft),
                                    )
                                }}
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="ot-date">OT date</Label>
                                <TeamFormIsoDatePicker
                                    v-model="activeDraft.ot_date"
                                    ariaLabel="Select overtime date"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ot-hours">Hours</Label>
                                <Input
                                    id="ot-hours"
                                    v-model.number="activeDraft.hours"
                                    class="h-9 font-mono tabular-nums"
                                    min="0.25"
                                    step="0.25"
                                    type="number"
                                />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="ot-submitted">Submitted</Label>
                                <TeamFormIsoDatePicker
                                    id="ot-submitted"
                                    v-model="activeDraft.submitted_at"
                                    ariaLabel="Submitted date"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ot-approve">Approve date</Label>
                                <TeamFormIsoDatePicker
                                    id="ot-approve"
                                    v-model="activeDraft.decided_at"
                                    ariaLabel="Approve date"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="ot-decision-maker"
                                >Decision maker</Label
                            >
                            <TeamHrDecisionMakerCombobox
                                id="ot-decision-maker"
                                v-model="selectedDecisionMakerModel"
                                :chart-branch-id="chartBranchId"
                                :disabled="chartBranchId === null"
                                placeholder="Search by name or employee ID…"
                            />
                            <p class="text-xs text-muted-foreground">
                                Type at least 2 letters to search active
                                employees in this branch.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="ot-status">Status</Label>
                            <Select
                                :model-value="activeDraft.status"
                                @update:model-value="
                                    (v: unknown) => {
                                        if (!activeDraft) {
                                            return;
                                        }

                                        activeDraft.status =
                                            v as TeamOvertimeRow['status'];
                                    }
                                "
                            >
                                <SelectTrigger
                                    id="ot-status"
                                    class="h-9 w-full"
                                >
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
                            <Label
                                for="ot-reason"
                                :class="optionalLabelRowClass"
                            >
                                Reason
                                <Badge variant="outline">Optional</Badge>
                            </Label>
                            <Textarea
                                id="ot-reason"
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
            </TooltipProvider>
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
                    >{{ isEditing ? 'Save changes' : 'Add Record' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete overtime record?</AlertDialogTitle>
                <AlertDialogDescription>
                    This soft-deletes the overtime record. Restore from HR
                    tooling if your organization supports it.
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
