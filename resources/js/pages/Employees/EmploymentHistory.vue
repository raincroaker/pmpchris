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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';
import { formatTenureDays } from '@/lib/formatTenureDays';
import EmploymentHistoryActionsMenu from '@/pages/Employees/EmploymentHistoryActionsMenu.vue';
import EmploymentHistoryEmployeeColumnHeader from '@/pages/Employees/EmploymentHistoryEmployeeColumnHeader.vue';
import type {
    EmploymentHistoryFilters,
    EmploymentHistoryPaginator,
    EmploymentHistoryRow,
} from '@/pages/Employees/employmentHistoryTypes';
import {
    EMPLOYMENT_STATUS_LABEL,
    EMPLOYMENT_STATUS_VALUES,
    employmentStatusBadgeClass,
} from '@/pages/Employees/employmentStatusConstants';
import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';
import { employees as employeesIndexRoute } from '@/routes';
import { employmentHistory as employmentHistoryRoute } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    employments: EmploymentHistoryPaginator;
    filters: EmploymentHistoryFilters;
    organization: { id: number; code: string; name: string } | null;
    branchScope: { id: number; code: string; name: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: employeesIndexRoute() },
    { title: 'Employment History', href: employmentHistoryRoute() },
];

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

function buildQuery(
    overrides: Partial<{
        search: string;
        employment_status: EmploymentStatusApi | null;
        employee_id: number | null;
        hire_from: string | null;
        hire_to: string | null;
        separation_from: string | null;
        separation_to: string | null;
        sort: EmploymentHistoryFilters['sort'];
        direction: EmploymentHistoryFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): Record<string, string | number> {
    const f: EmploymentHistoryFilters = { ...props.filters, ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.employments.current_page;

    const q: Record<string, string | number> = {
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
    if (f.employee_id !== null && f.employee_id !== undefined) {
        q.employee_id = f.employee_id;
    }
    if (f.hire_from !== null && f.hire_to !== null) {
        q.hire_from = f.hire_from;
        q.hire_to = f.hire_to;
    }
    if (f.separation_from !== null && f.separation_to !== null) {
        q.separation_from = f.separation_from;
        q.separation_to = f.separation_to;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        employment_status: EmploymentStatusApi | null;
        employee_id: number | null;
        hire_from: string | null;
        hire_to: string | null;
        separation_from: string | null;
        separation_to: string | null;
        sort: EmploymentHistoryFilters['sort'];
        direction: EmploymentHistoryFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): void {
    router.get(
        employmentHistoryRoute.url({ query: buildQuery(overrides) }),
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
        pageIndex: props.employments.current_page - 1,
        pageSize: props.employments.per_page,
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

function toggleSort(column: EmploymentHistoryFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function sortDirectionFor(
    column: EmploymentHistoryFilters['sort'],
): 'asc' | 'desc' | null {
    if (props.filters.sort !== column) {
        return null;
    }

    return props.filters.direction;
}

function onHireDateFilterUpdate(v: SubmittedDateRange | null): void {
    if (v === null) {
        applyQuery({
            hire_from: null,
            hire_to: null,
            page: 1,
        });

        return;
    }

    applyQuery({
        hire_from: v.from,
        hire_to: v.to,
        page: 1,
    });
}

function onSeparationDateFilterUpdate(v: SubmittedDateRange | null): void {
    if (v === null) {
        applyQuery({
            separation_from: null,
            separation_to: null,
            page: 1,
        });

        return;
    }

    applyQuery({
        separation_from: v.from,
        separation_to: v.to,
        page: 1,
    });
}

function hireFilterModel(): SubmittedDateRange | null {
    if (props.filters.hire_from !== null && props.filters.hire_to !== null) {
        return {
            from: props.filters.hire_from,
            to: props.filters.hire_to,
        };
    }

    return null;
}

function separationFilterModel(): SubmittedDateRange | null {
    if (
        props.filters.separation_from !== null &&
        props.filters.separation_to !== null
    ) {
        return {
            from: props.filters.separation_from,
            to: props.filters.separation_to,
        };
    }

    return null;
}

const statusFilterOptions = EMPLOYMENT_STATUS_VALUES.map((value) => ({
    value,
    label: EMPLOYMENT_STATUS_LABEL[value],
}));

const tablePlainHeadClass = 'font-medium text-muted-foreground';

const columns: ColumnDef<EmploymentHistoryRow>[] = [
    {
        id: 'employee',
        accessorKey: 'employee.display_name',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmploymentHistoryEmployeeColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                onSortBy: (field: 'last_name' | 'id_number') =>
                    toggleSort(field),
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-3 py-0.5' }, [
                h(
                    Avatar,
                    {
                        class: 'size-9 shrink-0 border border-border/70 bg-muted/30',
                    },
                    {
                        default: () => [
                            h(AvatarImage, {
                                src: row.original.employee.avatar_url ?? '',
                                alt: row.original.employee.display_name,
                            }),
                            h(
                                AvatarFallback,
                                {
                                    class: 'text-[11px] font-medium text-muted-foreground',
                                },
                                () =>
                                    employeeInitials(
                                        row.original.employee.display_name,
                                    ),
                            ),
                        ],
                    },
                ),
                h('div', { class: 'min-w-0 flex-1 flex flex-col gap-0.5' }, [
                    h('div', { class: 'inline-flex items-center gap-2' }, [
                        h(
                            'span',
                            { class: 'truncate font-medium text-foreground' },
                            row.original.employee.display_name,
                        ),
                        row.original.employment_status === 'active' &&
                        row.original.employee.is_org_wide
                            ? h(
                                  Badge,
                                  {
                                      variant: 'outline',
                                      class: 'rounded-full border-emerald-200 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-300',
                                  },
                                  () => 'Org-wide',
                              )
                            : null,
                    ]),
                    h(
                        'span',
                        { class: 'text-xs text-muted-foreground' },
                        row.original.employee.id_number || '—',
                    ),
                ]),
            ]),
        enableSorting: false,
    },
    {
        id: 'hire_date',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'Hire',
                modelValue: hireFilterModel(),
                enableSort: true,
                sortDirection: sortDirectionFor('hire_date'),
                'onUpdate:modelValue': onHireDateFilterUpdate,
                onToggleSort: () => toggleSort('hire_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-foreground' },
                formatCalendarTriggerFromIsoYmd(row.original.hire_date),
            ),
        enableSorting: false,
    },
    {
        id: 'separation_date',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'Separation',
                modelValue: separationFilterModel(),
                enableSort: true,
                sortDirection: sortDirectionFor('separation_date'),
                'onUpdate:modelValue': onSeparationDateFilterUpdate,
                onToggleSort: () => toggleSort('separation_date'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm tabular-nums text-muted-foreground' },
                row.original.separation_date
                    ? formatCalendarTriggerFromIsoYmd(
                          row.original.separation_date,
                      )
                    : '—',
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
                onClear: () =>
                    applyQuery({
                        employment_status: null,
                        page: 1,
                    }),
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
        id: 'tenure',
        meta: { headClass: 'min-w-[7rem]', cellClass: 'align-middle' },
        header: () =>
            h(TeamTableSortHeader, {
                columnTitle: 'Tenure',
                sortDirection: sortDirectionFor('tenure'),
                onToggleSort: () => toggleSort('tenure'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm text-foreground' },
                formatTenureDays(
                    row.original.hire_date,
                    row.original.separation_date,
                ),
            ),
        enableSorting: false,
    },
    {
        id: 'actions',
        meta: { headClass: 'w-[88px]', cellClass: 'text-center align-middle' },
        header: () =>
            h(
                'div',
                { class: `w-full text-center ${tablePlainHeadClass}` },
                'Actions',
            ),
        cell: ({ row }) =>
            h(EmploymentHistoryActionsMenu, { row: row.original }),
        enableSorting: false,
    },
];

const table = useVueTable({
    get data() {
        return props.employments.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.employments.last_page,
    rowCount: props.employments.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.employments.current_page - 1,
                pageSize: props.employments.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="Employment History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div>
                <h1 class="text-xl font-semibold text-foreground">
                    Employment History
                </h1>
                <p v-if="organization" class="text-sm text-muted-foreground">
                    {{ organization.name }}
                    <span class="text-muted-foreground/80">
                        ({{ organization.code }})</span
                    >
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    No default organization is configured. Employment rows
                    cannot be loaded until HR sets a default org.
                </p>
                <p
                    v-if="branchScope"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Showing employment for people visible under
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
                            id="employment_history_search"
                            :model-value="localSearch"
                            type="search"
                            placeholder="Search name or employee ID…"
                            autocomplete="off"
                            aria-label="Search employment history"
                            @update:model-value="onSearchUpdate"
                            @keyup="onSearchKeyup"
                            @change="onSearchCommit"
                            @search="onSearchCommit"
                        />
                    </InputGroup>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No employment rows match these filters."
                />
                <HrisServerTablePagination
                    :total="employments.total"
                    :from="employments.from"
                    :to="employments.to"
                    :current-page="employments.current_page"
                    :last-page="employments.last_page"
                    :per-page="employments.per_page"
                    :can-previous-page="employments.current_page > 1"
                    :can-next-page="
                        employments.current_page < employments.last_page
                    "
                    @update:per-page="onPerPageChange"
                    @go-first="applyQuery({ page: 1 })"
                    @go-prev="
                        applyQuery({ page: employments.current_page - 1 })
                    "
                    @go-next="
                        applyQuery({ page: employments.current_page + 1 })
                    "
                    @go-last="applyQuery({ page: employments.last_page })"
                />
            </div>
        </div>
    </AppLayout>
</template>
