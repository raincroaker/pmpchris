<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { Plus, Search } from 'lucide-vue-next';
import { computed, h, ref, watch, withDefaults } from 'vue';
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
import type {
    LeavePolicy,
    LeavePolicyAccrualCadence,
    LeavePolicyDraft,
    LeavePolicyUnit,
} from '@/pages/Leave/leavePolicyTypes';
import PoliciesActionsMenu from '@/pages/Leave/PoliciesActionsMenu.vue';
import type { PoliciesStatusFilter } from '@/pages/Leave/policiesIndexFilters';
import PoliciesIndexStatusColumnHeader from '@/pages/Leave/PoliciesIndexStatusColumnHeader.vue';
import { policies as leavePoliciesRoute } from '@/routes/leave';
import {
    destroy as leavePoliciesDestroy,
    store as leavePoliciesStore,
    update as leavePoliciesUpdate,
} from '@/routes/leave/policies';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave Policies', href: leavePoliciesRoute() },
];

const page = usePage<{
    can?: { canMutateLeaveOvertimePolicies?: boolean };
}>();

const canMutateLeavePolicies = computed(() =>
    Boolean(page.props.can?.canMutateLeaveOvertimePolicies),
);

const dialogScrollAreaClass =
    'max-h-[70vh] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

const dialogViewScrollAreaClass =
    'max-h-[min(70vh,520px)] pr-3 **:data-[slot=scroll-area-viewport]:focus-visible:outline-none **:data-[slot=scroll-area-viewport]:focus-visible:ring-0';

const tablePlainHeadClass = 'font-medium text-muted-foreground';

const optionalLabelRowClass =
    'relative flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1 pr-8';

function defaultDraft(): LeavePolicyDraft {
    return {
        code: '',
        name: '',
        unit: 'days',
        annualEntitlement: 15,
        useAccrual: false,
        accrualCadence: null,
        accrualPerPeriod: null,
        maxBalance: null,
        carryoverAllowed: true,
        carryoverCap: null,
        paid: true,
        requiresApproval: true,
        appliesAfterMonths: null,
        isActive: true,
        notes: '',
    };
}

function entitlementSummary(policy: LeavePolicy): string {
    const u = policy.unit === 'days' ? 'd' : 'h';
    if (policy.annualEntitlement <= 0 && !policy.paid) {
        return '—';
    }
    return `${policy.annualEntitlement} ${u}/yr`;
}

function accrualSummary(policy: LeavePolicy): string {
    if (!policy.useAccrual) {
        return 'Annual grant';
    }
    const cadence =
        policy.accrualCadence === 'pay_period'
            ? 'pay period'
            : policy.accrualCadence === 'monthly'
              ? 'monthly'
              : 'accrual';
    const rate =
        policy.accrualPerPeriod != null
            ? `${policy.accrualPerPeriod} ${policy.unit === 'days' ? 'd' : 'h'}`
            : '—';
    return `Accrual · ${cadence} (${rate})`;
}

function cloneDraftFromPolicy(row: LeavePolicy): LeavePolicyDraft {
    return {
        code: row.code,
        name: row.name,
        unit: row.unit,
        annualEntitlement: row.annualEntitlement,
        useAccrual: row.useAccrual,
        accrualCadence: row.accrualCadence,
        accrualPerPeriod: row.accrualPerPeriod,
        maxBalance: row.maxBalance,
        carryoverAllowed: row.carryoverAllowed,
        carryoverCap: row.carryoverCap,
        paid: row.paid,
        requiresApproval: row.requiresApproval,
        appliesAfterMonths: row.appliesAfterMonths,
        isActive: row.isActive,
        notes: row.notes ?? '',
    };
}

const props = withDefaults(
    defineProps<{
        leavePolicies: LeavePolicy[];
    }>(),
    {
        leavePolicies: () => [],
    },
);

const policies = ref<LeavePolicy[]>([...props.leavePolicies]);

