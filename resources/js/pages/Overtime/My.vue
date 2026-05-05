<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import {
    CalendarClock,
    CheckCircle2,
    Eye,
    ListOrdered,
    Search,
    XCircle,
} from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisEmployeeDirectoryUnitAndPositions from '@/components/hris/HrisEmployeeDirectoryUnitAndPositions.vue';
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
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    isoFirstDayOfMonth,
    isoLastDayOfMonth,
} from '@/lib/calendarMonthRange';
import type { TeamHrRequestStatusFilter } from '@/lib/teamHrRequestStatusFilter';
import { formatIsoCalendarDate } from '@/lib/teamRequestDateRange';
import {
    OVERTIME_CONTEXT_LABELS,
    formatOvertimeRateMultiplier,
} from '@/pages/Overtime/overtimePolicyFormat';
import type { TeamOvertimeRow } from '@/pages/Overtime/teamOvertimeTypes';
import { my as overtimeMy, team as overtimeTeam } from '@/routes/overtime';
import type { BreadcrumbItem } from '@/types';

type MyOvertimeFiltersProp = {
    page: number;
    per_page: number;
    q: string;
    date_from: string;
    date_to: string;
    status: TeamHrRequestStatusFilter;
    policy_code: string | null;
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

type MyOvertimeKpisProp = {
    today: string;
    approved_hours: number;
    upcoming_count: number;
    rejected_count: number;
    entries_count: number;
    approved_hours_by_policy: Array<{
        code: string;
        name: string;
        hours: number;
        record_count: number;
    }>;
    entries_rows: TeamOvertimeRow[];
    upcoming_rows: TeamOvertimeRow[];
    rejected_rows: TeamOvertimeRow[];
};

const props = withDefaults(
    defineProps<{
        myEmployeeOvertimes: TeamOvertimePaginator;
        myOvertimeFilters: MyOvertimeFiltersProp;
        overtimePolicyOptions: Array<{
            code: string;
            name: string;
            context: string;
            rateMultiplier: number;
        }>;
        myOvertimeKpis: MyOvertimeKpisProp;
        hasEmployeeRecord: boolean;
    }>(),
    {
        myEmployeeOvertimes: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
            from: null,
            to: null,
        }),
        myOvertimeFilters: () => ({
            page: 1,
            per_page: 10,
            q: '',
            date_from: isoFirstDayOfMonth(new Date()),
            date_to: isoLastDayOfMonth(new Date()),
            status: 'all',
            policy_code: null,
        }),
        overtimePolicyOptions: () => [],
        myOvertimeKpis: () => ({
            today: '',
            approved_hours: 0,
            upcoming_count: 0,
            rejected_count: 0,
            entries_count: 0,
            approved_hours_by_policy: [],
            entries_rows: [],
            upcoming_rows: [],
            rejected_rows: [],
        }),
        hasEmployeeRecord: true,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Overtime', href: overtimeMy() },
];

const tablePlainHeadClass = 'font-medium text-muted-foreground';
const dialogScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

function normalizedMyOvertimeFilters(): MyOvertimeFiltersProp {
    const f = props.myOvertimeFilters;

    return {
        page: f.page ?? 1,
        per_page: f.per_page ?? 10,
        q: f.q ?? '',
        date_from: f.date_from ?? isoFirstDayOfMonth(new Date()),
        date_to: f.date_to ?? isoLastDayOfMonth(new Date()),
        status: (f.status ?? 'all') as TeamHrRequestStatusFilter,
        policy_code: f.policy_code ?? null,
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
        policy_code: string | null;
    }> = {},
): Record<string, string | number> {
    const merged = { ...normalizedMyOvertimeFilters(), ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.myEmployeeOvertimes.current_page;

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

    if (merged.policy_code !== null && merged.policy_code !== '') {
        q.policy_code = merged.policy_code;
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
        policy_code: string | null;
    }> = {},
): void {
    router.get(
        overtimeMy.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const { localSearch, syncFromServerSearch } = useDebouncedSearchInput({
    initialValue: props.myOvertimeFilters.q ?? '',
    debounceMs: 300,
    onDebouncedSearch: (value) => applyQuery({ q: value, page: 1 }),
});

watch(
    () => props.myOvertimeFilters.q,
    (s) => syncFromServerSearch(s ?? ''),
);

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const toolbarDateFromModel = computed({
    get(): string {
        return (
            props.myOvertimeFilters.date_from ?? isoFirstDayOfMonth(new Date())
        );
    },
    set(iso: string): void {
        applyQuery({ date_from: iso, page: 1 });
    },
});

const toolbarDateToModel = computed({
    get(): string {
        return props.myOvertimeFilters.date_to ?? isoLastDayOfMonth(new Date());
    },
    set(iso: string): void {
        applyQuery({ date_to: iso, page: 1 });
    },
});

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.myEmployeeOvertimes.current_page - 1,
        pageSize: props.myEmployeeOvertimes.per_page,
    };
    const next = typeof updater === 'function' ? updater(prev) : updater;

    if (next.pageSize !== prev.pageSize) {
        applyQuery({ per_page: next.pageSize, page: 1 });

        return;
    }

    applyQuery({ page: next.pageIndex + 1 });
}

const viewDialogOpen = ref(false);
const viewTarget = ref<TeamOvertimeRow | null>(null);

function policyByCode(code: string) {
    return props.overtimePolicyOptions.find((p) => p.code === code);
}

function statusBadgeClass(status: TeamOvertimeRow['status']): string {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300';
    }

    return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300';
}

