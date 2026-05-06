<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Search } from 'lucide-vue-next';
import { h, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import TeamTableSortHeader from '@/components/hris/TeamTableSortHeader.vue';
import TeamTableSubmittedDateFilterHeader from '@/components/hris/TeamTableSubmittedDateFilterHeader.vue';
import type { SubmittedDateRange } from '@/components/hris/TeamTableSubmittedDateFilterHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';
import { formatTenureDays } from '@/lib/formatTenureDays';
import {
    EMPLOYMENT_STATUS_LABEL,
    EMPLOYMENT_STATUS_VALUES,
    employmentStatusBadgeClass,
} from '@/pages/Employees/employmentStatusConstants';
import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';
import type {
    JobHistoryFilters,
    JobHistoryPaginator,
    JobHistoryRow,
} from '@/pages/Positions/jobHistoryTypes';
import { positions } from '@/routes';
import { jobHistory as positionsJobHistory } from '@/routes/positions';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    jobHistory: JobHistoryPaginator;
    filters: JobHistoryFilters;
    organization: { id: number; code: string; name: string } | null;
    branchScope: { id: number; code: string; name: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Positions', href: positions() },
    { title: 'Job history', href: positionsJobHistory() },
];

const statusFilterOptions = EMPLOYMENT_STATUS_VALUES.map((value) => ({
    value,
    label: EMPLOYMENT_STATUS_LABEL[value],
}));

function buildQuery(
    overrides: Partial<{
        search: string;
        history_type: JobHistoryFilters['history_type'];
        employment_status: EmploymentStatusApi | null;
        start_from: string | null;
        start_to: string | null;
        end_from: string | null;
        end_to: string | null;
        sort: JobHistoryFilters['sort'];
        direction: JobHistoryFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): Record<string, string | number> {
    const f: JobHistoryFilters = { ...props.filters, ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.jobHistory.current_page;

    const q: Record<string, string | number> = {
        history_type: f.history_type,
        sort: f.sort,
        direction: f.direction,
        per_page: f.per_page,
        page,
    };

    if (f.search.trim() !== '') {
        q.search = f.search.trim();
    }
    if (f.employment_status !== null) {
        q.employment_status = f.employment_status;
    }
    if (f.start_from !== null && f.start_to !== null) {
        q.start_from = f.start_from;
        q.start_to = f.start_to;
    }
    if (f.end_from !== null && f.end_to !== null) {
        q.end_from = f.end_from;
        q.end_to = f.end_to;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        history_type: JobHistoryFilters['history_type'];
        employment_status: EmploymentStatusApi | null;
        start_from: string | null;
        start_to: string | null;
        end_from: string | null;
        end_to: string | null;
        sort: JobHistoryFilters['sort'];
        direction: JobHistoryFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): void {
    router.get(
        positionsJobHistory.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const {
    localSearch,
    syncFromServerSearch,
    onSearchUpdate,
    onSearchKeyup,
    onSearchCommit,
} = useDebouncedSearchInput({
    initialValue: props.filters.search,
    debounceMs: 300,
    onDebouncedSearch: (value) => applyQuery({ search: value, page: 1 }),
});

watch(
    () => props.filters.search,
    (s) => syncFromServerSearch(s),
);

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.jobHistory.current_page - 1,
        pageSize: props.jobHistory.per_page,
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

function toggleSort(column: JobHistoryFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function sortDirectionFor(
    column: JobHistoryFilters['sort'],
): 'asc' | 'desc' | null {
    if (props.filters.sort !== column) {
        return null;
    }

    return props.filters.direction;
}

function startFilterModel(): SubmittedDateRange | null {
    if (props.filters.start_from !== null && props.filters.start_to !== null) {
        return {
            from: props.filters.start_from,
            to: props.filters.start_to,
        };
    }

    return null;
}

function endFilterModel(): SubmittedDateRange | null {
    if (props.filters.end_from !== null && props.filters.end_to !== null) {
        return {
            from: props.filters.end_from,
            to: props.filters.end_to,
        };
    }

    return null;
}

function onStartDateFilterUpdate(v: SubmittedDateRange | null): void {
    if (v === null) {
        applyQuery({ start_from: null, start_to: null, page: 1 });

        return;
    }

    applyQuery({
        start_from: v.from,
        start_to: v.to,
        page: 1,
    });
}

function onEndDateFilterUpdate(v: SubmittedDateRange | null): void {
    if (v === null) {
        applyQuery({ end_from: null, end_to: null, page: 1 });

        return;
    }

    applyQuery({
        end_from: v.from,
        end_to: v.to,
        page: 1,
    });
}

const tablePlainHeadClass = 'font-medium text-muted-foreground';

const columns: ColumnDef<JobHistoryRow>[] = [
    {
        id: 'employee',
        accessorKey: 'employee.display_name',
        meta: { headClass: 'min-w-[14rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Employee',
                sortDirection: sortDirectionFor('last_name'),
                onToggleSort: () => toggleSort('last_name'),
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-0 flex-col gap-0.5 py-0.5' }, [
                h(
                    'span',
                    { class: 'truncate font-medium text-foreground' },
                    row.original.employee.display_name,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.employee.id_number || '—',
                ),
                row.original.employee.is_org_wide &&
                row.original.employment_status === 'active'
                    ? h(
                          Badge,
                          {
                              variant: 'outline',
                              class: 'mt-0.5 w-fit rounded-full border-emerald-200 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300',
                          },
                          () => 'Org-wide',
                      )
                    : null,
            ]),
        enableSorting: false,
    },
    {
        id: 'position',
        accessorKey: 'position.title',
        meta: { headClass: 'min-w-[14rem]', cellClass: 'align-middle' },
        header: () =>
            h(
                'span',
                { class: tablePlainHeadClass },
                props.filters.history_type === 'unit_assignments'
                    ? 'Unit assignment'
                    : 'Position',
            ),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-0 flex-col gap-0.5 py-0.5' }, [
                h(
                    'span',
                    { class: 'truncate font-medium text-foreground' },
                    props.filters.history_type === 'unit_assignments'
                        ? (row.original.organizational_unit?.name ??
                              'Organization')
                        : (row.original.position?.title ?? '—'),
                ),
                h(
                    'span',
                    { class: 'font-mono text-xs text-muted-foreground' },
                    props.filters.history_type === 'unit_assignments'
                        ? (row.original.organizational_unit?.code ?? 'ORG')
                        : (row.original.position?.code ?? '—'),
                ),
                props.filters.history_type === 'unit_assignments' &&
                row.original.organizational_unit?.unit_type
                    ? h(
                          'span',
                          { class: 'text-xs text-muted-foreground' },
                          row.original.organizational_unit.unit_type,
                      )
                    : null,
            ]),
        enableSorting: false,
    },
    {
        id: 'start_date',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'Start',
                modelValue: startFilterModel(),
                enableSort: true,
                sortDirection: sortDirectionFor('start_date'),
                'onUpdate:modelValue': onStartDateFilterUpdate,
                onToggleSort: () => toggleSort('start_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-foreground' },
                formatCalendarTriggerFromIsoYmd(row.original.start_date),
            ),
        enableSorting: false,
    },
    {
        id: 'end_date',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'End',
                modelValue: endFilterModel(),
                enableSort: true,
                sortDirection: sortDirectionFor('end_date'),
                'onUpdate:modelValue': onEndDateFilterUpdate,
                onToggleSort: () => toggleSort('end_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-muted-foreground' },
                row.original.end_date
                    ? formatCalendarTriggerFromIsoYmd(row.original.end_date)
                    : 'Present',
            ),
        enableSorting: false,
    },
    {
        id: 'job_status',
        accessorKey: 'job_status',
        meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
        header: () => h('span', { class: tablePlainHeadClass }, 'Job status'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'secondary',
                    class:
                        row.original.job_status === 'current'
                            ? 'rounded-full border-transparent bg-primary/15 text-primary'
                            : 'rounded-full border-transparent bg-muted text-muted-foreground',
                },
                () =>
                    row.original.job_status === 'current' ? 'Current' : 'Ended',
            ),
        enableSorting: false,
    },
    {
        id: 'employment_status',
        meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
        header: () =>
            h(HrisColumnFilterPopover, {
                label: 'Status',
                triggerAriaLabel:
                    props.filters.employment_status === null
                        ? 'Status filter: all statuses. Open to select a specific status.'
                        : `Status filter: ${EMPLOYMENT_STATUS_LABEL[props.filters.employment_status]}. Open to change status filter.`,
                modelValue: props.filters.employment_status,
                options: statusFilterOptions,
                isActive: props.filters.employment_status !== null,
                showClear: props.filters.employment_status !== null,
                allLabel: 'All',
                searchable: false,
                showCheckIcon: false,
                contentClass: 'w-auto min-w-48 p-2',
                clearAriaLabel: 'Clear status filter',
                'onUpdate:modelValue': (value: string | number | null) => {
                    applyQuery({
                        employment_status:
                            value === null
                                ? null
                                : (String(value) as EmploymentStatusApi),
                        page: 1,
                    });
                },
                onClear: () => applyQuery({ employment_status: null, page: 1 }),
            }),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'default',
                    class: employmentStatusBadgeClass(
                        row.original.employment_status,
                    ),
                },
                () => EMPLOYMENT_STATUS_LABEL[row.original.employment_status],
            ),
        enableSorting: false,
    },
    {
        id: 'total_days',
        meta: { headClass: 'min-w-[8rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Total days',
                sortDirection: sortDirectionFor('total_days'),
                onToggleSort: () => toggleSort('total_days'),
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-0.5' }, [
                h(
                    'span',
                    {
                        class: 'text-sm font-medium tabular-nums text-foreground',
                    },
                    `${row.original.total_days} day${row.original.total_days === 1 ? '' : 's'}`,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    formatTenureDays(
                        row.original.start_date,
                        row.original.end_date,
                    ),
                ),
            ]),
        enableSorting: false,
    },
];

