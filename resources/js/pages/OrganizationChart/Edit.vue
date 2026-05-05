<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Building2, Check, MapPinned, Plus, Search } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { index as organizationChart } from '@/actions/App/Http/Controllers/OrganizationChartController';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import EditStructureActionsMenu from '@/pages/OrganizationChart/EditStructureActionsMenu.vue';
import EditStructureAreasDialog from '@/pages/OrganizationChart/EditStructureAreasDialog.vue';
import type {
    EditStructureAreaRow,
    EditStructureFilters,
    EditStructurePaginator,
    EditStructureRootUnitFilterOption,
    EditStructureRow,
} from '@/pages/OrganizationChart/editStructureTypes';
import {
    resolveUnitTypeColorLabel,
    resolveUnitTypeColorOption,
    unitTypeColorOptions,
} from '@/pages/OrganizationChart/unit-type-color-options';
import { edit as organizationChartEdit } from '@/routes/organization-chart';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    organization: {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
    } | null;
    areas: EditStructureAreaRow[];
    unitTypes: EditStructurePaginator;
    parentTypeOptions: Array<{ id: number; name: string }>;
    rootUnitFilterOptions: EditStructureRootUnitFilterOption[];
    filters: EditStructureFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organization chart', href: organizationChart() },
    { title: 'Edit Structure', href: organizationChartEdit() },
];

const addUnitTypeDialogOpen = ref(false);
const addUnitTypeName = ref('');
const addUnitTypeColor = ref('');
const addUnitTypeCanBeRoot = ref(false);
const addUnitTypeParentTypeIds = ref<number[]>([]);
const editUnitTypeDialogOpen = ref(false);
const editUnitTypeName = ref('');
const editUnitTypeColor = ref('');
const editUnitTypeCanBeRoot = ref(false);
const editUnitTypeParentTypeIds = ref<number[]>([]);
const editTargetRow = ref<EditStructureRow | null>(null);
const viewUnitsDialogOpen = ref(false);
const viewUnitsTargetRow = ref<EditStructureRow | null>(null);
const deactivateAlertOpen = ref(false);
const deactivateTargetRow = ref<EditStructureRow | null>(null);
const deleteAlertOpen = ref(false);
const deleteTargetRow = ref<EditStructureRow | null>(null);
const areasDialogOpen = ref(false);
const localAreas = ref<EditStructureAreaRow[]>(props.areas ?? []);

const addUnitTypeParentTypesValid = computed(() => {
    if (addUnitTypeCanBeRoot.value) return true;
    return addUnitTypeParentTypeIds.value.length > 0;
});

const editUnitTypeParentTypesValid = computed(() => {
    if (editUnitTypeCanBeRoot.value) return true;
    return editUnitTypeParentTypeIds.value.length > 0;
});

watch(
    () => props.areas,
    (next) => {
        if (!areasDialogOpen.value) {
            localAreas.value = next.map((row) => ({ ...row }));
        }
    },
    { deep: true },
);

function buildQuery(
    overrides: Partial<{
        search: string;
        sort: EditStructureFilters['sort'];
        direction: EditStructureFilters['direction'];
        per_page: number;
        page: number;
        root_unit_filter: number | null;
    }> = {},
): Record<string, string | number> {
    const filters: EditStructureFilters = { ...props.filters, ...overrides };
    const page = overrides.page ?? props.unitTypes.current_page;
    const query: Record<string, string | number> = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
        page,
    };
    if (filters.search.trim() !== '') {
        query.search = filters.search.trim();
    }
    if (filters.root_unit_filter !== null) {
        query.root_unit_filter = filters.root_unit_filter;
    }

    return query;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        sort: EditStructureFilters['sort'];
        direction: EditStructureFilters['direction'];
        per_page: number;
        page: number;
        root_unit_filter: number | null;
    }> = {},
): void {
    router.get(
        organizationChartEdit.url({ query: buildQuery(overrides) }),
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
    (value) => syncFromServerSearch(value),
);

function toggleSort(column: EditStructureFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDirection =
        same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDirection, page: 1 });
}

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

