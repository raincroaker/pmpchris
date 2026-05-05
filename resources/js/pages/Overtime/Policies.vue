<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { Plus, Search } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import type { PoliciesStatusFilter } from '@/pages/Leave/policiesIndexFilters';
import OvertimePoliciesActionsMenu from '@/pages/Overtime/OvertimePoliciesActionsMenu.vue';
import OvertimePoliciesIndexStatusColumnHeader from '@/pages/Overtime/OvertimePoliciesIndexStatusColumnHeader.vue';
import {
    OVERTIME_CONTEXT_LABELS,
    formatOvertimeRateMultiplier,
} from '@/pages/Overtime/overtimePolicyFormat';
import type {
    OvertimePolicy,
    OvertimePolicyContext,
    OvertimePolicyDraft,
} from '@/pages/Overtime/overtimePolicyTypes';
import { policies as overtimePoliciesRoute } from '@/routes/overtime';
import {
    destroy as overtimePoliciesDestroy,
    store as overtimePoliciesStore,
    update as overtimePoliciesUpdate,
} from '@/routes/overtime/policies';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overtime Policies', href: overtimePoliciesRoute() },
];

const page = usePage<{
    can?: { canMutateLeaveOvertimePolicies?: boolean };
}>();

const canMutateOvertimePolicies = computed(() =>
    Boolean(page.props.can?.canMutateLeaveOvertimePolicies),
);

const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

const dialogViewScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

const tablePlainHeadClass = 'font-medium text-muted-foreground';

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';

function defaultDraft(): OvertimePolicyDraft {
    return {
        code: '',
        name: '',
        context: 'ordinary_weekday',
        rateMultiplier: 1.25,
        dailyThresholdHours: 8,
        dailyCapHours: null,
        weeklyCapHours: null,
        requiresApproval: true,
        minimumLeadTimeHours: null,
        isActive: true,
        notes: '',
    };
}

function capsSummary(policy: OvertimePolicy): string {
    const parts: string[] = [];
    if (policy.dailyCapHours != null) {
        parts.push(`${policy.dailyCapHours}h/day max`);
    }
    if (policy.weeklyCapHours != null) {
        parts.push(`${policy.weeklyCapHours}h/wk max`);
    }
    if (parts.length === 0) {
        return '—';
    }

    return parts.join(' · ');
}

function thresholdLabel(policy: OvertimePolicy): string {
    if (policy.dailyThresholdHours <= 0) {
        return 'N/A (full day)';
    }

    return `After ${policy.dailyThresholdHours}h regular`;
}

function searchHaystack(policy: OvertimePolicy): string {
    return [
        policy.name,
        policy.code,
        OVERTIME_CONTEXT_LABELS[policy.context],
        String(policy.rateMultiplier),
        policy.notes ?? '',
        capsSummary(policy),
        thresholdLabel(policy),
    ]
        .join(' ')
        .toLowerCase();
}

function cloneDraftFromPolicy(row: OvertimePolicy): OvertimePolicyDraft {
    return {
        code: row.code,
        name: row.name,
        context: row.context,
        rateMultiplier: row.rateMultiplier,
        dailyThresholdHours: row.dailyThresholdHours,
        dailyCapHours: row.dailyCapHours,
        weeklyCapHours: row.weeklyCapHours,
        requiresApproval: row.requiresApproval,
        minimumLeadTimeHours: row.minimumLeadTimeHours,
        isActive: row.isActive,
        notes: row.notes ?? '',
    };
}

const props = withDefaults(
    defineProps<{
        overtimePolicies: OvertimePolicy[];
    }>(),
    {
        overtimePolicies: () => [],
    },
);

const policies = ref<OvertimePolicy[]>([...props.overtimePolicies]);

watch(
    () => props.overtimePolicies,
    (next) => {
        policies.value = [...next];
    },
    { deep: true },
);

const localSearch = ref('');
const perPage = ref(10);
const currentPage = ref(1);

const statusFilter = ref<PoliciesStatusFilter>('all');

const mutateDialogOpen = ref(false);
const isEditing = ref(false);
const editTargetId = ref<number | null>(null);
const activeDraft = ref<OvertimePolicyDraft>(defaultDraft());
const formError = ref<string | null>(null);

const viewDialogOpen = ref(false);
const viewTarget = ref<OvertimePolicy | null>(null);

