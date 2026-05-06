<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Plus, Search } from 'lucide-vue-next';
import { computed, h, watch } from 'vue';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
import TeamTableSubmittedDateFilterHeader from '@/components/hris/TeamTableSubmittedDateFilterHeader.vue';
import type { SubmittedDateRange } from '@/components/hris/TeamTableSubmittedDateFilterHeader.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCalendarTriggerFromIsoYmd } from '@/lib/formatCalendarTriggerDate';
import type {
    EmployeePositionFilterOption,
    EmployeeUnitFilterOption,
    EmployeeFilters,
    EmployeeRow,
    EmployeesPaginator,
} from '@/pages/Employees/employeeIndexTypes';
import EmployeesIndexActionsMenu from '@/pages/Employees/EmployeesIndexActionsMenu.vue';
import EmployeesIndexEmployeeColumnHeader from '@/pages/Employees/EmployeesIndexEmployeeColumnHeader.vue';
import EmployeesIndexPositionColumnHeader from '@/pages/Employees/EmployeesIndexPositionColumnHeader.vue';
import EmployeesIndexPositionsCell from '@/pages/Employees/EmployeesIndexPositionsCell.vue';
import EmployeesIndexUnitColumnHeader from '@/pages/Employees/EmployeesIndexUnitColumnHeader.vue';
import EmployeesIndexUnitsCell from '@/pages/Employees/EmployeesIndexUnitsCell.vue';
import { employees as employeesIndexRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    employees: EmployeesPaginator;
    filters: EmployeeFilters;
    positionFilterOptions: EmployeePositionFilterOption[];
    unitFilterOptions: EmployeeUnitFilterOption[];
    organization: { id: number; code: string; name: string } | null;
    branchScope: { id: number; code: string; name: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: employeesIndexRoute() },
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
        sort: EmployeeFilters['sort'];
        direction: EmployeeFilters['direction'];
        per_page: number;
        page: number;
        position_id: number | null;
        unit_id: number | 'unassigned' | null;
        org_scope: EmployeeFilters['org_scope'];
        hire_from: string | null;
        hire_to: string | null;
    }> = {},
): Record<string, string | number> {
    const f: EmployeeFilters = { ...props.filters, ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.employees.current_page;
    const q: Record<string, string | number> = {
        sort: f.sort,
        direction: f.direction,
        per_page: f.per_page,
        page,
    };
    if (f.search.trim() !== '') {
        q.search = f.search.trim();
    }
    if (f.position_id !== null && f.position_id !== undefined) {
        q.position_id = f.position_id;
    }
    if (f.unit_id !== null && f.unit_id !== undefined) {
        q.unit_id = f.unit_id;
    }
    if (f.org_scope !== null) {
        q.org_scope = f.org_scope;
    }
    if (f.hire_from !== null && f.hire_to !== null) {
        q.hire_from = f.hire_from;
        q.hire_to = f.hire_to;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        sort: EmployeeFilters['sort'];
        direction: EmployeeFilters['direction'];
        per_page: number;
        page: number;
        position_id: number | null;
        unit_id: number | 'unassigned' | null;
        org_scope: EmployeeFilters['org_scope'];
        hire_from: string | null;
        hire_to: string | null;
    }> = {},
): void {
    router.get(
        employeesIndexRoute.url({ query: buildQuery(overrides) }),
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
        pageIndex: props.employees.current_page - 1,
        pageSize: props.employees.per_page,
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

function toggleSort(column: EmployeeFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function sortDirectionFor(
    column: EmployeeFilters['sort'],
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

function hireFilterModel(): SubmittedDateRange | null {
    if (props.filters.hire_from !== null && props.filters.hire_to !== null) {
        return {
            from: props.filters.hire_from,
            to: props.filters.hire_to,
        };
    }

    return null;
}

const employeesToolbarUnitFilterOptions = computed(() => {
    const base: Array<{
        value: string;
        label: string;
        code?: string | null;
    }> = [{ value: 'all', label: 'All units' }];

    return [
        ...base,
        {
            value: 'unassigned',
            label: 'Unassigned',
            code: null,
        },
        ...props.unitFilterOptions.map((unit) => ({
            value: String(unit.id),
            label: unit.name,
            code: unit.code,
        })),
    ];
});

const toolbarUnitSelectValue = computed(() => {
    if (props.filters.unit_id === null) {
        return 'all';
    }
    if (props.filters.unit_id === 'unassigned') {
        return 'unassigned';
    }

    return String(props.filters.unit_id);
});

function onToolbarUnitSelectModelValue(v: unknown): void {
    const s = String(v ?? 'all');
    if (s === 'all') {
        applyQuery({ unit_id: null, page: 1 });

        return;
    }
    if (s === 'unassigned') {
        applyQuery({ unit_id: 'unassigned', page: 1 });

        return;
    }
    const id = Number.parseInt(s, 10);
    applyQuery({ unit_id: Number.isNaN(id) ? null : id, page: 1 });
}

const columns: ColumnDef<EmployeeRow>[] = [
    {
        id: 'employee',
        accessorKey: 'display_name',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeesIndexEmployeeColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                orgScope: props.filters.org_scope,
                onSortBy: (field: 'last_name' | 'id_number') =>
                    toggleSort(field),
                'onUpdate:orgScope': (value: EmployeeFilters['org_scope']) => {
                    applyQuery({ org_scope: value, page: 1 });
                },
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
                                src: row.original.avatar_url ?? '',
                                alt: row.original.display_name,
                            }),
                            h(
                                AvatarFallback,
                                {
                                    class: 'text-[11px] font-medium text-muted-foreground',
                                },
                                () =>
                                    employeeInitials(row.original.display_name),
                            ),
                        ],
                    },
                ),
                h('div', { class: 'min-w-0 flex-1 flex flex-col gap-0.5' }, [
                    h('div', { class: 'inline-flex items-center gap-2' }, [
                        h(
                            'span',
                            { class: 'truncate font-medium text-foreground' },
                            row.original.display_name,
                        ),
                        row.original.is_org_wide
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
                        row.original.id_number,
                    ),
                ]),
            ]),
        enableSorting: false,
    },
    {
        id: 'hire_date',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(TeamTableSubmittedDateFilterHeader, {
                columnTitle: 'Hire date',
                modelValue: hireFilterModel(),
                enableSort: true,
                sortDirection: sortDirectionFor('hire_date'),
                'onUpdate:modelValue': onHireDateFilterUpdate,
                onToggleSort: () => toggleSort('hire_date'),
            }),
        cell: ({ row }) => {
            const hire = row.original.current_employment?.hire_date ?? null;

            return h(
                'span',
                {
                    class: hire
                        ? 'text-sm tabular-nums text-foreground'
                        : 'text-sm text-muted-foreground',
                },
                hire ? formatCalendarTriggerFromIsoYmd(hire) : '—',
            );
        },
        enableSorting: false,
    },
    {
        id: 'positions',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeesIndexPositionColumnHeader, {
                modelValue: props.filters.position_id,
                options: props.positionFilterOptions,
                'onUpdate:modelValue': (v: number | null) => {
                    applyQuery({ position_id: v, page: 1 });
                },
            }),
        cell: ({ row }) =>
            h(EmployeesIndexPositionsCell, {
                positions: row.original.positions,
            }),
        enableSorting: false,
    },
    {
        id: 'units',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeesIndexUnitColumnHeader, {
                modelValue: props.filters.unit_id,
                options: props.unitFilterOptions,
                'onUpdate:modelValue': (v: number | 'unassigned' | null) => {
                    applyQuery({ unit_id: v, page: 1 });
                },
            }),
        cell: ({ row }) =>
            h(EmployeesIndexUnitsCell, { units: row.original.units }),
        enableSorting: false,
    },
    {
        id: 'contacts',
        meta: {
            headClass: 'min-w-[9rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Contacts',
            ),
        cell: ({ row }) => {
            const { phone, email } = row.original.contact;

            return h('div', { class: 'flex flex-col gap-0.5 py-0.5 text-sm' }, [
                h(
                    'span',
                    {
                        class: phone
                            ? 'text-foreground'
                            : 'text-muted-foreground',
                    },
                    phone ?? '—',
                ),
                h(
                    'span',
                    {
                        class: email
                            ? 'break-all text-xs text-muted-foreground'
                            : 'text-xs text-muted-foreground',
                    },
                    email ?? '—',
                ),
            ]);
        },
        enableSorting: false,
    },
    {
        id: 'actions',
        meta: {
            headClass: 'w-[100px] text-center',
            cellClass: 'text-center align-middle',
        },
        header: () =>
            h(
                'div',
                {
                    class: 'w-full text-center font-medium text-muted-foreground',
                },
                'Actions',
            ),
        cell: ({ row }) => h(EmployeesIndexActionsMenu, { row: row.original }),
        enableSorting: false,
    },
];

