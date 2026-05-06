<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import {
    CalendarArrowUp,
    CheckCircle2,
    Eye,
    Info,
    Palmtree,
    Search,
    XCircle,
} from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisEmployeeDirectoryUnitAndPositions from '@/components/hris/HrisEmployeeDirectoryUnitAndPositions.vue';
import HrisKpiCard from '@/components/hris/HrisKpiCard.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import TeamHrRequestStatusColumnHeader from '@/components/hris/TeamHrRequestStatusColumnHeader.vue';
import TeamIndexDateRangePickers from '@/components/hris/TeamIndexDateRangePickers.vue';
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
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
} from '@/lib/calendarMonthRange';
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';
import type { TeamHrRequestStatusFilter } from '@/lib/teamHrRequestStatusFilter';
import { formatIsoCalendarDate } from '@/lib/teamRequestDateRange';
import {
    resolveLeaveDaysForRow,
    formatLeaveUnitsLabel,
    sortLeaveDays,
} from '@/pages/Leave/leaveDaysDraftUtils';
import type {
    TeamLeaveDayDraft,
    TeamLeaveRow,
} from '@/pages/Leave/teamLeaveTypes';
import { my as leaveMy, team as leaveTeam } from '@/routes/leave';
import type { BreadcrumbItem } from '@/types';

type MyLeaveFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    date_from: string;
    date_to: string;
    status: TeamHrRequestStatusFilter;
    leave_type: string | null;
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

type MyLeaveKpisProp = {
    today: string;
    approved_calendar_days: number;
    upcoming_count: number;
    vacation_calendar_days: number;
    rejected_count: number;
    approved_usage_by_type: Array<{
        code: string;
        name: string;
        days: number;
        record_count: number;
    }>;
    vacation_rows: TeamLeaveRow[];
    upcoming_rows: TeamLeaveRow[];
    rejected_rows: TeamLeaveRow[];
};

const props = withDefaults(
    defineProps<{
        myEmployeeLeaves: TeamLeavePaginator;
        myLeaveFilters: MyLeaveFiltersProp;
        leavePolicyOptions: Array<{ code: string; name: string }>;
        myLeaveKpis: MyLeaveKpisProp;
        hasEmployeeRecord: boolean;
    }>(),
    {
        myEmployeeLeaves: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        myLeaveFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            leave_type: null,
        }),
        leavePolicyOptions: () => [],
        myLeaveKpis: () => ({
            today: '',
            approved_calendar_days: 0,
            upcoming_count: 0,
            vacation_calendar_days: 0,
            rejected_count: 0,
            approved_usage_by_type: [],
            vacation_rows: [],
            upcoming_rows: [],
            rejected_rows: [],
        }),
        hasEmployeeRecord: true,
    },
);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My Leaves', href: leaveMy() }];

const tablePlainHeadClass = 'font-medium text-muted-foreground';
const dialogScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

function normalizedMyLeaveFilters(): MyLeaveFiltersProp {
    const f = props.myLeaveFilters;

    return {
        page: f.page ?? 1,
        per_page: f.per_page ?? 10,
        q: f.q ?? '',
        date_from: f.date_from ?? isoFirstDayOfMonth(new Date()),
        date_to: f.date_to ?? isoLastDayOfMonth(new Date()),
        status: (f.status ?? 'all') as TeamHrRequestStatusFilter,
        leave_type: f.leave_type ?? null,
    };
}

function buildQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        date_from: string;
        date_to: string;
        status: TeamHrRequestStatusFilter;
        leave_type: string | null;
    }> = {},
): Record<string, string | number> {
    const merged = { ...normalizedMyLeaveFilters(), ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.myEmployeeLeaves.current_page;

    const q: Record<string, string | number> = {
        page,
        per_page: merged.per_page,
        date_from: merged.date_from,
        date_to: merged.date_to,
        status: merged.status,
    };

    const trimmed = merged.q.trim();
    if (trimmed !== '') {
        q.q = trimmed;
    }

    if (merged.leave_type !== null && merged.leave_type !== '') {
        q.leave_type = merged.leave_type;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        page: number;
        per_page: number;
        q: string;
        date_from: string;
        date_to: string;
        status: TeamHrRequestStatusFilter;
        leave_type: string | null;
    }> = {},
): void {
    router.get(
        leaveMy.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const { localSearch, syncFromServerSearch } = useDebouncedSearchInput({
    initialValue: props.myLeaveFilters.q ?? '',
    debounceMs: 300,
    onDebouncedSearch: (value) => applyQuery({ q: value, page: 1 }),
});

watch(
    () => props.myLeaveFilters.q,
    (s) => syncFromServerSearch(s ?? ''),
);

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const toolbarDateFromModel = computed({
    get(): string {
        return props.myLeaveFilters.date_from ?? isoFirstDayOfMonth(new Date());
    },
    set(iso: string): void {
        applyQuery({ date_from: iso, page: 1 });
    },
});

const toolbarDateToModel = computed({
    get(): string {
        return props.myLeaveFilters.date_to ?? isoLastDayOfMonth(new Date());
    },
    set(iso: string): void {
        applyQuery({ date_to: iso, page: 1 });
    },
});

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.myEmployeeLeaves.current_page - 1,
        pageSize: props.myEmployeeLeaves.per_page,
    };
    const next = typeof updater === 'function' ? updater(prev) : updater;

    if (next.pageSize !== prev.pageSize) {
        applyQuery({ per_page: next.pageSize, page: 1 });

        return;
    }

    applyQuery({ page: next.pageIndex + 1 });
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

function statusBadgeClass(status: TeamLeaveRow['status']): string {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300';
}

function statusLabel(status: TeamLeaveRow['status']): string {
    return status === 'approved' ? 'Approved' : 'Rejected';
}

function shortCalendarDate(iso: string): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
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

function inclusiveCalendarDays(start: string, end: string): number {
    const a = new Date(`${start}T12:00:00`).getTime();
    const b = new Date(`${end}T12:00:00`).getTime();

    return Math.round((b - a) / 86400000) + 1;
}

const leaveTypeFilterOptions = computed(() =>
    props.leavePolicyOptions.map((lt) => ({
        value: lt.code,
        label: lt.name,
        secondary: lt.code,
        searchText: `${lt.name} ${lt.code}`,
    })),
);

type LeaveKpiDetailKind = 'approved' | 'vacation' | 'upcoming' | 'rejected';

const kpiDetailOpen = ref(false);
const kpiDetailKind = ref<LeaveKpiDetailKind | null>(null);

function openLeaveKpiDetail(kind: LeaveKpiDetailKind): void {
    kpiDetailKind.value = kind;
    kpiDetailOpen.value = true;
}

const leaveKpiDetailTitle = computed(() => {
    switch (kpiDetailKind.value) {
        case 'approved':
            return 'Approved leave by type';
        case 'vacation':
            return 'Vacation leave (VL) usage';
        case 'upcoming':
            return 'Upcoming leaves';
        case 'rejected':
            return 'Rejected requests';
        default:
            return 'Details';
    }
});

const leaveKpiDetailDescription = computed(() => {
    const todayLabel = props.myLeaveKpis.today;

    switch (kpiDetailKind.value) {
        case 'approved':
            return 'Calendar days summed by leave type using the same filters as the KPI card.';
        case 'vacation':
            return 'Approved VL rows in range (earliest start first).';
        case 'upcoming':
            return `Rows with leave starting on or after ${todayLabel}.`;
        case 'rejected':
            return 'Rejected rows that match current filters.';
        default:
            return '';
    }
});

const approvedUsageByType = computed(
    () => props.myLeaveKpis.approved_usage_by_type,
);

const vacationApprovedRows = computed(() => props.myLeaveKpis.vacation_rows);

const upcomingLeaveRows = computed(() => props.myLeaveKpis.upcoming_rows);

const rejectedLeaveRows = computed(() => props.myLeaveKpis.rejected_rows);

const kpiApprovedDays = computed(
    () => props.myLeaveKpis.approved_calendar_days,
);

const kpiUpcoming = computed(() => props.myLeaveKpis.upcoming_count);

const kpiVacationDays = computed(
    () => props.myLeaveKpis.vacation_calendar_days,
);

const kpiRejected = computed(() => props.myLeaveKpis.rejected_count);

const kpiToday = computed(() => props.myLeaveKpis.today);

const totalRows = computed(() => props.myEmployeeLeaves.total);

const emptyMessage = computed(() => {
    if (!props.hasEmployeeRecord) {
        return 'Your account is not linked to an employee record yet.';
    }

    if (props.myEmployeeLeaves.total === 0) {
        return 'No leave records for this period yet.';
    }

    return 'No records match your current filters.';
});

const lastPage = computed(() => props.myEmployeeLeaves.last_page);

function setPage(n: number): void {
    const page = Math.min(Math.max(n, 1), lastPage.value);
    applyQuery({ page });
}

const fromRow = computed(() => props.myEmployeeLeaves.from);

const toRow = computed(() => props.myEmployeeLeaves.to);

function openView(row: TeamLeaveRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

const columns = computed((): ColumnDef<TeamLeaveRow>[] => [
    {
        id: 'leave_type',
        meta: { cellClass: 'whitespace-normal' },
        header: () =>
            h(HrisColumnFilterPopover, {
                label: 'Leave type',
                triggerAriaLabel: 'Filter by leave type',
                modelValue: props.myLeaveFilters.leave_type ?? null,
                options: leaveTypeFilterOptions.value,
                isActive: props.myLeaveFilters.leave_type != null,
                showClear: props.myLeaveFilters.leave_type != null,
                clearAriaLabel: 'Clear leave type filter',
                allLabel: 'All types',
                searchPlaceholder: 'Search leave type…',
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
            }),
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
                        { class: 'font-mono text-xs text-muted-foreground' },
                        r.leave_type_code,
                    ),
                ],
            );
        },
    },
    {
        id: 'dates',
        meta: { cellClass: 'whitespace-normal' },
        header: () => h('span', { class: tablePlainHeadClass }, 'Dates'),
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
            const unitsLabel = formatLeaveUnitsLabel(resolveLeaveDaysForRow(r));

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
            h(TeamHrRequestStatusColumnHeader, {
                modelValue: props.myLeaveFilters.status,
                scopeLabel: 'My Leaves records',
                'onUpdate:modelValue': (v: TeamHrRequestStatusFilter) => {
                    applyQuery({ status: v, page: 1 });
                },
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
        id: 'submitted',
        header: () => h('span', { class: tablePlainHeadClass }, 'Submitted'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-muted-foreground' },
                formatIsoCalendarDate(row.original.submitted_at),
            ),
    },
    {
        id: 'actions',
        meta: { headClass: 'w-[88px]', cellClass: 'text-end' },
        header: () => h('span', { class: tablePlainHeadClass }, ' '),
        cell: ({ row }) =>
            h(
                Button,
                {
                    type: 'button',
                    variant: 'ghost',
                    size: 'sm',
                    class: 'h-8 gap-1 px-2',
                    onClick: () => openView(row.original),
                },
                () => [h(Eye, { class: 'size-4' }), ' View'],
            ),
    },
]);

const table = useVueTable({
    get data() {
        return props.myEmployeeLeaves.data;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.myEmployeeLeaves.last_page,
    rowCount: props.myEmployeeLeaves.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.myEmployeeLeaves.current_page - 1,
                pageSize: props.myEmployeeLeaves.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="My Leaves" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto overflow-y-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">My Leaves</h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Read-only snapshot of your leave history for the selected
                    period. KPI totals use the same filters as the table (not
                    only this page).
                </p>
                <p class="text-xs text-muted-foreground">
                    HR team view:
                    <Link
                        :href="leaveTeam()"
                        class="font-medium text-foreground underline-offset-4 hover:underline"
                        >Employee Leaves</Link
                    >
                </p>
            </div>

            <div
                v-if="!hasEmployeeRecord"
                class="rounded-lg border border-amber-500/35 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
                role="status"
            >
                Your login is not linked to an employee profile yet, so leave
                activity cannot be loaded.
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <HrisKpiCard
                    title="Approved days"
                    :value="kpiApprovedDays"
                    hint="Tap for days by leave type · in range · filtered"
                    tone="emerald"
                    :icon="CheckCircle2"
                    clickable
                    @select="openLeaveKpiDetail('approved')"
                />
                <HrisKpiCard
                    title="Vacation approved"
                    :value="kpiVacationDays"
                    hint="Tap for VL segments · VL days · filtered window"
                    tone="sky"
                    :icon="Palmtree"
                    clickable
                    @select="openLeaveKpiDetail('vacation')"
                />
                <HrisKpiCard
                    :title="`Upcoming from ${kpiToday}`"
                    :value="kpiUpcoming"
                    hint="Tap for dated rows · ≥ today · filtered"
                    tone="amber"
                    :icon="CalendarArrowUp"
                    clickable
                    @select="openLeaveKpiDetail('upcoming')"
                />
                <HrisKpiCard
                    title="Rejected rows"
                    :value="kpiRejected"
                    hint="Tap for rejection list · filtered"
                    tone="rose"
                    :icon="XCircle"
                    clickable
                    @select="openLeaveKpiDetail('rejected')"
                />
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="w-full min-w-0 flex-1 sm:max-w-md">
                    <InputGroup>
                        <InputGroupAddon align="inline-start">
                            <Search
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="my-leaves-search"
                            v-model="localSearch"
                            type="search"
                            autocomplete="off"
                            placeholder="Search leave type or notes…"
                            aria-label="Search My Leaves records"
                        />
                    </InputGroup>
                </div>
                <div
                    class="w-full min-w-0 shrink-0 sm:flex sm:w-auto sm:justify-end"
                >
                    <TeamIndexDateRangePickers
                        v-model:date-from="toolbarDateFromModel"
                        v-model:date-to="toolbarDateToModel"
                    />
                </div>
            </div>

            <div
                class="min-w-0 overflow-visible rounded-xl border border-border/60 bg-card/40"
            >
                <HrisTanStackTable
                    table-wrapper-class="overflow-visible"
                    :table="table"
                    :empty-message="emptyMessage"
                />
            </div>

            <HrisServerTablePagination
                :total="totalRows"
                :from="fromRow"
                :to="toRow"
                :current-page="myEmployeeLeaves.current_page"
                :last-page="lastPage"
                :per-page="myEmployeeLeaves.per_page"
                :can-previous-page="myEmployeeLeaves.current_page > 1"
                :can-next-page="myEmployeeLeaves.current_page < lastPage"
                @update:per-page="onPerPageChange"
                @go-first="setPage(1)"
                @go-prev="setPage(myEmployeeLeaves.current_page - 1)"
                @go-next="setPage(myEmployeeLeaves.current_page + 1)"
                @go-last="setPage(lastPage)"
            />

            <Dialog v-model:open="kpiDetailOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ leaveKpiDetailTitle }}</DialogTitle>
                        <DialogDescription>
                            {{ leaveKpiDetailDescription }}
                        </DialogDescription>
                    </DialogHeader>
                    <ScrollArea
                        class="max-h-[min(60vh,480px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                    >
                        <div
                            v-if="kpiDetailKind === 'approved'"
                            class="py-1 pr-2"
                        >
                            <template v-if="approvedUsageByType.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No approved leave in the current filtered
                                    set.
                                </p>
                            </template>
                            <table v-else class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-border/60 font-medium text-muted-foreground uppercase *:pb-2 *:text-start *:text-xs"
                                    >
                                        <th class="ps-2">Type</th>
                                        <th class="">Days</th>
                                        <th class="">Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in approvedUsageByType"
                                        :key="row.code"
                                        class="border-b border-border/40 last:border-0"
                                    >
                                        <td class="py-3 ps-2">
                                            <div class="flex flex-col gap-0.5">
                                                <span
                                                    class="font-mono text-xs text-muted-foreground"
                                                    >{{ row.code }}</span
                                                >
                                                <span
                                                    class="font-medium text-foreground"
                                                    >{{ row.name }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="tabular-nums">
                                            {{ row.days }}
                                        </td>
                                        <td class="tabular-nums">
                                            {{ row.record_count }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else-if="kpiDetailKind === 'vacation'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="vacationApprovedRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No approved vacation (VL) in the current
                                    filtered set.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in vacationApprovedRows"
                                    :key="row.id"
                                    class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                `${shortCalendarDate(row.start_date)} → ${shortCalendarDate(row.end_date)}`
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                            :title="`Span summary: ${row.duration_label}`"
                                        >
                                            {{
                                                formatLeaveUnitsLabel(
                                                    resolveLeaveDaysForRow(row),
                                                )
                                            }}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="mt-1"
                                            :class="
                                                statusBadgeClass(row.status)
                                            "
                                        >
                                            {{ statusLabel(row.status) }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            inclusiveCalendarDays(
                                                row.start_date,
                                                row.end_date,
                                            )
                                        }}
                                        calendar day(s)
                                    </p>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else-if="kpiDetailKind === 'upcoming'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="upcomingLeaveRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No upcoming starts on or after today.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in upcomingLeaveRows"
                                    :key="row.id"
                                    class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Start
                                            {{
                                                shortCalendarDate(
                                                    row.start_date,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            {{ row.leave_type_name }}
                                            <span
                                                class="font-mono text-muted-foreground"
                                            >
                                                ·
                                                {{ row.leave_type_code }}</span
                                            >
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="mt-1"
                                            :class="
                                                statusBadgeClass(row.status)
                                            "
                                        >
                                            {{ statusLabel(row.status) }}
                                        </Badge>
                                    </div>
                                    <span
                                        class="text-sm text-muted-foreground"
                                        :title="`Span summary: ${row.duration_label}`"
                                        >{{
                                            formatLeaveUnitsLabel(
                                                resolveLeaveDaysForRow(row),
                                            )
                                        }}</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else-if="kpiDetailKind === 'rejected'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="rejectedLeaveRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No rejected rows in the filtered set.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in rejectedLeaveRows"
                                    :key="row.id"
                                    class="space-y-1 py-3"
                                >
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                statusBadgeClass(row.status)
                                            "
                                        >
                                            {{ statusLabel(row.status) }}
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground tabular-nums"
                                            >{{
                                                formatIsoCalendarDate(
                                                    row.submitted_at,
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <p class="text-sm text-foreground">
                                        {{ row.leave_type_name }} ·
                                        {{ shortCalendarDate(row.start_date) }}
                                        → {{ shortCalendarDate(row.end_date) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Reason: {{ row.reason?.trim() || '—' }}
                                    </p>
                                </li>
                            </ul>
                        </div>
                    </ScrollArea>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            @click="kpiDetailOpen = false"
                            >Close</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="viewDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Leave record</DialogTitle>
                        <DialogDescription>
                            Read-only detail for your selected row.
                        </DialogDescription>
                    </DialogHeader>
                    <TooltipProvider :delay-duration="200">
                        <ScrollArea
                            v-if="viewTarget"
                            :class="dialogScrollAreaClass"
                        >
                            <div class="grid gap-4 px-1 py-1 sm:grid-cols-2">
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
                                <HrisEmployeeDirectoryUnitAndPositions
                                    :unit-name="viewTarget.unit_name"
                                    :unit-code="viewTarget.unit_code"
                                    :unit-directory-type="
                                        viewTarget.unit_type ??
                                        'Organizational unit'
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
                                                    {{
                                                        viewTarget.duration_label
                                                    }}.
                                                </p>
                                                <p class="mt-2">
                                                    Units here are reconstructed
                                                    from the saved start and end
                                                    (every calendar day in
                                                    range). When per-day rows
                                                    are stored and returned by
                                                    the API, this will match
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
                                            :key="`my-view-day-${row.date}`"
                                            variant="secondary"
                                            class="max-w-full gap-1 font-normal tabular-nums"
                                        >
                                            <span class="truncate">{{
                                                formatCalendarTriggerFromIsoYmd(
                                                    row.date,
                                                )
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
                                                    viewTarget.approver_id_number ??
                                                    ''
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
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                        >
                                            —
                                        </span>
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
                                            :class="
                                                statusBadgeClass(
                                                    viewTarget.status,
                                                )
                                            "
                                        >
                                            {{ statusLabel(viewTarget.status) }}
                                        </Badge>
                                    </div>
                                </div>
                                <div
                                    class="grid gap-3 sm:col-span-2 sm:grid-cols-2"
                                >
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
                                        class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm"
                                    >
                                        {{ viewTarget.reason?.trim() || '—' }}
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
                            >Close</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