const deleteDialogOpen = ref(false);
const deleteTarget = ref<OvertimePolicy | null>(null);

function openAddDialog(): void {
    isEditing.value = false;
    editTargetId.value = null;
    activeDraft.value = defaultDraft();
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openEditDialog(row: OvertimePolicy): void {
    isEditing.value = true;
    editTargetId.value = row.id;
    activeDraft.value = cloneDraftFromPolicy(row);
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openViewDialog(row: OvertimePolicy): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

function openDeleteDialog(row: OvertimePolicy): void {
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
}

function buildOvertimePayload(d: OvertimePolicyDraft): Record<string, unknown> {
    return {
        code: d.code.trim(),
        name: d.name.trim(),
        context: d.context,
        rate_multiplier: Number(d.rateMultiplier),
        daily_threshold_hours: Number(d.dailyThresholdHours),
        daily_cap_hours: d.dailyCapHours,
        weekly_cap_hours: d.weeklyCapHours,
        requires_approval: d.requiresApproval,
        minimum_lead_time_hours: d.minimumLeadTimeHours,
        is_active: d.isActive,
        notes: d.notes?.trim() === '' ? null : (d.notes?.trim() ?? null),
    };
}

function validateDraft(d: OvertimePolicyDraft): string | null {
    if (d.code.trim() === '') {
        return 'Policy code is required.';
    }
    if (d.name.trim() === '') {
        return 'Policy name is required.';
    }
    if (d.rateMultiplier <= 0) {
        return 'Rate multiplier must be greater than zero.';
    }
    if (d.dailyThresholdHours < 0) {
        return 'Daily threshold cannot be negative.';
    }

    return null;
}

function submitMutateDialog(): void {
    if (!canMutateOvertimePolicies.value) {
        formError.value =
            'You do not have permission to change overtime policies.';

        return;
    }

    const err = validateDraft(activeDraft.value);
    if (err) {
        formError.value = err;

        return;
    }
    formError.value = null;
    const d = activeDraft.value;
    const payload = buildOvertimePayload(d);

    if (isEditing.value && editTargetId.value != null) {
        router.patch(
            overtimePoliciesUpdate({
                overtimePolicy: editTargetId.value,
            }).url,
            payload as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    mutateDialogOpen.value = false;
                    appToast.success('Overtime policy updated.');
                },
                onError: () => {
                    formError.value =
                        'Could not save changes. Check the form and try again.';
                },
            },
        );

        return;
    }

    router.post(overtimePoliciesStore.url(), payload as RequestPayload, {
        preserveScroll: true,
        onSuccess: () => {
            mutateDialogOpen.value = false;
            appToast.success('Overtime policy added.');
        },
        onError: () => {
            formError.value =
                'Could not save changes. Check the form and try again.';
        },
    });
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        deleteDialogOpen.value = false;

        return;
    }
    if (!canMutateOvertimePolicies.value) {
        appToast.error(
            'You do not have permission to delete overtime policies.',
        );
        deleteDialogOpen.value = false;
        deleteTarget.value = null;

        return;
    }
    const id = deleteTarget.value.id;
    router.delete(overtimePoliciesDestroy({ overtimePolicy: id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            appToast.success('Overtime policy removed.');
        },
        onError: () => {
            appToast.error('Could not delete this policy.');
        },
    });
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

function policyMatchesStatusFilter(
    policy: OvertimePolicy,
    filter: PoliciesStatusFilter,
): boolean {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'active') {
        return policy.isActive;
    }

    return !policy.isActive;
}

const filteredPolicies = computed(() => {
    const q = localSearch.value.trim().toLowerCase();
    const sf = statusFilter.value;

    return policies.value.filter((policy) => {
        if (!policyMatchesStatusFilter(policy, sf)) {
            return false;
        }
        if (q === '') {
            return true;
        }

        return searchHaystack(policy).includes(q);
    });
});

const paginatedPolicies = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;

    return filteredPolicies.value.slice(start, start + perPage.value);
});

const totalRows = computed(() => filteredPolicies.value.length);

const emptyTableMessage = computed(() => {
    if (policies.value.length === 0) {
        return 'No Overtime Policies yet. Add an Overtime Policy to describe rates, thresholds, and caps.';
    }

    return 'No Overtime Policies match your search or filter.';
});

watch(statusFilter, () => setPage(1));