const ROOT_UNIT_FILTER_ALL = 'all' as const;

const rootUnitSelectModelValue = computed(
    () => props.filters.root_unit_filter?.toString() ?? ROOT_UNIT_FILTER_ALL,
);

function onRootUnitFilterChange(value: unknown): void {
    if (value === undefined || value === null || value === '') {
        applyQuery({ root_unit_filter: null, page: 1 });

        return;
    }

    if (typeof value !== 'string') {
        applyQuery({ root_unit_filter: null, page: 1 });

        return;
    }

    if (value === ROOT_UNIT_FILTER_ALL) {
        applyQuery({ root_unit_filter: null, page: 1 });

        return;
    }

    const parsed = Number(value);
    if (!Number.isInteger(parsed) || parsed <= 0) {
        applyQuery({ root_unit_filter: null, page: 1 });

        return;
    }

    applyQuery({ root_unit_filter: parsed, page: 1 });
}

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.unitTypes.current_page - 1,
        pageSize: props.unitTypes.per_page,
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

function openAddUnitTypeDialog(): void {
    addUnitTypeName.value = '';
    addUnitTypeColor.value = unitTypeColorOptions[0]?.hex ?? '';
    addUnitTypeCanBeRoot.value = false;
    addUnitTypeParentTypeIds.value = [];
    addUnitTypeDialogOpen.value = true;
}

function openAreasDialog(): void {
    localAreas.value = props.areas.map((row) => ({ ...row }));
    areasDialogOpen.value = true;
}

function onAreasUpdated(areas: EditStructureAreaRow[]): void {
    localAreas.value = areas.map((row) => ({ ...row }));
}

function submitAddUnitType(): void {
    router.post(
        '/organization-chart/edit/unit-types',
        {
            name: addUnitTypeName.value,
            description: '',
            color: addUnitTypeColor.value,
            can_be_root: addUnitTypeCanBeRoot.value,
            parent_type_ids: addUnitTypeCanBeRoot.value
                ? []
                : addUnitTypeParentTypeIds.value,
            ...buildQuery(),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                appToast.success('Unit type created.');
                addUnitTypeDialogOpen.value = false;
            },
            onError: (errors) => {
                appToast.error(
                    firstErrorMessage(errors, 'Could not create unit type.'),
                );
            },
        },
    );
}

function openEditUnitTypeDialog(row: EditStructureRow): void {
    editTargetRow.value = row;
    editUnitTypeName.value = row.name;
    editUnitTypeColor.value = row.color ?? '';
    editUnitTypeCanBeRoot.value = row.can_be_root;
    editUnitTypeParentTypeIds.value = [...row.allowed_parent_type_ids];
    editUnitTypeDialogOpen.value = true;
}

function submitEditUnitType(): void {
    if (!editTargetRow.value) {
        return;
    }

    router.patch(
        editUnitTypeMutationUrl(editTargetRow.value.id),
        {
            name: editUnitTypeName.value,
            description: editTargetRow.value.description ?? '',
            color: editUnitTypeColor.value,
            can_be_root: editUnitTypeCanBeRoot.value,
            parent_type_ids: editUnitTypeCanBeRoot.value
                ? []
                : editUnitTypeParentTypeIds.value,
            ...buildQuery(),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                appToast.success('Unit type updated.');
                editUnitTypeDialogOpen.value = false;
                editTargetRow.value = null;
            },
            onError: (errors) => {
                appToast.error(
                    firstErrorMessage(errors, 'Could not update unit type.'),
                );
            },
        },
    );
}

function openViewUnitsDialog(row: EditStructureRow): void {
    viewUnitsTargetRow.value = row;
    viewUnitsDialogOpen.value = true;
}

function openDeactivateDialog(row: EditStructureRow): void {
    deactivateTargetRow.value = row;
    deactivateAlertOpen.value = true;
}

