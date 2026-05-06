<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Search } from 'lucide-vue-next';
import { computed, h, onUnmounted, ref, watch } from 'vue';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import type {
    EmployeeScheduleFilters,
    EmployeeScheduleUnitFilterOption,
    EmployeeSchedulePositionFilterOption,
    EmployeeScheduleRow,
    EmployeeSchedulesPaginator,
    EmployeeScheduleTemplateOption,
} from '@/pages/Attendance/employeeScheduleIndexTypes';
import EmployeeSchedulesIndexActionsMenu from '@/pages/Attendance/EmployeeSchedulesIndexActionsMenu.vue';
import EmployeeSchedulesIndexAttendanceIdColumnHeader from '@/pages/Attendance/EmployeeSchedulesIndexAttendanceIdColumnHeader.vue';
import EmployeeSchedulesIndexEmployeeColumnHeader from '@/pages/Attendance/EmployeeSchedulesIndexEmployeeColumnHeader.vue';
import EmployeeSchedulesIndexWorkScheduleColumnHeader from '@/pages/Attendance/EmployeeSchedulesIndexWorkScheduleColumnHeader.vue';
import EmployeeScheduleViewDialog from '@/pages/Attendance/EmployeeScheduleViewDialog.vue';
import EmployeesIndexPositionColumnHeader from '@/pages/Employees/EmployeesIndexPositionColumnHeader.vue';
import EmployeesIndexPositionsCell from '@/pages/Employees/EmployeesIndexPositionsCell.vue';
import { employeeSchedules } from '@/routes/attendance';
import workScheduleTemplate from '@/routes/employees/work-schedule-template';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    scheduleTemplateOptions: EmployeeScheduleTemplateOption[];
    positionFilterOptions: EmployeeSchedulePositionFilterOption[];
    unitFilterOptions: EmployeeScheduleUnitFilterOption[];
    employees: EmployeeSchedulesPaginator;
    filters: EmployeeScheduleFilters;
    organization: { id: number; code: string; name: string } | null;
    branchScope: { id: number; code: string; name: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employee Schedules', href: employeeSchedules() },
];

const DIALOG_LEAVE_MS = 220;
const viewEmployeeScheduleDialogOpen = ref(false);
const viewEmployeeScheduleTarget = ref<EmployeeScheduleRow | null>(null);
let viewEmployeeScheduleDialogClearTimeout:
    | ReturnType<typeof setTimeout>
    | undefined;

const editEmployeeScheduleDialogOpen = ref(false);
const editEmployeeScheduleTarget = ref<EmployeeScheduleRow | null>(null);
const editEmployeeScheduleAttendanceId = ref('');
const editEmployeeScheduleTemplateId = ref<string>('none');
const editEmployeeScheduleSaving = ref(false);
let editEmployeeScheduleDialogClearTimeout:
    | ReturnType<typeof setTimeout>
    | undefined;

/** Lets Dialog leave animations finish before clearing row refs (avoids “pop”). */

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

/** Active templates from the server plus the employee current unit-linked template when it is inactive. */
const editEmployeeScheduleTemplateOptions = computed(
    (): EmployeeScheduleTemplateOption[] => {
        const opts = [...props.scheduleTemplateOptions];
        const ws = editEmployeeScheduleTarget.value?.work_schedule;

        if (ws !== undefined && ws !== null) {
            if (!opts.some((o) => o.id === ws.id)) {
                opts.push({
                    id: ws.id,
                    name: ws.name,
                    is_active: ws.is_active,
                });
            }
        }

        opts.sort((a, b) => a.name.localeCompare(b.name));

        return opts;
    },
);