const fromRow = computed(() => {
    if (totalRows.value === 0) {
        return null;
    }

    return (currentPage.value - 1) * perPage.value + 1;
});

const toRow = computed(() => {
    if (totalRows.value === 0) {
        return null;
    }

    return Math.min(currentPage.value * perPage.value, totalRows.value);
});

const lastPage = computed(() =>
    Math.max(1, Math.ceil(totalRows.value / perPage.value)),
);

function setPage(next: number): void {
    currentPage.value = Math.min(Math.max(next, 1), lastPage.value);
}

function onSearchUpdate(value: string | number): void {
    localSearch.value = String(value ?? '');
    setPage(1);
}

function onPerPageChange(value: number): void {
    perPage.value = value;
    setPage(1);
}

const columns = computed((): ColumnDef<OvertimePolicy>[] => [
    {
        id: 'name',
        meta: { headClass: 'min-w-[12rem]', cellClass: 'whitespace-normal' },
        header: () => h('span', { class: tablePlainHeadClass }, 'Policy'),
        cell: ({ row }) => {
            const policy = row.original;

            return h(
                'div',
                { class: 'flex max-w-[18rem] flex-col gap-1 py-1' },
                [
                    h(
                        'div',
                        {
                            class: 'flex min-w-0 flex-wrap items-center gap-2',
                        },
                        [
                            h(
                                'span',
                                {
                                    class: 'min-w-0 font-medium text-foreground',
                                },
                                policy.name,
                            ),
                            h(
                                Badge,
                                { variant: 'outline', class: 'shrink-0' },
                                () => policy.code,
                            ),
                        ],
                    ),
                    h(
                        'span',
                        {
                            class: 'text-xs leading-snug text-muted-foreground',
                        },
                        OVERTIME_CONTEXT_LABELS[policy.context],
                    ),
                ],
            );
        },
    },
    {
        id: 'rate',
        header: () =>
            h('span', { class: tablePlainHeadClass }, 'Rate multiplier'),
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: 'text-sm font-medium tabular-nums text-foreground',
                },
                formatOvertimeRateMultiplier(row.original.rateMultiplier),
            ),
    },
    {
        id: 'threshold',
        meta: { cellClass: 'whitespace-normal' },
        header: () => h('span', { class: tablePlainHeadClass }, 'OT threshold'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'max-w-[14rem] text-sm text-muted-foreground' },
                thresholdLabel(row.original),
            ),
    },
    {
        id: 'caps',
        meta: { cellClass: 'whitespace-normal' },
        header: () => h('span', { class: tablePlainHeadClass }, 'Caps'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'max-w-[14rem] text-sm text-muted-foreground' },
                capsSummary(row.original),
            ),
    },
    {
        id: 'approval',
        header: () => h('span', { class: tablePlainHeadClass }, 'Approval'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-sm text-muted-foreground' },
                row.original.requiresApproval ? 'Required' : 'Self-serve',
            ),
    },
    {
        id: 'status',
        header: () =>
            h(OvertimePoliciesIndexStatusColumnHeader, {
                modelValue: statusFilter.value,
                'onUpdate:modelValue': (v: PoliciesStatusFilter) => {
                    statusFilter.value = v;
                },
            }),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: row.original.isActive
                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300',
                },
                () => (row.original.isActive ? 'Active' : 'Inactive'),
            ),
    },
    {
        id: 'actions',
        meta: { headClass: 'w-[100px] text-center', cellClass: 'text-center' },
        header: () =>
            h(
                'div',
                {
                    class: `w-full text-center ${tablePlainHeadClass}`,
                },
                'Actions',
            ),
        cell: ({ row }) =>
            h(OvertimePoliciesActionsMenu, {
                row: row.original,
                canMutate: canMutateOvertimePolicies,
                onView: openViewDialog,
                onEdit: openEditDialog,
                onDelete: openDeleteDialog,
            }),
    },
]);

const table = useVueTable({
    get data() {
        return paginatedPolicies.value;
    },
    get columns() {
        return columns.value;
    },
    getCoreRowModel: getCoreRowModel(),
});
</script>