function openDeleteDialog(row: EditStructureRow): void {
    deleteTargetRow.value = row;
    deleteAlertOpen.value = true;
}

function firstErrorMessage(
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

function editUnitTypeMutationUrl(unitTypeId: number): string {
    return `/organization-chart/edit/unit-types/${unitTypeId}`;
}

function confirmDeactivate(): void {
    if (!deactivateTargetRow.value) {
        return;
    }

    const row = deactivateTargetRow.value;
    router.patch(
        `${editUnitTypeMutationUrl(row.id)}/deactivate`,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                appToast.success('Unit type deactivated.');
                deactivateAlertOpen.value = false;
                deactivateTargetRow.value = null;
            },
            onError: (errors) => {
                appToast.error(
                    firstErrorMessage(
                        errors,
                        'Could not deactivate unit type.',
                    ),
                );
            },
        },
    );
}

function confirmDelete(): void {
    if (!deleteTargetRow.value) {
        return;
    }

    const row = deleteTargetRow.value;
    router.delete(editUnitTypeMutationUrl(row.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            appToast.success('Unit type deleted.');
            deleteAlertOpen.value = false;
            deleteTargetRow.value = null;
        },
        onError: (errors) => {
            appToast.error(
                firstErrorMessage(errors, 'Could not delete unit type.'),
            );
        },
    });
}

function toggleParentTypeSelection(
    model: typeof addUnitTypeParentTypeIds | typeof editUnitTypeParentTypeIds,
    parentTypeId: number,
    checked: boolean | 'indeterminate',
): void {
    const isChecked = checked === true;
    if (isChecked) {
        if (!model.value.includes(parentTypeId)) {
            model.value = [...model.value, parentTypeId];
        }

        return;
    }

    model.value = model.value.filter((id) => id !== parentTypeId);
}

function onAddParentTypeToggle(
    parentTypeId: number,
    checked: boolean | 'indeterminate',
): void {
    toggleParentTypeSelection(addUnitTypeParentTypeIds, parentTypeId, checked);
}

function onEditParentTypeToggle(
    parentTypeId: number,
    checked: boolean | 'indeterminate',
): void {
    toggleParentTypeSelection(editUnitTypeParentTypeIds, parentTypeId, checked);
}

watch(addUnitTypeCanBeRoot, (isRoot) => {
    if (isRoot) {
        addUnitTypeParentTypeIds.value = [];
    }
});

watch(editUnitTypeCanBeRoot, (isRoot) => {
    if (isRoot) {
        editUnitTypeParentTypeIds.value = [];
    }
});