const table = useVueTable({
    get data() {
        return props.jobHistory.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.jobHistory.last_page,
    rowCount: props.jobHistory.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.jobHistory.current_page - 1,
                pageSize: props.jobHistory.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="Job history" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div>
                <h1 class="text-xl font-semibold text-foreground">
                    Job history
                </h1>
                <p v-if="organization" class="text-sm text-muted-foreground">
                    {{ organization.name }}
                    <span class="text-muted-foreground/80">
                        ({{ organization.code }})</span
                    >
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    No default organization is configured. Job history rows
                    cannot be loaded until HR sets a default org.
                </p>
                <p
                    v-if="branchScope"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Showing job history for people visible under
                    <span class="font-medium text-foreground">{{
                        branchScope.name
                    }}</span>
                    <span class="text-muted-foreground/80">
                        ({{ branchScope.code }})</span
                    >.
                </p>
            </div>

            <HrisIndexToolbar>
                <template #start>
                    <InputGroup class="max-w-md">
                        <InputGroupAddon align="inline-start">
                            <Search
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="positions_job_history_search"
                            :model-value="localSearch"
                            type="search"
                            placeholder="Search employee ID, name, position, or unit…"
                            autocomplete="off"
                            aria-label="Search job history"
                            @update:model-value="onSearchUpdate"
                            @keyup="onSearchKeyup"
                            @change="onSearchCommit"
                            @search="onSearchCommit"
                        />
                    </InputGroup>
                </template>
                <template #end>
                    <div
                        class="inline-flex items-center rounded-md border bg-background p-1"
                    >
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                props.filters.history_type === 'positions'
                                    ? 'default'
                                    : 'ghost'
                            "
                            class="h-8 rounded-md px-3"
                            :class="
                                props.filters.history_type === 'positions'
                                    ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="
                                applyQuery({
                                    history_type: 'positions',
                                    page: 1,
                                })
                            "
                        >
                            Positions
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="
                                props.filters.history_type ===
                                'unit_assignments'
                                    ? 'default'
                                    : 'ghost'
                            "
                            class="h-8 rounded-md px-3"
                            :class="
                                props.filters.history_type ===
                                'unit_assignments'
                                    ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="
                                applyQuery({
                                    history_type: 'unit_assignments',
                                    page: 1,
                                })
                            "
                        >
                            Unit Assignments
                        </Button>
                    </div>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No job history rows match these filters."
                />
                <HrisServerTablePagination
                    :total="jobHistory.total"
                    :from="jobHistory.from"
                    :to="jobHistory.to"
                    :current-page="jobHistory.current_page"
                    :last-page="jobHistory.last_page"
                    :per-page="jobHistory.per_page"
                    :can-previous-page="jobHistory.current_page > 1"
                    :can-next-page="
                        jobHistory.current_page < jobHistory.last_page
                    "
                    @update:per-page="onPerPageChange"
                    @go-first="applyQuery({ page: 1 })"
                    @go-prev="applyQuery({ page: jobHistory.current_page - 1 })"
                    @go-next="applyQuery({ page: jobHistory.current_page + 1 })"
                    @go-last="applyQuery({ page: jobHistory.last_page })"
                />
            </div>
        </div>
    </AppLayout>
</template>