<template>
    <Head title="Overtime Policies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Overtime Policies
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Configure overtime premiums—multipliers, when OT starts
                    after regular hours, daily or weekly caps, and filing rules.
                    Policies are stored per organization and used when filing
                    and approvals are wired to payroll.
                </p>
            </div>

            <HrisIndexToolbar>
                <template #start>
                    <div class="w-full max-w-md">
                        <InputGroup class="max-w-md">
                            <InputGroupAddon align="inline-start">
                                <Search
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="overtime-policies-search"
                                :model-value="localSearch"
                                placeholder="Search name, code, rate, context, or notes…"
                                @update:model-value="onSearchUpdate"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <Button
                        v-if="canMutateOvertimePolicies"
                        type="button"
                        class="shrink-0"
                        @click="openAddDialog"
                    >
                        <Plus class="size-4" />
                        <span class="mr-1">Add Overtime Policy</span>
                    </Button>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    :empty-message="emptyTableMessage"
                />

                <HrisServerTablePagination
                    :total="totalRows"
                    :from="fromRow"
                    :to="toRow"
                    :current-page="currentPage"
                    :last-page="lastPage"
                    :per-page="perPage"
                    :can-previous-page="currentPage > 1"
                    :can-next-page="currentPage < lastPage"
                    @update:per-page="onPerPageChange"
                    @go-first="setPage(1)"
                    @go-prev="setPage(currentPage - 1)"
                    @go-next="setPage(currentPage + 1)"
                    @go-last="setPage(lastPage)"
                />
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="viewDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>View Overtime Policy</DialogTitle>
                <DialogDescription>
                    Rate, work context, thresholds, caps, and approval rules.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="dialogViewScrollAreaClass">
                <div
                    v-if="viewTarget"
                    class="grid gap-3 px-2 py-2 text-sm sm:grid-cols-2"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Name & code
                        </p>
                        <div
                            class="flex min-h-10 flex-wrap items-center gap-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <span class="font-medium text-foreground">{{
                                viewTarget.name
                            }}</span>
                            <Badge variant="outline">{{
                                viewTarget.code
                            }}</Badge>
                        </div>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Context
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{ OVERTIME_CONTEXT_LABELS[viewTarget.context] }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Rate multiplier
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 font-medium text-foreground tabular-nums"
                        >
                            {{
                                formatOvertimeRateMultiplier(
                                    viewTarget.rateMultiplier,
                                )
                            }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Daily threshold
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{ thresholdLabel(viewTarget) }}
                        </p>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Caps
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{ capsSummary(viewTarget) }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Approval
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{
                                viewTarget.requiresApproval
                                    ? 'Required'
                                    : 'Self-serve'
                            }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Minimum lead time
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{
                                viewTarget.minimumLeadTimeHours != null
                                    ? `${viewTarget.minimumLeadTimeHours} hours before OT`
                                    : 'None'
                            }}
                        </p>
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
                                    viewTarget.isActive
                                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/15 dark:text-emerald-300'
                                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/15 dark:text-rose-300'
                                "
                            >
                                {{
                                    viewTarget.isActive ? 'Active' : 'Inactive'
                                }}
                            </Badge>
                        </div>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Notes
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 whitespace-pre-wrap text-foreground"
                        >
                            {{ viewTarget.notes ?? '—' }}
                        </p>
                    </div>
                </div>
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

    <Dialog v-model:open="mutateDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    isEditing ? 'Edit Overtime Policy' : 'Add Overtime Policy'
                }}</DialogTitle>
                <DialogDescription>
                    Mock form — values stay in this browser session until the
                    API is connected.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="dialogScrollAreaClass">
                <div class="grid gap-4 px-1 py-1">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ot-code">Code</Label>
                            <Input
                                id="ot-code"
                                v-model="activeDraft.code"
                                class="font-mono uppercase"
                                maxlength="16"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ot-context">Context</Label>
                            <Select
                                :model-value="activeDraft.context"
                                @update:model-value="
                                    (v) =>
                                        (activeDraft.context =
                                            v as OvertimePolicyContext)
                                "
                            >
                                <SelectTrigger
                                    id="ot-context"
                                    class="h-9 w-full"
                                >
                                    <SelectValue placeholder="Context" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ordinary_weekday">
                                        Ordinary weekday
                                    </SelectItem>
                                    <SelectItem value="rest_day">
                                        Rest day
                                    </SelectItem>
                                    <SelectItem value="regular_holiday">
                                        Regular holiday
                                    </SelectItem>
                                    <SelectItem value="special_holiday">
                                        Special holiday
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ot-name">Policy name</Label>
                        <Input
                            id="ot-name"
                            v-model="activeDraft.name"
                            autocomplete="off"
                        />
                    </div>

                    <Separator class="bg-border/70" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ot-rate">Rate multiplier</Label>
                            <Input
                                id="ot-rate"
                                v-model.number="activeDraft.rateMultiplier"
                                class="tabular-nums"
                                min="0.01"
                                step="0.01"
                                type="number"
                            />
                            <p class="text-xs text-muted-foreground">
                                Applied to the regular hourly rate (e.g. 1.25 =
                                125%).
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="ot-threshold"
                                >Regular hours before OT</Label
                            >
                            <Input
                                id="ot-threshold"
                                v-model.number="activeDraft.dailyThresholdHours"
                                class="tabular-nums"
                                min="0"
                                step="0.5"
                                type="number"
                            />
                            <p class="text-xs text-muted-foreground">
                                Use 0 when the whole shift is premium (e.g. rest
                                day).
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ot-daily-cap"
                                >Daily OT cap (optional)</Label
                            >
                            <Input
                                id="ot-daily-cap"
                                :model-value="activeDraft.dailyCapHours ?? ''"
                                class="tabular-nums"
                                min="0"
                                step="0.5"
                                type="number"
                                placeholder="No cap"
                                @update:model-value="
                                    (v) => {
                                        const s = String(v ?? '').trim();
                                        activeDraft.dailyCapHours =
                                            s === '' ? null : Number(s);
                                    }
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ot-weekly-cap"
                                >Weekly OT cap (optional)</Label
                            >
                            <Input
                                id="ot-weekly-cap"
                                :model-value="activeDraft.weeklyCapHours ?? ''"
                                class="tabular-nums"
                                min="0"
                                step="0.5"
                                type="number"
                                placeholder="No cap"
                                @update:model-value="
                                    (v) => {
                                        const s = String(v ?? '').trim();
                                        activeDraft.weeklyCapHours =
                                            s === '' ? null : Number(s);
                                    }
                                "
                            />
                        </div>
                    </div>

                    <Separator class="bg-border/70" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            class="flex items-center justify-between gap-3 rounded-md border border-border/50 px-3 py-2"
                        >
                            <Label class="text-sm font-normal"
                                >Requires approval</Label
                            >
                            <Switch
                                :checked="activeDraft.requiresApproval"
                                @update:checked="
                                    (v: boolean) =>
                                        (activeDraft.requiresApproval =
                                            Boolean(v))
                                "
                            />
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 rounded-md border border-border/50 px-3 py-2"
                        >
                            <Label class="text-sm font-normal">Active</Label>
                            <Switch
                                :checked="activeDraft.isActive"
                                @update:checked="
                                    (v: boolean) =>
                                        (activeDraft.isActive = Boolean(v))
                                "
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="ot-lead" :class="optionalLabelRowClass">
                            Minimum lead time (hours)
                            <Badge variant="outline">Optional</Badge>
                        </Label>
                        <Input
                            id="ot-lead"
                            :model-value="
                                activeDraft.minimumLeadTimeHours ?? ''
                            "
                            class="tabular-nums"
                            min="0"
                            step="1"
                            type="number"
                            placeholder="None"
                            @update:model-value="
                                (v: string | number) => {
                                    const s = String(v ?? '').trim();
                                    activeDraft.minimumLeadTimeHours =
                                        s === '' ? null : Number(s);
                                }
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ot-notes" :class="optionalLabelRowClass">
                            Notes
                            <Badge variant="outline">Optional</Badge>
                        </Label>
                        <Textarea
                            id="ot-notes"
                            :model-value="activeDraft.notes ?? ''"
                            rows="3"
                            class="resize-y"
                            placeholder="Legal references, shift rules, exceptions…"
                            @update:model-value="
                                (v: string | number) => {
                                    const s = String(v);
                                    activeDraft.notes =
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

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="mutateDialogOpen = false"
                    >Cancel</Button
                >
                <Button type="button" @click="submitMutateDialog">{{
                    isEditing ? 'Save changes' : 'Add Overtime Policy'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Overtime Policy?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="deleteTarget">
                        This removes
                        <span class="font-medium text-foreground">{{
                            deleteTarget.name
                        }}</span>
                        from the organization catalog. This cannot be undone.
                    </template>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
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