const columns: ColumnDef<EditStructureRow>[] = [
    {
        accessorKey: 'name',
        meta: {
            headClass: 'w-[32%] min-w-[12rem]',
            cellClass: 'align-middle min-w-0',
        },
        header: () =>
            h(
                'button',
                {
                    type: 'button',
                    class: 'font-medium text-muted-foreground transition-colors hover:text-foreground',
                    onClick: () => toggleSort('name'),
                },
                'Name',
            ),
        cell: ({ row }) =>
            h('div', { class: 'flex min-w-0 flex-col gap-0 py-0.5' }, [
                h('div', { class: 'inline-flex min-w-0 items-center gap-2' }, [
                    h(
                        'span',
                        {
                            class: 'min-w-0 truncate font-medium text-foreground',
                        },
                        row.original.name,
                    ),
                    row.original.can_be_root
                        ? h(
                              Badge,
                              {
                                  variant: 'secondary',
                                  class: 'rounded-full border border-border/60 text-[11px] font-medium',
                              },
                              () => 'Root Unit',
                          )
                        : null,
                ]),
            ]),
    },
    {
        accessorKey: 'units_count',
        meta: {
            headClass: 'w-[140px]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(
                'button',
                {
                    type: 'button',
                    class: 'font-medium text-muted-foreground transition-colors hover:text-foreground',
                    onClick: () => toggleSort('id'),
                },
                'Units',
            ),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-0 py-0.5' }, [
                h(
                    Badge,
                    {
                        variant: 'outline',
                        class:
                            row.original.units_count > 0
                                ? 'w-fit rounded-full border-sky-500/30 bg-sky-500/10 px-2 py-0 text-[11px] font-medium text-sky-700 dark:border-sky-400/30 dark:bg-sky-400/15 dark:text-sky-300'
                                : 'w-fit rounded-full px-2 py-0 text-[11px] font-medium',
                    },
                    () =>
                        `${row.original.units_count} Unit${row.original.units_count === 1 ? '' : 's'}`,
                ),
            ]),
    },
    {
        accessorKey: 'color',
        meta: {
            headClass: 'w-[150px]',
            cellClass: 'text-left',
        },
        header: () =>
            h(
                'button',
                {
                    type: 'button',
                    class: 'font-medium text-muted-foreground transition-colors hover:text-foreground',
                    onClick: () => toggleSort('id'),
                },
                'Color',
            ),
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-3 py-1' }, [
                h('span', {
                    class: 'inline-flex size-4 rounded-full border border-border/70',
                    style: {
                        backgroundColor: row.original.color ?? 'transparent',
                    },
                }),
                h(
                    'span',
                    { class: 'text-xs font-medium text-muted-foreground' },
                    resolveUnitTypeColorLabel(row.original.color),
                ),
            ]),
    },
    {
        id: 'status',
        accessorKey: 'is_active',
        meta: {
            headClass: 'w-[130px]',
            cellClass: 'align-middle',
        },
        header: () =>
            h('span', { class: 'font-medium text-muted-foreground' }, 'Status'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: row.original.is_active
                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300',
                },
                () => (row.original.is_active ? 'Active' : 'Inactive'),
            ),
        enableSorting: false,
    },
    {
        id: 'actions',
        meta: {
            headClass: 'w-[88px] text-center',
            cellClass: 'text-center',
        },
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Actions',
            ),
        cell: ({ row }) =>
            h(EditStructureActionsMenu, {
                row: row.original,
                onViewUnits: (unitType: EditStructureRow) =>
                    openViewUnitsDialog(unitType),
                onEdit: (unitType: EditStructureRow) =>
                    openEditUnitTypeDialog(unitType),
                onDeactivate: (unitType: EditStructureRow) =>
                    openDeactivateDialog(unitType),
                onDelete: (unitType: EditStructureRow) =>
                    openDeleteDialog(unitType),
            }),
        enableSorting: false,
    },
];