function onViewEmployeeScheduleDialogOpenChange(open: boolean): void {
    if (open) {
        if (viewEmployeeScheduleDialogClearTimeout !== undefined) {
            clearTimeout(viewEmployeeScheduleDialogClearTimeout);
            viewEmployeeScheduleDialogClearTimeout = undefined;
        }
        viewEmployeeScheduleDialogOpen.value = true;

        return;
    }

    viewEmployeeScheduleDialogOpen.value = false;
    if (viewEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(viewEmployeeScheduleDialogClearTimeout);
    }

    viewEmployeeScheduleDialogClearTimeout = setTimeout(() => {
        viewEmployeeScheduleTarget.value = null;
        viewEmployeeScheduleDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function openViewEmployeeScheduleDialog(row: EmployeeScheduleRow): void {
    if (viewEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(viewEmployeeScheduleDialogClearTimeout);
        viewEmployeeScheduleDialogClearTimeout = undefined;
    }

    viewEmployeeScheduleTarget.value = row;
    viewEmployeeScheduleDialogOpen.value = true;
}

function openEditEmployeeScheduleDialog(row: EmployeeScheduleRow): void {
    if (editEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(editEmployeeScheduleDialogClearTimeout);
        editEmployeeScheduleDialogClearTimeout = undefined;
    }

    editEmployeeScheduleTarget.value = row;
    editEmployeeScheduleAttendanceId.value = row.attendance_id ?? '';
    editEmployeeScheduleTemplateId.value = row.work_schedule
        ? String(row.work_schedule.id)
        : 'none';
    editEmployeeScheduleDialogOpen.value = true;
}

function onEditEmployeeScheduleDialogOpenChange(open: boolean): void {
    if (open) {
        if (editEmployeeScheduleDialogClearTimeout !== undefined) {
            clearTimeout(editEmployeeScheduleDialogClearTimeout);
            editEmployeeScheduleDialogClearTimeout = undefined;
        }
        editEmployeeScheduleDialogOpen.value = true;

        return;
    }

    editEmployeeScheduleDialogOpen.value = false;
    if (editEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(editEmployeeScheduleDialogClearTimeout);
    }

    editEmployeeScheduleDialogClearTimeout = setTimeout(() => {
        editEmployeeScheduleTarget.value = null;
        editEmployeeScheduleDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function editScheduleFirstErrorMessage(
    errors: Record<string, string | string[] | undefined>,
    fallback: string,
): string {
    const values = Object.values(errors);
    for (const value of values) {
        if (Array.isArray(value) && value.length > 0) {
            return value[0] ?? fallback;
        }
        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return fallback;
}

async function submitEditEmployeeSchedule(): Promise<void> {
    const target = editEmployeeScheduleTarget.value;
    if (target === null) {
        return;
    }

    const attendanceTrimmed = editEmployeeScheduleAttendanceId.value.trim();
    const templateRaw = editEmployeeScheduleTemplateId.value;
    const workScheduleTemplateId =
        templateRaw === 'none' ? null : Number(templateRaw);

    if (templateRaw !== 'none' && Number.isNaN(workScheduleTemplateId)) {
        appToast.error('Please select a valid work schedule template.');

        return;
    }

    const completion = new Promise<void>((resolve, reject) => {
        editEmployeeScheduleSaving.value = true;
        router.patch(
            workScheduleTemplate.update.url({ employee: target.id }),
            {
                work_schedule_template_id: workScheduleTemplateId,
                attendance_id:
                    attendanceTrimmed === '' ? null : attendanceTrimmed,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resolve();
                    onEditEmployeeScheduleDialogOpenChange(false);
                },
                onError: (errors) => {
                    reject(
                        new Error(
                            editScheduleFirstErrorMessage(
                                errors,
                                'Could not update attendance and schedule.',
                            ),
                        ),
                    );
                },
                onFinish: () => {
                    editEmployeeScheduleSaving.value = false;
                },
            },
        );
    });

    await appToast.promise(completion, {
        loading: 'Updating attendance and schedule…',
        success: 'Attendance and schedule updated.',
        error: (err: unknown) =>
            err instanceof Error && err.message.trim() !== ''
                ? err.message
                : 'Could not update attendance and schedule.',
    });
}

onUnmounted(() => {
    if (editEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(editEmployeeScheduleDialogClearTimeout);
    }
    if (viewEmployeeScheduleDialogClearTimeout !== undefined) {
        clearTimeout(viewEmployeeScheduleDialogClearTimeout);
    }
});

function buildQuery(
    overrides: Partial<{
        search: string;
        sort: EmployeeScheduleFilters['sort'];
        direction: EmployeeScheduleFilters['direction'];
        per_page: number;
        page: number;
        position_id: number | null;
        org_scope: EmployeeScheduleFilters['org_scope'];
        attendance_id_filter: EmployeeScheduleFilters['attendance_id_filter'];
        work_schedule_filter: EmployeeScheduleFilters['work_schedule_filter'];
        unit_filter: EmployeeScheduleFilters['unit_filter'];
    }> = {},
): Record<string, string | number> {
    const f: EmployeeScheduleFilters = { ...props.filters, ...overrides };
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
    if (f.org_scope !== null) {
        q.org_scope = f.org_scope;
    }
    if (f.attendance_id_filter !== null) {
        q.attendance_id_filter = f.attendance_id_filter;
    }
    if (f.work_schedule_filter !== null) {
        q.work_schedule_filter = f.work_schedule_filter;
    }
    if (
        f.unit_filter !== null &&
        f.unit_filter !== '' &&
        f.unit_filter !== 'all'
    ) {
        q.unit_filter = f.unit_filter;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        sort: EmployeeScheduleFilters['sort'];
        direction: EmployeeScheduleFilters['direction'];
        per_page: number;
        page: number;
        position_id: number | null;
        org_scope: EmployeeScheduleFilters['org_scope'];
        attendance_id_filter: EmployeeScheduleFilters['attendance_id_filter'];
        work_schedule_filter: EmployeeScheduleFilters['work_schedule_filter'];
        unit_filter: EmployeeScheduleFilters['unit_filter'];
    }> = {},
): void {
    router.get(
        employeeSchedules.url({ query: buildQuery(overrides) }),
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

function toggleSort(column: EmployeeScheduleFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

const UNIT_FILTER_ALL = 'all' as const;

const unitSelectModelValue = computed(
    () => props.filters.unit_filter ?? UNIT_FILTER_ALL,
);

function onUnitFilterChange(value: unknown): void {
    if (value === undefined || value === null || value === '') {
        applyQuery({ unit_filter: null, page: 1 });

        return;
    }

    if (typeof value !== 'string') {
        applyQuery({ unit_filter: null, page: 1 });

        return;
    }

    const next: EmployeeScheduleFilters['unit_filter'] =
        value === UNIT_FILTER_ALL ? null : value;
    applyQuery({ unit_filter: next, page: 1 });
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

const columns: ColumnDef<EmployeeScheduleRow>[] = [
    {
        id: 'employee',
        accessorKey: 'display_name',
        meta: {
            headClass: 'min-w-[11rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeeSchedulesIndexEmployeeColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                orgScope: props.filters.org_scope,
                onSortBy: (field: 'last_name' | 'id_number') =>
                    toggleSort(field),
                'onUpdate:orgScope': (
                    value: EmployeeScheduleFilters['org_scope'],
                ) => {
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
        id: 'positions',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeesIndexPositionColumnHeader, {
                modelValue: props.filters.position_id,
                options: props.positionFilterOptions,
                'onUpdate:modelValue': (value: number | null) => {
                    applyQuery({ position_id: value, page: 1 });
                },
            }),
        cell: ({ row }) =>
            h(EmployeesIndexPositionsCell, {
                positions: row.original.positions,
            }),
        enableSorting: false,
    },
    {
        id: 'attendance_id',
        meta: {
            headClass: 'min-w-[8rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeeSchedulesIndexAttendanceIdColumnHeader, {
                filter: props.filters.attendance_id_filter,
                'onUpdate:filter': (
                    value: EmployeeScheduleFilters['attendance_id_filter'],
                ) => {
                    applyQuery({
                        attendance_id_filter: value,
                        page: 1,
                    });
                },
            }),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'flex py-0.5 text-start' },
                h(
                    'span',
                    row.original.attendance_id
                        ? {
                              class: 'font-mono text-sm tabular-nums leading-normal text-foreground',
                          }
                        : {
                              class: 'text-sm leading-normal text-muted-foreground',
                          },
                    row.original.attendance_id ?? '—',
                ),
            ),
        enableSorting: false,
    },
    {
        id: 'work_schedule',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(EmployeeSchedulesIndexWorkScheduleColumnHeader, {
                filter: props.filters.work_schedule_filter,
                'onUpdate:filter': (
                    value: EmployeeScheduleFilters['work_schedule_filter'],
                ) => {
                    applyQuery({
                        work_schedule_filter: value,
                        page: 1,
                    });
                },
            }),
        cell: ({ row }) => {
            const ws = row.original.work_schedule;
            if (ws === null) {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                'div',
                { class: 'flex flex-col gap-1 py-0.5 text-start' },
                [
                    h(
                        'span',
                        {
                            class: 'text-sm font-medium leading-normal text-foreground',
                        },
                        ws.name,
                    ),
                    ws.is_active
                        ? null
                        : h(
                              Badge,
                              {
                                  variant: 'secondary',
                                  class: 'w-fit rounded-full border border-border/60 text-[11px] font-medium',
                              },
                              () => 'Inactive template',
                          ),
                ],
            );
        },
        enableSorting: false,
    },
    {
        id: 'actions',
        meta: {
            headClass: 'w-[78px] text-center',
            cellClass: 'text-center',
        },
        header: () =>
            h(
                'div',
                {
                    class: 'w-full text-center font-medium text-muted-foreground',
                },
                'Actions',
            ),
        cell: ({ row }) =>
            h(EmployeeSchedulesIndexActionsMenu, {
                row: row.original,
                onView: openViewEmployeeScheduleDialog,
                onEdit: openEditEmployeeScheduleDialog,
            }),
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
    <Head title="Employee Schedules" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div>
                <h1 class="text-xl font-semibold text-foreground">
                    Employee Schedules
                </h1>
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
                    <div class="w-full max-w-md">
                        <InputGroup class="max-w-md">
                            <InputGroupAddon align="inline-start">
                                <Search
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="employee_schedules_search"
                                :model-value="localSearch"
                                type="search"
                                placeholder="Search name, ID, attendance ID…"
                                autocomplete="off"
                                aria-label="Search employees for schedule unit settings"
                                @update:model-value="onSearchUpdate"
                                @keyup="onSearchKeyup"
                                @change="onSearchCommit"
                                @search="onSearchCommit"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <div
                        v-if="
                            organization !== null &&
                            unitFilterOptions.length > 1
                        "
                        class="flex w-full shrink-0 sm:w-auto"
                    >
                        <Select
                            :model-value="unitSelectModelValue"
                            @update:model-value="onUnitFilterChange"
                        >
                            <SelectTrigger
                                id="employee_schedule_unit_filter"
                                class="w-full justify-between text-start font-normal sm:w-54"
                                aria-label="Filter employees by unit"
                            >
                                <SelectValue placeholder="All units">
                                    <template #default="{ modelValue }">
                                        <HrisUnitSelectTriggerLabel
                                            :select-model-value="modelValue"
                                            :options="unitFilterOptions"
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in unitFilterOptions"
                                    :key="opt.value"
                                    class="items-center py-1.5"
                                    :value="opt.value"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-baseline gap-1 pr-1 leading-snug"
                                    >
                                        <span
                                            class="min-w-0 truncate text-sm text-foreground"
                                            >{{ opt.label }}</span
                                        >
                                        <span
                                            v-if="
                                                opt.code !== undefined &&
                                                opt.code !== null &&
                                                opt.code !== ''
                                            "
                                            class="shrink-0 font-mono text-xs text-muted-foreground"
                                            >{{ opt.code }}</span
                                        >
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No employees in this branch directory match your search."
                />

                <HrisServerTablePagination
                    :total="employees.total"
                    :from="employees.from"
                    :to="employees.to"
                    :current-page="employees.current_page"
                    :last-page="employees.last_page"
                    :per-page="employees.per_page"
                    :per-page-options="[10, 15, 25, 50]"
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

        <EmployeeScheduleViewDialog
            :open="viewEmployeeScheduleDialogOpen"
            :row="viewEmployeeScheduleTarget"
            @update:open="onViewEmployeeScheduleDialogOpenChange"
        />

        <Dialog
            :open="editEmployeeScheduleDialogOpen"
            @update:open="onEditEmployeeScheduleDialogOpenChange"
        >
            <DialogContent
                v-if="editEmployeeScheduleTarget"
                class="gap-4 sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Edit Attendance &amp; Schedule</DialogTitle>
                    <DialogDescription>
                        Update The Attendance ID And Work Schedule Template For
                        This Employee. Changes Apply When You Save.
                    </DialogDescription>
                </DialogHeader>
                <div class="-mx-0.5 grid gap-4 px-1 py-2 sm:-mx-1 sm:px-2">
                    <div class="grid min-w-0 gap-2">
                        <Label for="edit-employee-schedule-attendance-id"
                            >Attendance ID</Label
                        >
                        <Input
                            id="edit-employee-schedule-attendance-id"
                            v-model="editEmployeeScheduleAttendanceId"
                            type="text"
                            maxlength="50"
                            autocomplete="off"
                            placeholder="Optional External Attendance Identifier"
                            class="min-w-0 overflow-x-auto"
                        />
                        <p class="text-xs text-muted-foreground">
                            Used By Attendance Hardware Or Integrations. Maximum
                            50 Characters.
                        </p>
                    </div>
                    <div class="grid w-full min-w-0 gap-2">
                        <Label for="edit-employee-schedule-template"
                            >Work Schedule</Label
                        >
                        <Select v-model="editEmployeeScheduleTemplateId">
                            <SelectTrigger
                                id="edit-employee-schedule-template"
                                class="w-full min-w-0 shrink-0 justify-between text-start *:data-[slot=select-value]:line-clamp-none *:data-[slot=select-value]:min-w-0 *:data-[slot=select-value]:flex-1 *:data-[slot=select-value]:justify-start *:data-[slot=select-value]:overflow-x-auto *:data-[slot=select-value]:text-start"
                            >
                                <SelectValue placeholder="Select A Template…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Unassigned
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in editEmployeeScheduleTemplateOptions"
                                    :key="opt.id"
                                    :value="String(opt.id)"
                                >
                                    {{ opt.name
                                    }}<template v-if="!opt.is_active">
                                        (inactive)</template
                                    >
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Only active templates appear here unless this
                            employee still has an inactive template from a prior
                            unit setup.
                        </p>
                    </div>
                </div>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="editEmployeeScheduleSaving"
                        @click="onEditEmployeeScheduleDialogOpenChange(false)"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            editEmployeeScheduleTarget === null ||
                            editEmployeeScheduleSaving
                        "
                        @click="submitEditEmployeeSchedule"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