watch(
    () => props.leavePolicies,
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
const activeDraft = ref<LeavePolicyDraft>(defaultDraft());
const formError = ref<string | null>(null);

const viewDialogOpen = ref(false);
const viewTarget = ref<LeavePolicy | null>(null);

const deleteDialogOpen = ref(false);
const deleteTarget = ref<LeavePolicy | null>(null);

function openAddDialog(): void {
    isEditing.value = false;
    editTargetId.value = null;
    activeDraft.value = defaultDraft();
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openEditDialog(row: LeavePolicy): void {
    isEditing.value = true;
    editTargetId.value = row.id;
    activeDraft.value = cloneDraftFromPolicy(row);
    formError.value = null;
    mutateDialogOpen.value = true;
}

function openViewDialog(row: LeavePolicy): void {
    viewTarget.value = row;
    viewDialogOpen.value = true;
}

function openDeleteDialog(row: LeavePolicy): void {
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
}

watch(
    () => activeDraft.value.useAccrual,
    (on) => {
        if (!on) {
            activeDraft.value.accrualCadence = null;
            activeDraft.value.accrualPerPeriod = null;
        } else {
            activeDraft.value.accrualCadence =
                activeDraft.value.accrualCadence ?? 'monthly';
            if (activeDraft.value.accrualPerPeriod == null) {
                activeDraft.value.accrualPerPeriod = 0;
            }
        }
    },
);

watch(
    () => activeDraft.value.carryoverAllowed,
    (on) => {
        if (!on) {
            activeDraft.value.carryoverCap = null;
        }
    },
);

function buildLeavePayload(d: LeavePolicyDraft): Record<string, unknown> {
    return {
        code: d.code.trim(),
        name: d.name.trim(),
        unit: d.unit,
        annual_entitlement: Number(d.annualEntitlement),
        use_accrual: d.useAccrual,
        accrual_cadence: d.useAccrual ? d.accrualCadence : null,
        accrual_per_period: d.useAccrual ? Number(d.accrualPerPeriod) : null,
        max_balance: d.maxBalance,
        carryover_allowed: d.carryoverAllowed,
        carryover_cap: d.carryoverAllowed ? d.carryoverCap : null,
        paid: d.paid,
        requires_approval: d.requiresApproval,
        applies_after_months: d.appliesAfterMonths,
        is_active: d.isActive,
        notes: d.notes?.trim() === '' ? null : (d.notes?.trim() ?? null),
    };
}

function validateDraft(d: LeavePolicyDraft): string | null {
    if (d.code.trim() === '') {
        return 'Policy code is required.';
    }
    if (d.name.trim() === '') {
        return 'Policy name is required.';
    }
    if (d.useAccrual) {
        if (!d.accrualCadence) {
            return 'Select an accrual cadence.';
        }
        if (
            d.accrualPerPeriod == null ||
            Number.isNaN(Number(d.accrualPerPeriod))
        ) {
            return 'Accrual amount per period is required.';
        }
        if (Number(d.accrualPerPeriod) < 0) {
            return 'Accrual amount cannot be negative.';
        }
    }
    if (d.annualEntitlement < 0) {
        return 'Annual entitlement cannot be negative.';
    }

    return null;
}

function submitMutateDialog(): void {
    if (!canMutateLeavePolicies.value) {
        formError.value =
            'You do not have permission to change leave policies.';

        return;
    }

    const err = validateDraft(activeDraft.value);
    if (err) {
        formError.value = err;

        return;
    }
    formError.value = null;
    const d = activeDraft.value;
    const payload = buildLeavePayload(d);

    if (isEditing.value && editTargetId.value != null) {
        router.patch(
            leavePoliciesUpdate({ leavePolicy: editTargetId.value }).url,
            payload as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    mutateDialogOpen.value = false;
                    appToast.success('Leave policy updated.');
                },
                onError: () => {
                    formError.value =
                        'Could not save changes. Check the form and try again.';
                },
            },
        );

        return;
    }

    router.post(leavePoliciesStore.url(), payload as RequestPayload, {
        preserveScroll: true,
        onSuccess: () => {
            mutateDialogOpen.value = false;
            appToast.success('Leave policy added.');
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
    if (!canMutateLeavePolicies.value) {
        appToast.error('You do not have permission to delete leave policies.');
        deleteDialogOpen.value = false;
        deleteTarget.value = null;

        return;
    }
    const id = deleteTarget.value.id;
    router.delete(leavePoliciesDestroy({ leavePolicy: id }).url, {
        preserveScroll: true,
        onSuccess: () => {
            appToast.success('Leave policy removed.');
        },
        onError: () => {
            appToast.error('Could not delete this policy.');
        },
    });
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
}

function policyMatchesStatusFilter(
    policy: LeavePolicy,
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
        const notes = (policy.notes ?? '').toLowerCase();

        return (
            policy.name.toLowerCase().includes(q) ||
            policy.code.toLowerCase().includes(q) ||
            accrualSummary(policy).toLowerCase().includes(q) ||
            notes.includes(q)
        );
    });
});