const table = useVueTable({
    get data() {
        return props.unitTypes.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    rowCount: computed(() => props.unitTypes.total).value,
    state: {
        get pagination() {
            return {
                pageIndex: props.unitTypes.current_page - 1,
                pageSize: props.unitTypes.per_page,
            };
        },
    },
    onPaginationChange,
});
</script>

<template>
    <Head title="Edit organization structure" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Edit Structure
                </h1>
                <p class="text-sm text-muted-foreground">
                    Review the current organization context and browse
                    organizational unit types in a table-first management view.
                </p>
            </div>

            <div
                class="rounded-lg border border-primary/30 bg-muted/30 p-4 dark:bg-muted/25"
            >
                <div class="flex flex-row items-center gap-4">
                    <span
                        class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/10 p-4 text-primary"
                    >
                        <Building2 class="size-8 shrink-0" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1 space-y-1">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Organization
                        </p>
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span
                                class="min-w-0 truncate text-lg leading-snug font-semibold tracking-tight text-foreground"
                                :title="organization?.name ?? 'Organization'"
                            >
                                {{
                                    organization?.name ??
                                    'No organization configured'
                                }}
                            </span>
                            <Badge
                                v-if="organization"
                                variant="outline"
                                class="h-5 shrink-0 border-foreground/40 px-1.5 font-mono text-[11px] tracking-[0.12em] uppercase"
                            >
                                {{ organization.code }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <HrisIndexToolbar>
                <template #start>
                    <div class="flex w-full flex-col gap-2 sm:max-w-sm">
                        <InputGroup>
                            <InputGroupAddon>
                                <Search class="size-4 text-muted-foreground" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="edit-structure-search"
                                :model-value="localSearch"
                                placeholder="Search by type name or description"
                                @update:model-value="onSearchUpdate"
                                @keyup="onSearchKeyup"
                                @change="onSearchCommit"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <div class="flex items-center gap-2">
                        <Select
                            v-if="rootUnitFilterOptions.length > 1"
                            :model-value="rootUnitSelectModelValue"
                            @update:model-value="onRootUnitFilterChange"
                        >
                            <SelectTrigger
                                id="edit_structure_root_unit_filter"
                                class="w-full justify-between text-start font-normal sm:w-54"
                                aria-label="Filter by root unit"
                            >
                                <SelectValue placeholder="All units">
                                    <template #default="{ modelValue }">
                                        <HrisUnitSelectTriggerLabel
                                            :select-model-value="modelValue"
                                            :options="rootUnitFilterOptions"
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in rootUnitFilterOptions"
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
                        <Button
                            type="button"
                            variant="secondary"
                            class="gap-2 border border-border/60 shadow-sm hover:bg-accent/80"
                            @click="openAreasDialog"
                        >
                            <MapPinned class="size-4" />
                            Areas
                        </Button>
                        <Button type="button" @click="openAddUnitTypeDialog">
                            <Plus class="size-4" />
                            Add Unit Type
                        </Button>
                    </div>
                </template>
            </HrisIndexToolbar>

            <EditStructureAreasDialog
                v-model:open="areasDialogOpen"
                :areas="localAreas"
                @areas-updated="onAreasUpdated"
            />

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No organizational unit types found."
                />

                <HrisServerTablePagination
                    :total="unitTypes.total"
                    :from="unitTypes.from"
                    :to="unitTypes.to"
                    :current-page="unitTypes.current_page"
                    :last-page="unitTypes.last_page"
                    :per-page="unitTypes.per_page"
                    :can-previous-page="unitTypes.current_page > 1"
                    :can-next-page="
                        unitTypes.current_page < unitTypes.last_page
                    "
                    @update:per-page="onPerPageChange"
                    @go-first="applyQuery({ page: 1 })"
                    @go-prev="applyQuery({ page: unitTypes.current_page - 1 })"
                    @go-next="applyQuery({ page: unitTypes.current_page + 1 })"
                    @go-last="applyQuery({ page: unitTypes.last_page })"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewUnitsDialogOpen">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader class="gap-1.5">
                <DialogTitle>View units</DialogTitle>
                <DialogDescription>
                    Review all organizational units currently assigned to this
                    unit type.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="pr-3">
                    <div
                        v-if="viewUnitsTargetRow"
                        class="mb-2 flex items-center gap-2"
                    >
                        <span class="text-sm font-medium text-foreground">
                            {{ viewUnitsTargetRow.name }}
                        </span>
                        <Badge
                            variant="outline"
                            class="rounded-full border-sky-500/30 bg-sky-500/10 px-2 py-0 text-[11px] font-medium text-sky-700 dark:border-sky-400/30 dark:bg-sky-400/15 dark:text-sky-300"
                        >
                            {{ viewUnitsTargetRow.units_count }} Units
                        </Badge>
                    </div>

                    <ScrollArea
                        class="h-[min(46vh,360px)] **:data-[slot=scroll-area-viewport]:focus-visible:ring-0 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow
                                    class="border-b border-border/70 bg-primary/5 hover:bg-primary/5"
                                >
                                    <TableHead class="h-11 px-3"
                                        >Name</TableHead
                                    >
                                    <TableHead class="h-11 px-3"
                                        >Parent</TableHead
                                    >
                                    <TableHead class="h-11 px-3"
                                        >Status</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="unit in viewUnitsTargetRow?.units ??
                                    []"
                                    :key="unit.id"
                                    class="transition-colors hover:bg-accent/30"
                                >
                                    <TableCell
                                        class="px-3 py-3.5 font-medium text-foreground"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span>{{ unit.name }}</span>
                                            <span
                                                v-if="
                                                    unit.code !== null &&
                                                    unit.code !== ''
                                                "
                                                class="font-mono text-xs tracking-[0.08em] text-muted-foreground uppercase"
                                            >
                                                {{ unit.code }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="px-3 py-3.5 text-muted-foreground"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span>{{
                                                unit.parent_name ??
                                                'Top-level unit'
                                            }}</span>
                                            <span
                                                v-if="
                                                    unit.parent_name !== null &&
                                                    unit.parent_code !== null &&
                                                    unit.parent_code !== ''
                                                "
                                                class="font-mono text-xs tracking-[0.08em] text-muted-foreground uppercase"
                                            >
                                                {{ unit.parent_code }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-3 py-3.5">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                unit.is_active
                                                    ? 'rounded-full border-emerald-500/30 bg-emerald-500/10 px-2 py-0 text-[11px] font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                                                    : 'rounded-full border-rose-500/30 bg-rose-500/10 px-2 py-0 text-[11px] font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300'
                                            "
                                        >
                                            {{
                                                unit.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-if="
                                        (viewUnitsTargetRow?.units.length ??
                                            0) === 0
                                    "
                                >
                                    <TableCell
                                        colspan="3"
                                        class="h-28 px-3 text-center text-sm text-muted-foreground"
                                    >
                                        No organizational units are using this
                                        type yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </ScrollArea>
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="viewUnitsDialogOpen = false"
                >
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="addUnitTypeDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add unit type</DialogTitle>
                <DialogDescription>
                    Create a new organizational unit type for the structure
                    editor.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-2 py-2">
                <Label for="add-edit-structure-unit-name">Type name</Label>
                <Input
                    id="add-edit-structure-unit-name"
                    v-model="addUnitTypeName"
                    placeholder="Enter unit type name"
                />
                <Label>Color</Label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <Button
                        v-for="option in unitTypeColorOptions"
                        :key="option.key"
                        type="button"
                        variant="outline"
                        class="h-10 justify-start gap-2 px-2.5"
                        :title="option.label"
                        :class="
                            addUnitTypeColor === option.hex
                                ? 'border-primary bg-primary/8 text-foreground'
                                : ''
                        "
                        @click="addUnitTypeColor = option.hex"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                            :style="{ backgroundColor: option.hex }"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ option.label }}</span>
                        <Check
                            v-if="addUnitTypeColor === option.hex"
                            class="ml-auto size-3.5 text-primary"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Selected:
                    <span class="font-medium text-foreground">
                        {{
                            resolveUnitTypeColorOption(addUnitTypeColor)
                                ?.label ?? 'No color'
                        }}
                    </span>
                </p>
                <div
                    class="mt-2 flex items-center justify-between rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                >
                    <div class="space-y-0.5">
                        <p class="text-sm text-foreground">Is root type</p>
                        <p class="text-xs text-muted-foreground">
                            Can be used as a top-level type.
                        </p>
                    </div>
                    <Switch v-model="addUnitTypeCanBeRoot" />
                </div>
                <div
                    v-if="addUnitTypeCanBeRoot"
                    class="text-xs text-muted-foreground"
                >
                    Root types don’t need parent types.
                </div>
                <div v-else class="grid gap-2">
                    <Label>Allowed parent types</Label>
                    <div
                        class="rounded-md border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="space-y-2">
                            <label
                                v-for="option in parentTypeOptions"
                                :key="option.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :model-value="
                                        addUnitTypeParentTypeIds.includes(
                                            option.id,
                                        )
                                    "
                                    @update:model-value="
                                        onAddParentTypeToggle(option.id, $event)
                                    "
                                />
                                <span class="text-sm text-foreground">{{
                                    option.name
                                }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Select at least one parent type.
                    </p>
                    <p
                        v-if="addUnitTypeParentTypeIds.length === 0"
                        class="text-xs text-destructive"
                    >
                        Parent type is required when this type is not root.
                    </p>
                </div>
            </div>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="addUnitTypeDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="!addUnitTypeParentTypesValid"
                    @click="submitAddUnitType"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editUnitTypeDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit unit type</DialogTitle>
                <DialogDescription>
                    Update the selected unit type details.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-2 py-2">
                <Label for="edit-structure-unit-name">Type name</Label>
                <Input
                    id="edit-structure-unit-name"
                    v-model="editUnitTypeName"
                    placeholder="Enter unit type name"
                />
                <Label>Color</Label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <Button
                        v-for="option in unitTypeColorOptions"
                        :key="option.key"
                        type="button"
                        variant="outline"
                        class="h-10 justify-start gap-2 px-2.5"
                        :title="option.label"
                        :class="
                            editUnitTypeColor === option.hex
                                ? 'border-primary bg-primary/8 text-foreground'
                                : ''
                        "
                        @click="editUnitTypeColor = option.hex"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full ring-1 ring-border/60"
                            :style="{ backgroundColor: option.hex }"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ option.label }}</span>
                        <Check
                            v-if="editUnitTypeColor === option.hex"
                            class="ml-auto size-3.5 text-primary"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Selected:
                    <span class="font-medium text-foreground">
                        {{
                            resolveUnitTypeColorOption(editUnitTypeColor)
                                ?.label ?? 'No color'
                        }}
                    </span>
                </p>
                <div
                    class="mt-2 flex items-center justify-between rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                >
                    <div class="space-y-0.5">
                        <p class="text-sm text-foreground">Is root type</p>
                        <p class="text-xs text-muted-foreground">
                            Can be used as a top-level type.
                        </p>
                    </div>
                    <Switch v-model="editUnitTypeCanBeRoot" />
                </div>
                <div
                    v-if="editUnitTypeCanBeRoot"
                    class="text-xs text-muted-foreground"
                >
                    Root types don’t need parent types.
                </div>
                <div v-else class="grid gap-2">
                    <Label>Allowed parent types</Label>
                    <div
                        class="rounded-md border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="space-y-2">
                            <label
                                v-for="option in parentTypeOptions"
                                :key="option.id"
                                class="flex items-center gap-3"
                            >
                                <Checkbox
                                    :model-value="
                                        editUnitTypeParentTypeIds.includes(
                                            option.id,
                                        )
                                    "
                                    @update:model-value="
                                        onEditParentTypeToggle(
                                            option.id,
                                            $event,
                                        )
                                    "
                                />
                                <span class="text-sm text-foreground">{{
                                    option.name
                                }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Select at least one parent type.
                    </p>
                    <p
                        v-if="editUnitTypeParentTypeIds.length === 0"
                        class="text-xs text-destructive"
                    >
                        Parent type is required when this type is not root.
                    </p>
                </div>
            </div>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="editUnitTypeDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="!editUnitTypeParentTypesValid"
                    @click="submitEditUnitType"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deactivateAlertOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Deactivate unit type?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="deactivateTargetRow">
                        This will mark
                        <span class="font-medium text-foreground">{{
                            deactivateTargetRow.name
                        }}</span
                        >. as inactive. Existing organizational units using this
                        type will stay intact.
                    </template>
                    <template v-else>
                        Deactivate the selected unit type.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel> Cancel </AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="confirmDeactivate"
                >
                    Deactivate
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="deleteAlertOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete unit type?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="deleteTargetRow">
                        This will permanently delete
                        <span class="font-medium text-foreground">{{
                            deleteTargetRow.name
                        }}</span
                        >. This action is blocked when organizational units
                        still reference this type.
                    </template>
                    <template v-else>
                        Delete the selected unit type permanently.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel> Cancel </AlertDialogCancel>
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