function statusLabel(status: TeamOvertimeRow['status']): string {
    return status === 'approved' ? 'Approved' : 'Rejected';
}

function shortOtDate(iso: string): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const policyFilterOptions = computed(() =>
    props.overtimePolicyOptions.map((p) => ({
        value: p.code,
        label: p.name,
        secondary: p.code,
        searchText: `${p.name} ${p.code}`,
    })),
);

type OvertimeKpiDetailKind = 'approved' | 'entries' | 'upcoming' | 'rejected';

const otKpiDetailOpen = ref(false);
const otKpiDetailKind = ref<OvertimeKpiDetailKind | null>(null);

function openOvertimeKpiDetail(kind: OvertimeKpiDetailKind): void {
    otKpiDetailKind.value = kind;
    otKpiDetailOpen.value = true;
}

const otKpiDetailTitle = computed(() => {
    switch (otKpiDetailKind.value) {
        case 'approved':
            return 'Approved hours by policy';
        case 'entries':
            return 'Entries in filtered set';
        case 'upcoming':
            return 'Upcoming overtime dates';
        case 'rejected':
            return 'Rejected requests';

        default:
            return 'Details';
    }
});

const otKpiDetailDescription = computed(() => {
    const t = props.myOvertimeKpis.today;

    switch (otKpiDetailKind.value) {
        case 'approved':
            return 'Sum of credited hours grouped by overtime policy.';
        case 'entries':
            return 'Rows matching current filters (preview list).';
        case 'upcoming':
            return `Records with OT date on or after ${t}.`;
        case 'rejected':
            return 'Rejected rows that match filters.';

        default:
            return '';
    }
});

const approvedHoursByPolicy = computed(
    () => props.myOvertimeKpis.approved_hours_by_policy,
);

const entriesOrderedRows = computed(() => props.myOvertimeKpis.entries_rows);

const upcomingOtRows = computed(() => props.myOvertimeKpis.upcoming_rows);

const rejectedOtRows = computed(() => props.myOvertimeKpis.rejected_rows);

const kpiApprovedHours = computed(() => props.myOvertimeKpis.approved_hours);

const kpiUpcomingCount = computed(() => props.myOvertimeKpis.upcoming_count);

const kpiRejected = computed(() => props.myOvertimeKpis.rejected_count);

const kpiEntries = computed(() => props.myOvertimeKpis.entries_count);

const kpiToday = computed(() => props.myOvertimeKpis.today);

const totalRows = computed(() => props.myEmployeeOvertimes.total);

const emptyMessage = computed(() => {
    if (!props.hasEmployeeRecord) {
        return 'Your account is not linked to an employee record yet.';
    }

    if (props.myEmployeeOvertimes.total === 0) {
        return 'No overtime records for this period yet.';
    }

    return 'No records match your current filters.';
});

const lastPage = computed(() => props.myEmployeeOvertimes.last_page);

function setPage(n: number): void {
    const page = Math.min(Math.max(n, 1), lastPage.value);
    applyQuery({ page });
}

const fromRow = computed(() => props.myEmployeeOvertimes.from);

const toRow = computed(() => props.myEmployeeOvertimes.to);

function openView(row: TeamOvertimeRow): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