const paginatedPolicies = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;

    return filteredPolicies.value.slice(start, start + perPage.value);
});

const totalRows = computed(() => filteredPolicies.value.length);

const emptyTableMessage = computed(() => {
    if (policies.value.length === 0) {
        return 'No Leave Policies yet. Add a Leave Policy to describe entitlements and rules.';
    }

    return 'No Leave Policies match your search or filter.';
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

const columns = computed((): ColumnDef<LeavePolicy>[] => [
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
                        policy.unit === 'days' ? 'Days' : 'Hours',
                    ),
                ],
            );
        },
    },
    {
        id: 'entitlement',
        header: () => h('span', { class: tablePlainHeadClass }, 'Entitlement'),
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: 'text-sm tabular-nums text-foreground',
                },
                entitlementSummary(row.original),
            ),
    },
    {
        id: 'accrual',
        meta: { cellClass: 'whitespace-normal' },
        header: () =>
            h('span', { class: tablePlainHeadClass }, 'Grant / accrual'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'max-w-[16rem] text-sm text-muted-foreground' },
                accrualSummary(row.original),
            ),
    },
    {
        id: 'paid',
        header: () => h('span', { class: tablePlainHeadClass }, 'Paid'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: 'outline',
                    class: row.original.paid
                        ? 'border-sky-500/30 bg-sky-500/10 text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/15 dark:text-sky-200'
                        : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/15 dark:text-amber-100',
                },
                () => (row.original.paid ? 'Paid' : 'Unpaid'),
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
            h(PoliciesIndexStatusColumnHeader, {
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
            h(PoliciesActionsMenu, {
                row: row.original,
                canMutate: canMutateLeavePolicies,
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
    <Head title="Leave Policies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Leave Policies
                </h1>
                <p
                    class="max-w-3xl text-sm leading-relaxed text-muted-foreground"
                >
                    Define leave types, annual entitlement, whether balance
                    accrues over time, caps and carryover, and approval rules.
                    Policies are saved to the server for your organization.
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
                                id="leave-policies-search"
                                :model-value="localSearch"
                                placeholder="Search name, code, accrual, or notes…"
                                @update:model-value="onSearchUpdate"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <Button
                        v-if="canMutateLeavePolicies"
                        type="button"
                        class="shrink-0"
                        @click="openAddDialog"
                    >
                        <Plus class="size-4" />
                        <span class="mr-1">Add Leave Policy</span>
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
                <DialogTitle>View Leave Policy</DialogTitle>
                <DialogDescription>
                    Entitlement, accrual, balances, and workflow flags for this
                    leave type.
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

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Unit
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{ viewTarget.unit === 'days' ? 'Days' : 'Hours' }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Annual entitlement
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground tabular-nums"
                        >
                            {{ entitlementSummary(viewTarget) }}
                        </p>
                    </div>

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Grant / accrual
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{ accrualSummary(viewTarget) }}
                        </p>
                    </div>

                    <template v-if="viewTarget.useAccrual">
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Accrual cadence
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                            >
                                {{
                                    viewTarget.accrualCadence === 'pay_period'
                                        ? 'Every pay period'
                                        : 'Monthly'
                                }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Per period
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground tabular-nums"
                            >
                                {{
                                    viewTarget.accrualPerPeriod != null
                                        ? `${viewTarget.accrualPerPeriod} (${viewTarget.unit === 'days' ? 'days' : 'hours'})`
                                        : '—'
                                }}
                            </p>
                        </div>
                    </template>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Max balance
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground tabular-nums"
                        >
                            {{
                                viewTarget.maxBalance != null
                                    ? `${viewTarget.maxBalance} ${viewTarget.unit === 'days' ? 'days' : 'hours'}`
                                    : '—'
                            }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Carryover
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            <template v-if="viewTarget.carryoverAllowed">
                                Allowed
                                <span
                                    v-if="viewTarget.carryoverCap != null"
                                    class="text-muted-foreground"
                                >
                                    · cap
                                    {{ viewTarget.carryoverCap }}
                                    {{ viewTarget.unit === 'days' ? 'd' : 'h' }}
                                </span>
                            </template>
                            <template v-else> Not allowed </template>
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Paid
                        </p>
                        <div
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <Badge
                                variant="outline"
                                :class="
                                    viewTarget.paid
                                        ? 'border-sky-500/30 bg-sky-500/10 text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/15 dark:text-sky-200'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/15 dark:text-amber-100'
                                "
                            >
                                {{ viewTarget.paid ? 'Paid' : 'Unpaid' }}
                            </Badge>
                        </div>
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

                    <div class="grid gap-1.5 sm:col-span-2">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Minimum tenure
                        </p>
                        <p
                            class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-foreground"
                        >
                            {{
                                viewTarget.appliesAfterMonths != null
                                    ? `${viewTarget.appliesAfterMonths} months`
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
                    isEditing ? 'Edit Leave Policy' : 'Add Leave Policy'
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
                            <Label for="lp-code">Code</Label>
                            <Input
                                id="lp-code"
                                v-model="activeDraft.code"
                                class="font-mono uppercase"
                                maxlength="16"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lp-unit">Unit</Label>
                            <Select
                                :model-value="activeDraft.unit"
                                @update:model-value="
                                    (v) =>
                                        (activeDraft.unit =
                                            v as LeavePolicyUnit)
                                "
                            >
                                <SelectTrigger id="lp-unit" class="h-9 w-full">
                                    <SelectValue placeholder="Unit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="days">Days</SelectItem>
                                    <SelectItem value="hours">Hours</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="lp-name">Policy name</Label>
                        <Input
                            id="lp-name"
                            v-model="activeDraft.name"
                            autocomplete="off"
                        />
                    </div>

                    <Separator class="bg-border/70" />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="lp-annual">Annual entitlement</Label>
                            <Input
                                id="lp-annual"
                                v-model.number="activeDraft.annualEntitlement"
                                class="tabular-nums"
                                min="0"
                                step="0.25"
                                type="number"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="lp-max">Max balance (optional)</Label>
                            <Input
                                id="lp-max"
                                :model-value="activeDraft.maxBalance ?? ''"
                                class="tabular-nums"
                                min="0"
                                step="0.25"
                                type="number"
                                placeholder="No cap"
                                @update:model-value="
                                    (v: string | number) => {
                                        const s = String(v ?? '').trim();
                                        activeDraft.maxBalance =
                                            s === '' ? null : Number(s);
                                    }
                                "
                            />
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-md border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Accrual
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    When off, employees receive the full annual
                                    grant each cycle (mock).
                                </p>
                            </div>
                            <Switch
                                :checked="activeDraft.useAccrual"
                                @update:checked="
                                    (v: boolean) =>
                                        (activeDraft.useAccrual = Boolean(v))
                                "
                            />
                        </div>

                        <template v-if="activeDraft.useAccrual">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="lp-cadence">Cadence</Label>
                                    <Select
                                        :model-value="
                                            activeDraft.accrualCadence ??
                                            'monthly'
                                        "
                                        @update:model-value="
                                            (v) =>
                                                (activeDraft.accrualCadence =
                                                    v as LeavePolicyAccrualCadence)
                                        "
                                    >
                                        <SelectTrigger id="lp-cadence">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="monthly">
                                                Monthly
                                            </SelectItem>
                                            <SelectItem value="pay_period">
                                                Every pay period
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="lp-rate"
                                        >Amount per period</Label
                                    >
                                    <Input
                                        id="lp-rate"
                                        :model-value="
                                            activeDraft.accrualPerPeriod ?? ''
                                        "
                                        class="tabular-nums"
                                        min="0"
                                        step="0.01"
                                        type="number"
                                        @update:model-value="
                                            (v: string | number) => {
                                                const s = String(
                                                    v ?? '',
                                                ).trim();
                                                activeDraft.accrualPerPeriod =
                                                    s === '' ? null : Number(s);
                                            }
                                        "
                                    />
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-md border border-border/60 bg-muted/20 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    Carryover
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Allow unused balance to roll into the next
                                    period.
                                </p>
                            </div>
                            <Switch
                                :checked="activeDraft.carryoverAllowed"
                                @update:checked="
                                    (v: boolean) =>
                                        (activeDraft.carryoverAllowed =
                                            Boolean(v))
                                "
                            />
                        </div>
                        <div
                            v-if="activeDraft.carryoverAllowed"
                            class="grid gap-2"
                        >
                            <Label for="lp-carry-cap"
                                >Carryover cap (optional)</Label
                            >
                            <Input
                                id="lp-carry-cap"
                                :model-value="activeDraft.carryoverCap ?? ''"
                                class="tabular-nums"
                                min="0"
                                step="0.25"
                                type="number"
                                placeholder="Unlimited"
                                @update:model-value="
                                    (v: string | number) => {
                                        const s = String(v ?? '').trim();
                                        activeDraft.carryoverCap =
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
                                >Paid leave</Label
                            >
                            <Switch
                                :checked="activeDraft.paid"
                                @update:checked="
                                    (v: boolean) =>
                                        (activeDraft.paid = Boolean(v))
                                "
                            />
                        </div>
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
                    </div>

                    <div class="grid gap-2">
                        <Label for="lp-tenure" :class="optionalLabelRowClass">
                            Minimum tenure (months)
                            <Badge variant="outline">Optional</Badge>
                        </Label>
                        <Input
                            id="lp-tenure"
                            :model-value="activeDraft.appliesAfterMonths ?? ''"
                            class="tabular-nums"
                            min="0"
                            step="1"
                            type="number"
                            placeholder="None"
                            @update:model-value="
                                (v) => {
                                    const s = String(v ?? '').trim();
                                    activeDraft.appliesAfterMonths =
                                        s === '' ? null : Number(s);
                                }
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

                    <div class="grid gap-2">
                        <Label for="lp-notes" :class="optionalLabelRowClass">
                            Notes
                            <Badge variant="outline">Optional</Badge>
                        </Label>
                        <Textarea
                            id="lp-notes"
                            :model-value="activeDraft.notes ?? ''"
                            rows="3"
                            class="resize-y"
                            placeholder="Eligibility, documentation, local rules…"
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
                    isEditing ? 'Save changes' : 'Add Leave Policy'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Leave Policy?</AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="deleteTarget">
                        This removes
                        <span class="font-medium text-foreground">{{
                            deleteTarget.name
                        }}</span>
                        from the mock catalog for this session only.
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