const table = useVueTable({
    get data() {
        return props.employees.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.employees.last_page,
    rowCount: props.employees.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.employees.current_page - 1,
                pageSize: props.employees.per_page,
            };
        },
    },
});
</script>

<template>
    <Head title="Employees" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div>
                <h1 class="text-xl font-semibold text-foreground">Employees</h1>
                <p v-if="organization" class="text-sm text-muted-foreground">
                    {{ organization.name }}
                    <span class="text-muted-foreground/80">
                        ({{ organization.code }})</span
                    >
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    No default organization is configured. Employee rows cannot
                    be loaded until HR sets a default org.
                </p>
                <p
                    v-if="branchScope"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Showing employees for
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
                            id="employees_search"
                            :model-value="localSearch"
                            type="search"
                            placeholder="Search name or employee ID…"
                            autocomplete="off"
                            aria-label="Search employees by name or employee ID"
                            @update:model-value="onSearchUpdate"
                            @keyup="onSearchKeyup"
                            @change="onSearchCommit"
                            @search="onSearchCommit"
                        />
                    </InputGroup>
                </template>
                <template #end>
                    <div
                        class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto"
                    >
                        <Select
                            :model-value="toolbarUnitSelectValue"
                            @update:model-value="onToolbarUnitSelectModelValue"
                        >
                            <SelectTrigger
                                class="h-9 w-full min-w-56 justify-between text-start font-normal sm:w-56"
                                aria-label="Filter by unit"
                            >
                                <SelectValue placeholder="All units">
                                    <template #default="{ modelValue }">
                                        <HrisUnitSelectTriggerLabel
                                            :select-model-value="modelValue"
                                            :options="
                                                employeesToolbarUnitFilterOptions
                                            "
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in employeesToolbarUnitFilterOptions"
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
                            class="h-9 shrink-0"
                            :as-child="true"
                        >
                            <a
                                class="inline-flex items-center gap-1"
                                href="/employees/create"
                            >
                                <Plus class="size-4" aria-hidden="true" />
                                <span class="mr-1">Add Employee</span>
                            </a>
                        </Button>
                    </div>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No employees match the current filters."
                />

                <HrisServerTablePagination
                    :total="employees.total"
                    :from="employees.from"
                    :to="employees.to"
                    :current-page="employees.current_page"
                    :last-page="employees.last_page"
                    :per-page="employees.per_page"
                    :can-previous-page="table.getCanPreviousPage()"
                    :can-next-page="table.getCanNextPage()"
                    @update:per-page="onPerPageChange"
                    @go-first="
                        onPaginationChange({
                            pageIndex: 0,
                            pageSize: employees.per_page,
                        })
                    "
                    @go-prev="table.previousPage()"
                    @go-next="table.nextPage()"
                    @go-last="
                        onPaginationChange({
                            pageIndex: Math.max(employees.last_page - 1, 0),
                            pageSize: employees.per_page,
                        })
                    "
                />
            </div>
        </div>
    </AppLayout>
</template>