const columns = computed((): ColumnDef<TeamOvertimeRow>[] => [
    {
        id: 'ot_date',
        header: () => h('span', { class: tablePlainHeadClass }, 'OT date'),
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
                triggerAriaLabel: 'Filter by overtime policy',
                modelValue: props.myOvertimeFilters.policy_code ?? null,
                options: policyFilterOptions.value,
                isActive: props.myOvertimeFilters.policy_code != null,
                showClear: props.myOvertimeFilters.policy_code != null,
                clearAriaLabel: 'Clear policy filter',
                allLabel: 'All policies',
                searchPlaceholder: 'Search policy…',
                emptyText: 'No matching policies.',
                'onUpdate:modelValue': (v: string | number | null) => {
                    applyQuery({
                        policy_code: v === null || v === '' ? null : String(v),
                        page: 1,
                    });
                },
                onClear: () => {
                    applyQuery({ policy_code: null, page: 1 });
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
            h(TeamHrRequestStatusColumnHeader, {
                modelValue: props.myOvertimeFilters.status,
                scopeLabel: 'My Overtime records',
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
        return props.myEmployeeOvertimes.data;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.myEmployeeOvertimes.last_page,
    rowCount: props.myEmployeeOvertimes.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.myEmployeeOvertimes.current_page - 1,
                pageSize: props.myEmployeeOvertimes.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="My Overtime" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto overflow-y-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    My Overtime
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Read-only view of your credited overtime for the selected
                    OT-date range. KPI totals use the same filters as the table
                    (not only this page).
                </p>
                <p class="text-xs text-muted-foreground">
                    HR team view:
                    <Link
                        :href="overtimeTeam()"
                        class="font-medium text-foreground underline-offset-4 hover:underline"
                        >Employee Overtime</Link
                    >
                </p>
            </div>

            <div
                v-if="!hasEmployeeRecord"
                class="rounded-lg border border-amber-500/35 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
                role="status"
            >
                Your login is not linked to an employee profile yet, so overtime
                activity cannot be loaded.
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    type="button"
                    class="group rounded-xl border border-border/70 bg-card text-start shadow-none transition-colors hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-label="Open breakdown of approved hours by overtime policy"
                    @click="openOvertimeKpiDetail('approved')"
                >
                    <div
                        class="flex gap-3 border-l-4 border-emerald-500 p-4 ps-5 dark:border-emerald-400"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300"
                        >
                            <CheckCircle2
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Approved hours
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground tabular-nums"
                            >
                                {{ kpiApprovedHours.toFixed(1) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Tap for hours-by-policy breakdown · filtered
                            </p>
                        </div>
                    </div>
                </button>
                <button
                    type="button"
                    class="group rounded-xl border border-border/70 bg-card text-start shadow-none transition-colors hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-label="Open list of all filtered overtime entries"
                    @click="openOvertimeKpiDetail('entries')"
                >
                    <div
                        class="flex gap-3 border-l-4 border-violet-500 p-4 ps-5 dark:border-violet-400"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-900 dark:bg-violet-400/15 dark:text-violet-200"
                        >
                            <ListOrdered
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Entries filtered
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground tabular-nums"
                            >
                                {{ kpiEntries }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Tap for chronological list · same filters as
                                table
                            </p>
                        </div>
                    </div>
                </button>
                <button
                    type="button"
                    class="group rounded-xl border border-border/70 bg-card text-start shadow-none transition-colors hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-label="Open upcoming overtime by OT date"
                    @click="openOvertimeKpiDetail('upcoming')"
                >
                    <div
                        class="flex gap-3 border-l-4 border-amber-500 p-4 ps-5 dark:border-amber-400"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200"
                        >
                            <CalendarClock
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Upcoming OT from {{ kpiToday }}
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground tabular-nums"
                            >
                                {{ kpiUpcomingCount }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Tap for dated rows · OT date ≥ today · filtered
                            </p>
                        </div>
                    </div>
                </button>
                <button
                    type="button"
                    class="group rounded-xl border border-border/70 bg-card text-start shadow-none transition-colors hover:bg-muted/35 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    aria-label="Open rejected overtime detail"
                    @click="openOvertimeKpiDetail('rejected')"
                >
                    <div
                        class="flex gap-3 border-l-4 border-rose-500 p-4 ps-5 dark:border-rose-400"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-800 dark:bg-rose-400/15 dark:text-rose-200"
                        >
                            <XCircle
                                class="size-5 shrink-0"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Rejected rows
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold text-foreground tabular-nums"
                            >
                                {{ kpiRejected }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Tap for rejection list · filtered
                            </p>
                        </div>
                    </div>
                </button>
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
                            id="my-overtime-search"
                            v-model="localSearch"
                            type="search"
                            autocomplete="off"
                            placeholder="Search policy or notes…"
                            aria-label="Search My Overtime records"
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
                :current-page="myEmployeeOvertimes.current_page"
                :last-page="lastPage"
                :per-page="myEmployeeOvertimes.per_page"
                :can-previous-page="myEmployeeOvertimes.current_page > 1"
                :can-next-page="myEmployeeOvertimes.current_page < lastPage"
                @update:per-page="onPerPageChange"
                @go-first="setPage(1)"
                @go-prev="setPage(myEmployeeOvertimes.current_page - 1)"
                @go-next="setPage(myEmployeeOvertimes.current_page + 1)"
                @go-last="setPage(lastPage)"
            />

            <Dialog v-model:open="otKpiDetailOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{{ otKpiDetailTitle }}</DialogTitle>
                        <DialogDescription>{{
                            otKpiDetailDescription
                        }}</DialogDescription>
                    </DialogHeader>
                    <ScrollArea
                        class="max-h-[min(60vh,480px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                    >
                        <div
                            v-if="otKpiDetailKind === 'approved'"
                            class="py-1 pr-2"
                        >
                            <template v-if="approvedHoursByPolicy.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No approved overtime in the current filtered
                                    set.
                                </p>
                            </template>
                            <table v-else class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-border/60 font-medium text-muted-foreground uppercase *:pb-2 *:text-start *:text-xs"
                                    >
                                        <th class="ps-2">Policy</th>
                                        <th class="">Hours</th>
                                        <th class="">Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in approvedHoursByPolicy"
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
                                            {{ row.hours.toFixed(1) }}
                                        </td>
                                        <td class="tabular-nums">
                                            {{ row.record_count }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else-if="otKpiDetailKind === 'entries'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="entriesOrderedRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    Nothing to list for the current filtered
                                    set.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in entriesOrderedRows"
                                    :key="row.id"
                                    class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ shortOtDate(row.ot_date) }}
                                            <span class="font-mono">
                                                · {{ row.policy_code }}</span
                                            >
                                            ·
                                            {{
                                                policyByCode(row.policy_code)
                                                    ?.name ?? row.policy_code
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
                                    <span
                                        class="font-mono text-sm text-foreground tabular-nums"
                                        >{{ row.hours }} h</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else-if="otKpiDetailKind === 'upcoming'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="upcomingOtRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No OT dates on or after today with current
                                    filters.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in upcomingOtRows"
                                    :key="row.id"
                                    class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            OT {{ shortOtDate(row.ot_date) }}
                                        </p>
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            {{
                                                policyByCode(row.policy_code)
                                                    ?.name ?? row.policy_code
                                            }}
                                            <span
                                                class="font-mono text-muted-foreground"
                                            >
                                                · {{ row.policy_code }}</span
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
                                    <span class="font-mono text-sm tabular-nums"
                                        >{{ row.hours }} h</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else-if="otKpiDetailKind === 'rejected'"
                            class="space-y-2 py-1 pr-2"
                        >
                            <template v-if="rejectedOtRows.length === 0">
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    No rejected rows in the filtered set.
                                </p>
                            </template>
                            <ul v-else class="divide-y divide-border/60">
                                <li
                                    v-for="row in rejectedOtRows"
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
                                        {{ shortOtDate(row.ot_date) }} ·
                                        {{
                                            policyByCode(row.policy_code)
                                                ?.name ?? row.policy_code
                                        }}
                                        <span
                                            class="font-mono text-muted-foreground"
                                        >
                                            · {{ row.hours }} h</span
                                        >
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
                            @click="otKpiDetailOpen = false"
                            >Close</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="viewDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Overtime record</DialogTitle>
                        <DialogDescription>
                            Read-only detail for your selected row.
                        </DialogDescription>
                    </DialogHeader>
                    <ScrollArea
                        v-if="viewTarget"
                        :class="dialogScrollAreaClass"
                    >
                        <div class="grid gap-4 px-1 py-1 sm:grid-cols-2">
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
                                    OT date
                                </p>
                                <p
                                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 tabular-nums"
                                >
                                    {{
                                        new Date(
                                            `${viewTarget.ot_date}T12:00:00`,
                                        ).toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
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
                                    <span
                                        v-if="viewTarget.context"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            OVERTIME_CONTEXT_LABELS[
                                                viewTarget.context
                                            ]
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
                                    <span v-else class="text-muted-foreground">
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
                                            statusBadgeClass(viewTarget.status)
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
