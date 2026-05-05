<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import {
    AlertTriangle,
    CalendarDays,
    ChevronRight,
    EllipsisVertical,
    Eye,
    Loader2,
    Pencil,
    Save,
    Search,
    PlusSquare,
    Power,
    SearchX,
    Trash2,
    Users,
    UserPlus,
} from 'lucide-vue-next';
import { computed, inject, onUnmounted, ref, watch } from 'vue';
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
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    ScrollArea
} from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { appToast } from '@/lib/app-toast-client';
import type {
    OrgChartEmployeeRow,
    OrgChartNode,
} from '@/lib/build-organization-chart';
import { nodeVisual } from '@/lib/org-chart-node-visuals';
import { orgChartSheetBridgeKey } from '@/lib/org-chart-sheet-bridge';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    activeNode: OrgChartNode | null;
    chartCapabilities: {
        canManageBranch: boolean;
        canManageOrganizationNode: boolean;
    };
    chartBranchId?: number | null;
}>();

const sheetBridge = inject(orgChartSheetBridgeKey, null);
const todayIsoDate = new Date().toISOString().slice(0, 10);
const todayCalendarDate = parseDate(todayIsoDate) as DateValue;

const sheetDetailTab = ref<'units' | 'employees'>('units');

const visual = computed(() =>
    props.activeNode ? nodeVisual(props.activeNode.type) : nodeVisual('org'),
);

const fullName = computed(() => props.activeNode?.data.fullName ?? '');
const code = computed(() => props.activeNode?.data.alias ?? '');
const parentUnit = computed(() => props.activeNode?.data.parentUnit ?? null);
const directChildrenList = computed(
    () => props.activeNode?.data.directChildren ?? [],
);

const sheetEmployees = computed(() => props.activeNode?.data.employees ?? []);

const relatedUnitCodeBadgeClass =
    'h-5 shrink-0 w-fit border-foreground/50 px-1.5 font-mono text-[10px] uppercase tracking-[0.12em] dark:border-foreground/60';

/** Hover-first on fine pointers; always visible on coarse/touch; visible when row is focus-visible (keyboard). Units: compose with `ml-auto`. */
const rowAffordanceIconBaseClass =
    'size-4 shrink-0 text-muted-foreground opacity-100 transition-opacity motion-reduce:transition-none [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-visible:opacity-100';

const rowAffordanceIconClass = `ml-auto ${rowAffordanceIconBaseClass}`;

const sheetRowButtonClass =
    'nodrag nopan group flex w-full cursor-pointer items-center justify-start gap-2 rounded-md border border-border/60 bg-muted/40 px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50';

const employeeSheetRowButtonClass =
    'nodrag nopan group flex w-full cursor-pointer items-center gap-3 rounded-md border border-border/60 bg-muted/40 px-3 py-2 text-left text-sm text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring/50';

const employeeSearchQuery = ref('');
const employeeDialogOpen = ref(false);
const selectedEmployee = ref<OrgChartEmployeeRow | null>(null);
const employeeDialogMode = ref<'view' | 'edit'>('view');
const employeeEditOpenedFrom = ref<'view' | 'direct'>('view');
const employeeEditDraft = ref<{
    selectedPositionId: string;
    isPrimary: boolean;
    isHead: boolean;
    effectiveDate: string;
}>({
    selectedPositionId: '',
    isPrimary: false,
    isHead: false,
    effectiveDate: todayIsoDate,
});
const employeeDialogNotice = ref<string | null>(null);
const employeeRemoveConfirmOpen = ref(false);
const employeeEditSubmitting = ref(false);
const employeeRemoveSubmitting = ref(false);
const employeeRemoveEndDate = ref<string>(todayIsoDate);
let employeeDialogResetTimer: ReturnType<typeof setTimeout> | null = null;
const EMPLOYEE_DIALOG_RESET_DELAY_MS = 200;
const chartReloadOnlyProps = [
    'orgChart',
    'chartBranchId',
    'chartBranches',
    'chartCapabilities',
] as const;

const filteredSheetEmployees = computed((): OrgChartEmployeeRow[] => {
    const list = sheetEmployees.value;
    const q = employeeSearchQuery.value.trim().toLowerCase();
    if (q === '') {
        return list;
    }

    return list.filter((emp) => {
        if (emp.full_name.toLowerCase().includes(q)) {
            return true;
        }
        if (String(emp.employee_id).includes(q)) {
            return true;
        }
        if (emp.position_title?.toLowerCase().includes(q)) {
            return true;
        }

        return false;
    });
});

const employeeSearchHasNoMatches = computed(
    () =>
        sheetEmployees.value.length > 0 &&
        employeeSearchQuery.value.trim() !== '' &&
        filteredSheetEmployees.value.length === 0,
);

watch(open, (isOpen) => {
    if (isOpen) {
        sheetDetailTab.value = 'units';
    }
});

watch(
    () => props.activeNode?.id,
    () => {
        employeeSearchQuery.value = '';
        employeeDialogOpen.value = false;
        employeeRemoveConfirmOpen.value = false;
    },
);

watch(employeeDialogOpen, (isOpen) => {
    if (isOpen) {
        if (employeeDialogResetTimer !== null) {
            clearTimeout(employeeDialogResetTimer);
            employeeDialogResetTimer = null;
        }
        return;
    }

    employeeRemoveConfirmOpen.value = false;
    scheduleEmployeeDialogReset();
});

watch(
    [sheetEmployees, selectedEmployee],
    ([employees, selected]) => {
        if (!selected || !employeeDialogOpen.value) {
            return;
        }

        const stillPresent = employees.some(
            (employee) => employee.employee_id === selected.employee_id,
        );
        if (!stillPresent) {
            closeEmployeeDialog();
        }
    },
    { deep: false },
);

function employeeInitials(fullName: string): string {
    const parts = fullName
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

function onChildRowClick(childId: string): void {
    sheetBridge?.openNodeSheet(childId);
}

function dateValueToIsoDate(value: DateValue): string {
    return value.toDate(getLocalTimeZone()).toISOString().slice(0, 10);
}

function calendarValueFromIsoDate(value: string): DateValue | undefined {
    if (value.trim() === '') {
        return undefined;
    }

    try {
        return parseDate(value) as DateValue;
    } catch {
        return undefined;
    }
}

function isoDateDisplay(value: string): string {
    const calendarValue = calendarValueFromIsoDate(value);
    if (!calendarValue) {
        return '';
    }

    return calendarValue.toDate(getLocalTimeZone()).toLocaleDateString();
}

function onEmployeeEffectiveDateSelect(
    value: unknown,
    close: () => void,
): void {
    if (!value || Array.isArray(value) || typeof value !== 'object') {
        employeeEditDraft.value.effectiveDate = '';
        close();
        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        employeeEditDraft.value.effectiveDate = '';
        close();
        return;
    }

    employeeEditDraft.value.effectiveDate = dateValueToIsoDate(
        value as DateValue,
    );
    close();
}

function onEmployeeRemoveEndDateSelect(
    value: unknown,
    close: () => void,
): void {
    if (!value || Array.isArray(value) || typeof value !== 'object') {
        employeeRemoveEndDate.value = '';
        close();
        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        employeeRemoveEndDate.value = '';
        close();
        return;
    }

    employeeRemoveEndDate.value = dateValueToIsoDate(value as DateValue);
    close();
}

function seedEmployeeDraft(employee: OrgChartEmployeeRow): void {
    const options = employee.position_options ?? [];
    const matched = options.find(
        (option) => option.title === (employee.position_title ?? ''),
    );
    employeeEditDraft.value = {
        selectedPositionId: matched
            ? String(matched.id)
            : options[0]
              ? String(options[0].id)
              : '',
        isPrimary: Boolean(employee.is_primary),
        isHead: Boolean(employee.is_head),
        effectiveDate: new Date().toISOString().slice(0, 10),
    };
}

function scheduleEmployeeDialogReset(): void {
    if (employeeDialogResetTimer !== null) {
        clearTimeout(employeeDialogResetTimer);
    }

    employeeDialogResetTimer = setTimeout(() => {
        selectedEmployee.value = null;
        employeeDialogMode.value = 'view';
        employeeEditDraft.value = {
            selectedPositionId: '',
            isPrimary: false,
            isHead: false,
            effectiveDate: todayIsoDate,
        };
        employeeDialogNotice.value = null;
        employeeRemoveConfirmOpen.value = false;
        employeeRemoveEndDate.value = todayIsoDate;
        employeeDialogResetTimer = null;
    }, EMPLOYEE_DIALOG_RESET_DELAY_MS);
}

function openEmployeeDialog(employee: OrgChartEmployeeRow): void {
    if (employeeDialogResetTimer !== null) {
        clearTimeout(employeeDialogResetTimer);
        employeeDialogResetTimer = null;
    }

    selectedEmployee.value = employee;
    employeeDialogMode.value = 'view';
    employeeEditOpenedFrom.value = 'view';
    employeeDialogNotice.value = null;
    employeeRemoveEndDate.value = todayIsoDate;
    employeeRemoveConfirmOpen.value = false;
    seedEmployeeDraft(employee);
    employeeDialogOpen.value = true;
}

function openEmployeeDialogForEdit(employee: OrgChartEmployeeRow): void {
    openEmployeeDialog(employee);
    enterEmployeeEditMode('direct');
}

function openEmployeeDialogForRemove(employee: OrgChartEmployeeRow): void {
    if (employeeDialogResetTimer !== null) {
        clearTimeout(employeeDialogResetTimer);
        employeeDialogResetTimer = null;
    }

    selectedEmployee.value = employee;
    employeeDialogMode.value = 'view';
    employeeDialogNotice.value = null;
    employeeRemoveEndDate.value = todayIsoDate;
    employeeDialogOpen.value = false;
    employeeRemoveConfirmOpen.value = true;
}

function closeEmployeeDialog(): void {
    employeeDialogOpen.value = false;
}

function enterEmployeeEditMode(from: 'view' | 'direct' = 'view'): void {
    if (!selectedEmployee.value) {
        return;
    }
    seedEmployeeDraft(selectedEmployee.value);
    employeeEditOpenedFrom.value = from;
    employeeDialogMode.value = 'edit';
    employeeDialogNotice.value = null;
}

function cancelEmployeeEditMode(): void {
    employeeDialogNotice.value = null;
    if (employeeEditOpenedFrom.value === 'direct') {
        closeEmployeeDialog();
        return;
    }

    employeeDialogMode.value = 'view';
}

async function saveEmployeeEditDraft(): Promise<void> {
    if (employeeEditSubmitting.value || employeeRemoveSubmitting.value) {
        return;
    }

    const options = selectedEmployee.value?.position_options ?? [];
    if (
        options.length > 0 &&
        employeeEditDraft.value.selectedPositionId === ''
    ) {
        employeeDialogNotice.value =
            'Please select a position for this unit assignment.';
        return;
    }
    if (employeeEditDraft.value.effectiveDate.trim() === '') {
        employeeDialogNotice.value = 'Please select an effective date.';
        return;
    }

    const assignmentId = selectedEmployee.value?.assignment_id;
    const chartBranchId = props.chartBranchId ?? null;
    const nodeId = props.activeNode?.id ?? null;
    const positionId = Number.parseInt(
        employeeEditDraft.value.selectedPositionId,
        10,
    );

    if (
        !assignmentId ||
        !chartBranchId ||
        !nodeId ||
        !Number.isFinite(positionId) ||
        positionId <= 0
    ) {
        employeeDialogNotice.value =
            'Unable to save this assignment right now.';
        return;
    }

    employeeEditSubmitting.value = true;
    employeeDialogNotice.value = null;

    try {
        const csrfToken = resolveCsrfToken();
        const response = await fetch(
            `/organization-chart/employees/${assignmentId}`,
            {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    chart_branch_id: chartBranchId,
                    node_id: nodeId,
                    position_id: positionId,
                    effective_date: employeeEditDraft.value.effectiveDate,
                    is_primary: employeeEditDraft.value.isPrimary,
                    is_head: employeeEditDraft.value.isHead,
                }),
            },
        );

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            employeeDialogNotice.value =
                firstErrorMessage(payload) ??
                'Please review the employee assignment details and try again.';
            return;
        }

        if (!response.ok) {
            const message =
                response.status === 403
                    ? 'You are not allowed to edit this assignment.'
                    : 'Unable to save this assignment right now.';
            employeeDialogNotice.value = message;
            appToast.error(message);
            return;
        }

        employeeDialogMode.value = 'view';
        closeEmployeeDialog();
        refreshOrganizationChart();
        appToast.success('Employee assignment updated', {
            description:
                selectedEmployee.value?.full_name ??
                'The employee details were saved successfully.',
        });
    } catch {
        employeeDialogNotice.value =
            'Unable to save this assignment right now.';
        appToast.error('Unable to save this assignment right now.');
    } finally {
        employeeEditSubmitting.value = false;
    }
}

function openEmployeeRemoveConfirm(): void {
    employeeDialogNotice.value = null;
    employeeRemoveEndDate.value = todayIsoDate;
    employeeRemoveConfirmOpen.value = true;
}

function closeEmployeeRemoveConfirm(): void {
    if (employeeRemoveSubmitting.value) {
        return;
    }

    employeeRemoveConfirmOpen.value = false;
}

function firstErrorMessage(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const errors = 'errors' in payload ? payload.errors : null;
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value)) {
            const first = value.find(
                (entry) => typeof entry === 'string' && entry.trim() !== '',
            );
            if (typeof first === 'string') {
                return first;
            }
        }

        if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return null;
}

function resolveCsrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

async function parseJsonPayload<T>(response: Response): Promise<T | null> {
    const contentType = response.headers.get('content-type') ?? '';
    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return (await response.json()) as T;
    } catch {
        return null;
    }
}

function refreshOrganizationChart(): void {
    const loadingToastId = appToast.loading('Updating chart…');
    router.reload({
        only: [...chartReloadOnlyProps],
        showProgress: false,
        onFinish: () => {
            appToast.dismiss(loadingToastId);
        },
        onError: () => {
            appToast.error('Could not refresh the chart.');
        },
    });
}

async function confirmEmployeeRemove(): Promise<void> {
    const assignmentId = selectedEmployee.value?.assignment_id;
    const chartBranchId = props.chartBranchId ?? null;
    const nodeId = props.activeNode?.id ?? null;

    if (!assignmentId || !chartBranchId || !nodeId) {
        employeeRemoveConfirmOpen.value = false;
        employeeDialogNotice.value =
            'Unable to remove this assignment right now.';
        return;
    }
    if (employeeRemoveEndDate.value.trim() === '') {
        employeeDialogNotice.value = 'Please select an end date.';
        return;
    }

    if (employeeRemoveSubmitting.value || employeeEditSubmitting.value) {
        return;
    }

    employeeRemoveSubmitting.value = true;
    employeeDialogNotice.value = null;

    try {
        const csrfToken = resolveCsrfToken();
        const response = await fetch(
            `/organization-chart/employees/${assignmentId}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    chart_branch_id: chartBranchId,
                    node_id: nodeId,
                    end_date: employeeRemoveEndDate.value,
                }),
            },
        );

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            employeeDialogNotice.value =
                firstErrorMessage(payload) ??
                'Please confirm the removal details and try again.';
            return;
        }

        if (!response.ok) {
            const message =
                response.status === 403
                    ? 'You are not allowed to remove this assignment.'
                    : 'Unable to remove this assignment right now.';
            employeeDialogNotice.value = message;
            appToast.error(message);
            return;
        }

        employeeRemoveConfirmOpen.value = false;
        closeEmployeeDialog();
        refreshOrganizationChart();
        appToast.success('Employee removed from unit', {
            description:
                selectedEmployee.value?.full_name ??
                'The assignment was removed successfully.',
        });
    } catch {
        employeeDialogNotice.value =
            'Unable to remove this assignment right now.';
        appToast.error('Unable to remove this assignment right now.');
    } finally {
        employeeRemoveSubmitting.value = false;
    }
}

function requestSheetEditUnit(): void {
    const id = props.activeNode?.id;
    if (!id || !sheetBridge) {
        return;
    }

    sheetBridge.requestNodeAction(id, 'editUnit');
}

function requestSheetAddUnit(): void {
    const id = props.activeNode?.id;
    if (!id || !sheetBridge) {
        return;
    }

    sheetBridge.requestNodeAction(id, 'addUnit');
}

function requestSheetAddEmployee(): void {
    const id = props.activeNode?.id;
    if (!id || !sheetBridge) {
        return;
    }

    sheetBridge.requestNodeAction(id, 'addEmployee');
}

const sheetActionsDisabled = computed(() => !sheetBridge);
const canManageActiveNode = computed(() => {
    if (!props.chartCapabilities.canManageBranch) {
        return false;
    }

    if (props.activeNode?.id.startsWith('organization-') ?? false) {
        return props.chartCapabilities.canManageOrganizationNode;
    }

    return true;
});
const actionButtonsDisabled = computed(
    () => sheetActionsDisabled.value || !canManageActiveNode.value,
);
const employeeMutationPending = computed(
    () => employeeEditSubmitting.value || employeeRemoveSubmitting.value,
);
const sheetUnitStatusDialogOpen = ref(false);
const sheetUnitDeleteDialogOpen = ref(false);
const sheetUnitActionSubmitting = ref(false);
const activeNodeIsUnit = computed(
    () => props.activeNode?.id.startsWith('unit-') ?? false,
);
const activeNodeIsActive = computed(
    () => props.activeNode?.data.isActive ?? true,
);
const sheetUnitStatusActionLabel = computed(() =>
    activeNodeIsActive.value ? 'Deactivate Unit' : 'Activate Unit',
);

function openSheetUnitStatusDialog(): void {
    if (!canManageActiveNode.value || !activeNodeIsUnit.value) {
        return;
    }

    sheetUnitStatusDialogOpen.value = true;
}

function openSheetUnitDeleteDialog(): void {
    if (!canManageActiveNode.value || !activeNodeIsUnit.value) {
        return;
    }

    sheetUnitDeleteDialogOpen.value = true;
}

async function confirmSheetUnitStatusAction(): Promise<void> {
    const nodeId = props.activeNode?.id ?? null;
    const chartBranchId = props.chartBranchId ?? null;
    if (!nodeId?.startsWith('unit-') || !chartBranchId) {
        appToast.error('Unable to update unit status right now.');
        return;
    }

    const unitId = Number.parseInt(nodeId.slice('unit-'.length), 10);
    if (!Number.isFinite(unitId) || unitId <= 0) {
        appToast.error('Unable to update unit status right now.');
        return;
    }

    sheetUnitActionSubmitting.value = true;
    const activating = !activeNodeIsActive.value;
    const endpoint = activating
        ? `/organization-chart/units/${unitId}/activate`
        : `/organization-chart/units/${unitId}/deactivate`;

    try {
        const csrfToken = resolveCsrfToken();
        const response = await fetch(endpoint, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                chart_branch_id: chartBranchId,
                node_id: nodeId,
            }),
        });

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            appToast.error(
                firstErrorMessage(payload) ??
                    'Unable to update unit status right now.',
            );
            return;
        }

        if (!response.ok) {
            appToast.error(
                response.status === 403
                    ? 'You are not allowed to manage this unit.'
                    : 'Unable to update unit status right now.',
            );
            return;
        }

        sheetUnitStatusDialogOpen.value = false;
        refreshOrganizationChart();
        appToast.success(activating ? 'Unit activated.' : 'Unit deactivated.');
    } catch {
        appToast.error('Unable to update unit status right now.');
    } finally {
        sheetUnitActionSubmitting.value = false;
    }
}

async function confirmSheetUnitDeleteAction(): Promise<void> {
    const nodeId = props.activeNode?.id ?? null;
    const chartBranchId = props.chartBranchId ?? null;
    if (!nodeId?.startsWith('unit-') || !chartBranchId) {
        appToast.error('Unable to delete this unit right now.');
        return;
    }

    const unitId = Number.parseInt(nodeId.slice('unit-'.length), 10);
    if (!Number.isFinite(unitId) || unitId <= 0) {
        appToast.error('Unable to delete this unit right now.');
        return;
    }

    sheetUnitActionSubmitting.value = true;

    try {
        const csrfToken = resolveCsrfToken();
        const response = await fetch(`/organization-chart/units/${unitId}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                chart_branch_id: chartBranchId,
                node_id: nodeId,
            }),
        });

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            appToast.error(
                firstErrorMessage(payload) ??
                    'Unable to delete this unit right now.',
            );
            return;
        }

        if (!response.ok) {
            appToast.error(
                response.status === 403
                    ? 'You are not allowed to delete this unit.'
                    : 'Unable to delete this unit right now.',
            );
            return;
        }

        sheetUnitDeleteDialogOpen.value = false;
        refreshOrganizationChart();
        appToast.success('Unit deletion applied.');
    } catch {
        appToast.error('Unable to delete this unit right now.');
    } finally {
        sheetUnitActionSubmitting.value = false;
    }
}

onUnmounted(() => {
    if (employeeDialogResetTimer !== null) {
        clearTimeout(employeeDialogResetTimer);
    }
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex max-h-dvh flex-col sm:max-w-md">
            <template v-if="activeNode">
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div
                        class="flex min-h-0 flex-1 flex-col gap-0 overflow-y-auto px-4 pb-4 text-sm text-muted-foreground"
                    >
                        <SheetHeader class="space-y-0 px-0 pt-4 pb-0 text-left">
                            <SheetTitle
                                class="flex min-w-0 items-center justify-start gap-2 pr-8 text-base leading-snug font-semibold"
                            >
                                <span
                                    :class="[
                                        'min-w-0 shrink truncate',
                                        visual.nameClass,
                                    ]"
                                    :title="fullName"
                                >
                                    {{ fullName }}
                                </span>
                                <Badge
                                    v-if="code"
                                    variant="outline"
                                    :class="[
                                        'h-5 w-fit shrink-0 border-foreground/50 px-1.5 font-mono text-[10px] tracking-[0.12em] uppercase dark:border-foreground/60',
                                        visual.codeClass,
                                    ]"
                                >
                                    {{ code }}
                                </Badge>
                            </SheetTitle>
                            <SheetDescription class="sr-only">
                                View the selected organization chart unit
                                details, employees, and available management
                                actions.
                            </SheetDescription>
                            <div class="mt-3 space-y-1">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Unit type
                                </p>
                                <p
                                    class="text-xs font-semibold tracking-wide text-foreground/90 uppercase"
                                >
                                    {{ visual.unitLabel }}
                                </p>
                            </div>
                        </SheetHeader>
                        <Tabs
                            v-model="sheetDetailTab"
                            class="mt-2 flex min-h-0 flex-1 flex-col gap-3"
                        >
                            <TabsList class="grid w-full shrink-0 grid-cols-2">
                                <TabsTrigger value="units"> Units </TabsTrigger>
                                <TabsTrigger value="employees">
                                    Employees
                                </TabsTrigger>
                            </TabsList>
                            <TabsContent
                                value="units"
                                class="flex min-h-0 flex-1 flex-col overflow-hidden focus-visible:outline-none data-[state=inactive]:hidden"
                            >
                                <div class="flex min-h-0 flex-1 flex-col">
                                    <div v-if="parentUnit" class="mt-2">
                                        <p
                                            class="text-xs font-semibold tracking-wide text-foreground/80 uppercase"
                                        >
                                            Parent unit
                                        </p>
                                        <button
                                            type="button"
                                            :class="[
                                                sheetRowButtonClass,
                                                'mt-2',
                                            ]"
                                            @click.stop="
                                                onChildRowClick(parentUnit.id)
                                            "
                                        >
                                            <span
                                                class="min-w-0 shrink truncate leading-snug font-medium"
                                                :title="parentUnit.fullName"
                                            >
                                                {{ parentUnit.fullName }}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                :class="
                                                    relatedUnitCodeBadgeClass
                                                "
                                            >
                                                {{ parentUnit.alias }}
                                            </Badge>
                                            <ChevronRight
                                                :class="rowAffordanceIconClass"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>

                                    <div
                                        v-if="directChildrenList.length > 0"
                                        class="flex min-h-0 flex-1 flex-col"
                                        :class="
                                            parentUnit
                                                ? 'mt-6 border-t border-border/60 pt-4'
                                                : 'mt-2'
                                        "
                                    >
                                        <p
                                            class="text-xs font-semibold tracking-wide text-foreground/80 uppercase"
                                        >
                                            Direct units
                                        </p>
                                        <div
                                            class="mt-2 max-h-[40vh] min-h-0 flex-1 space-y-1 overflow-y-auto"
                                        >
                                            <button
                                                v-for="child in directChildrenList"
                                                :key="child.id"
                                                type="button"
                                                :class="sheetRowButtonClass"
                                                @click.stop="
                                                    onChildRowClick(child.id)
                                                "
                                            >
                                                <span
                                                    class="min-w-0 shrink truncate leading-snug font-medium"
                                                    :title="child.fullName"
                                                >
                                                    {{ child.fullName }}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    :class="
                                                        relatedUnitCodeBadgeClass
                                                    "
                                                >
                                                    {{ child.alias }}
                                                </Badge>
                                                <ChevronRight
                                                    :class="
                                                        rowAffordanceIconClass
                                                    "
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="!parentUnit"
                                        class="mt-2 rounded-md border border-dashed border-border/70 bg-muted/30 p-4"
                                    >
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            No related units yet
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Add a unit to build this part of the
                                            organization structure.
                                        </p>
                                    </div>
                                </div>
                            </TabsContent>
                            <TabsContent
                                value="employees"
                                class="flex min-h-0 flex-1 flex-col overflow-hidden focus-visible:outline-none data-[state=inactive]:hidden"
                            >
                                <div
                                    v-if="sheetEmployees.length > 0"
                                    class="mt-2 flex min-h-0 flex-1 flex-col gap-2 px-1"
                                >
                                    <div class="relative shrink-0">
                                        <Search
                                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <Input
                                            id="org-chart-sheet-employee-search"
                                            v-model="employeeSearchQuery"
                                            type="search"
                                            class="w-full pl-9"
                                            placeholder="Search by name or position…"
                                            aria-label="Search employees"
                                        />
                                    </div>
                                    <ScrollArea class="min-h-0 flex-1">
                                        <div class="space-y-1">
                                        <button
                                            v-for="emp in filteredSheetEmployees"
                                            :key="`${emp.employee_id}-${emp.full_name}`"
                                            type="button"
                                            :class="employeeSheetRowButtonClass"
                                            :title="`Open ${emp.full_name} details`"
                                            :aria-label="`Open ${emp.full_name} details`"
                                            @click.stop="
                                                openEmployeeDialog(emp)
                                            "
                                        >
                                            <Avatar
                                                class="size-9 shrink-0 border border-border/70 bg-muted/30"
                                            >
                                                <AvatarImage
                                                    :src="emp.avatar_url ?? ''"
                                                    :alt="emp.full_name"
                                                />
                                                <AvatarFallback
                                                    class="text-[11px] font-medium text-muted-foreground"
                                                >
                                                    {{
                                                        employeeInitials(
                                                            emp.full_name,
                                                        )
                                                    }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex w-full min-w-0 items-center gap-2"
                                                >
                                                    <span
                                                        class="min-w-0 shrink truncate text-sm font-medium text-foreground"
                                                        :title="emp.full_name"
                                                    >
                                                        {{ emp.full_name }}
                                                    </span>
                                                    <Badge
                                                        v-if="emp.is_head"
                                                        variant="outline"
                                                        class="h-5 shrink-0 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                                                    >
                                                        Head
                                                    </Badge>
                                                </div>
                                                <p
                                                    v-if="emp.position_title"
                                                    class="mt-0.5 text-xs text-muted-foreground"
                                                >
                                                    {{ emp.position_title }}
                                                </p>
                                                <p
                                                    v-else
                                                    class="mt-0.5 text-xs text-muted-foreground/80 italic"
                                                >
                                                    No position linked
                                                </p>
                                            </div>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger
                                                    :as-child="true"
                                                >
                                                    <button
                                                        type="button"
                                                        class="nodrag nopan inline-flex size-7 shrink-0 items-center justify-center self-center rounded-sm text-muted-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset"
                                                        :aria-label="`Open actions for ${emp.full_name}`"
                                                        @click.stop
                                                        @pointerdown.stop
                                                    >
                                                        <EllipsisVertical
                                                            class="size-4"
                                                        />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                    class="min-w-44"
                                                >
                                                    <DropdownMenuItem
                                                        class="nodrag nopan"
                                                        @select="
                                                            openEmployeeDialog(
                                                                emp,
                                                            )
                                                        "
                                                    >
                                                        <Eye class="size-4" />
                                                        View Details
                                                    </DropdownMenuItem>
                                                    <template
                                                        v-if="
                                                            canManageActiveNode
                                                        "
                                                    >
                                                        <DropdownMenuSeparator
                                                            class="bg-border/80"
                                                        />
                                                        <DropdownMenuItem
                                                            class="nodrag nopan"
                                                            @select="
                                                                openEmployeeDialogForEdit(
                                                                    emp,
                                                                )
                                                            "
                                                        >
                                                            <Pencil
                                                                class="size-4"
                                                            />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="nodrag nopan text-destructive focus:text-destructive"
                                                            @select="
                                                                openEmployeeDialogForRemove(
                                                                    emp,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="size-4"
                                                            />
                                                            Remove
                                                        </DropdownMenuItem>
                                                    </template>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </button>
                                        <div
                                            v-if="employeeSearchHasNoMatches"
                                            class="rounded-md border border-dashed border-border/70 bg-muted/30 p-4"
                                        >
                                            <div class="flex items-start gap-3">
                                                <SearchX
                                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                />
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-sm font-medium text-foreground"
                                                    >
                                                        No matching employees
                                                    </p>
                                                    <p
                                                        class="mt-1 text-xs text-muted-foreground"
                                                    >
                                                        Try another name, ID, or
                                                        position.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </ScrollArea>
                                </div>
                                <div
                                    v-else
                                    class="mt-2 rounded-md border border-dashed border-border/70 bg-muted/30 p-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <Users
                                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                No employees assigned
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Employees assigned to this unit
                                                will appear here.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>

                    <div
                        v-if="canManageActiveNode"
                        class="shrink-0 border-t border-border/60 bg-background px-4 py-4"
                    >
                        <div
                            class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:items-stretch"
                        >
                            <DropdownMenu>
                                <DropdownMenuTrigger :as-child="true">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full min-w-0 sm:hidden"
                                        :disabled="actionButtonsDisabled"
                                    >
                                        <EllipsisVertical
                                            class="size-4 shrink-0"
                                        />
                                        <span class="truncate">Actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    class="min-w-52"
                                >
                                    <DropdownMenuItem
                                        class="nodrag nopan"
                                        @select="requestSheetEditUnit"
                                    >
                                        <Pencil class="size-4" />
                                        Edit Unit
                                    </DropdownMenuItem>
                                    <template v-if="activeNodeIsUnit">
                                        <DropdownMenuSeparator
                                            class="bg-border/80"
                                        />
                                        <DropdownMenuItem
                                            class="nodrag nopan"
                                            @select="openSheetUnitStatusDialog"
                                        >
                                            <Power class="size-4" />
                                            {{ sheetUnitStatusActionLabel }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="nodrag nopan text-destructive focus:text-destructive"
                                            @select="openSheetUnitDeleteDialog"
                                        >
                                            <Trash2 class="size-4" />
                                            Delete Unit
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <DropdownMenu>
                                <DropdownMenuTrigger :as-child="true">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="hidden shrink-0 sm:inline-flex"
                                        :disabled="actionButtonsDisabled"
                                    >
                                        <EllipsisVertical
                                            class="size-4 shrink-0"
                                        />
                                        <span class="sr-only"
                                            >Unit actions</span
                                        >
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    class="min-w-52"
                                >
                                    <DropdownMenuItem
                                        class="nodrag nopan"
                                        @select="requestSheetEditUnit"
                                    >
                                        <Pencil class="size-4" />
                                        Edit Unit
                                    </DropdownMenuItem>
                                    <template v-if="activeNodeIsUnit">
                                        <DropdownMenuSeparator
                                            class="bg-border/80"
                                        />
                                        <DropdownMenuItem
                                            class="nodrag nopan"
                                            @select="openSheetUnitStatusDialog"
                                        >
                                            <Power class="size-4" />
                                            {{ sheetUnitStatusActionLabel }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            class="nodrag nopan text-destructive focus:text-destructive"
                                            @select="openSheetUnitDeleteDialog"
                                        >
                                            <Trash2 class="size-4" />
                                            Delete Unit
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button
                                type="button"
                                variant="outline"
                                class="w-full min-w-0 sm:flex-1"
                                :disabled="actionButtonsDisabled"
                                @click="requestSheetAddUnit"
                            >
                                <PlusSquare class="size-4 shrink-0" />
                                <span class="truncate">Add Unit</span>
                            </Button>
                            <Button
                                type="button"
                                class="w-full min-w-0 sm:flex-1"
                                :disabled="actionButtonsDisabled"
                                @click="requestSheetAddEmployee"
                            >
                                <UserPlus class="size-4 shrink-0" />
                                <span class="truncate">Add Employee</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="sheetUnitStatusDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ sheetUnitStatusActionLabel }}</DialogTitle>
                <DialogDescription>
                    This is a frontend-only placeholder for now. Backend unit
                    lifecycle rules will be wired next.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="sheetUnitActionSubmitting"
                    @click="sheetUnitStatusDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="sheetUnitActionSubmitting"
                    @click="confirmSheetUnitStatusAction"
                >
                    {{ sheetUnitStatusActionLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="sheetUnitDeleteDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete Unit</DialogTitle>
                <DialogDescription>
                    This is a frontend-only placeholder for now. Delete
                    constraints and backend validation will be wired next.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="sheetUnitActionSubmitting"
                    @click="sheetUnitDeleteDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="sheetUnitActionSubmitting"
                    @click="confirmSheetUnitDeleteAction"
                >
                    Delete Unit
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="employeeDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Employee Details</DialogTitle>
                <DialogDescription>
                    Review assignment details for this unit.
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedEmployee" class="grid gap-4 py-2">
                <div class="flex items-center gap-3">
                    <Avatar
                        class="size-11 shrink-0 border border-border/70 bg-muted/30"
                    >
                        <AvatarImage
                            :src="selectedEmployee.avatar_url ?? ''"
                            :alt="selectedEmployee.full_name"
                        />
                        <AvatarFallback
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{ employeeInitials(selectedEmployee.full_name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-semibold text-foreground"
                            :title="selectedEmployee.full_name"
                        >
                            {{ selectedEmployee.full_name }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Employee ID: {{ selectedEmployee.employee_id }}
                        </p>
                    </div>
                    <Badge
                        v-if="selectedEmployee.is_head"
                        variant="outline"
                        class="h-5 shrink-0 border-foreground/50 px-1.5 text-[10px] dark:border-foreground/60"
                    >
                        Head
                    </Badge>
                </div>

                <div
                    v-if="employeeDialogMode === 'view'"
                    class="rounded-md border border-border/60 bg-muted/30 p-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Assignment in this unit
                    </p>
                    <p
                        v-if="selectedEmployee.position_title"
                        class="mt-2 text-sm text-foreground"
                    >
                        {{ selectedEmployee.position_title }}
                    </p>
                    <p v-else class="mt-2 text-sm text-muted-foreground italic">
                        No position linked
                    </p>
                </div>

                <div v-else class="grid gap-2">
                    <Label for="sheet-employee-position-select"
                        >Position for this unit</Label
                    >
                    <Select
                        v-model="employeeEditDraft.selectedPositionId"
                        :disabled="employeeMutationPending"
                    >
                        <SelectTrigger
                            id="sheet-employee-position-select"
                            class="w-full"
                        >
                            <SelectValue
                                :placeholder="
                                    (selectedEmployee.position_options
                                        ?.length ?? 0) > 0
                                        ? 'Select one position'
                                        : 'No active positions available'
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="position in selectedEmployee.position_options ??
                                []"
                                :key="position.id"
                                :value="String(position.id)"
                            >
                                {{ position.title }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="
                            (selectedEmployee.position_options?.length ?? 0) ===
                            0
                        "
                        class="text-xs text-muted-foreground"
                    >
                        This employee has no active positions available for
                        assignment.
                    </p>
                    <div
                        class="mt-1 flex items-center justify-between rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                    >
                        <p class="text-sm text-foreground">
                            Primary assignment designation
                        </p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                {{
                                    employeeEditDraft.isPrimary
                                        ? 'Primary'
                                        : 'Not primary'
                                }}
                            </span>
                            <Switch
                                id="sheet-employee-is-primary"
                                v-model="employeeEditDraft.isPrimary"
                                :disabled="employeeMutationPending"
                            />
                        </div>
                    </div>
                    <div
                        class="mt-1 flex items-center justify-between rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                    >
                        <p class="text-sm text-foreground">
                            Unit head designation
                        </p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                {{
                                    employeeEditDraft.isHead ? 'Head' : 'Member'
                                }}
                            </span>
                            <Switch
                                id="sheet-employee-is-head"
                                v-model="employeeEditDraft.isHead"
                                :disabled="employeeMutationPending"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sheet-employee-effective-date"
                            >Effective Date</Label
                        >
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button
                                    id="sheet-employee-effective-date"
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-between gap-2 font-normal"
                                    :disabled="employeeMutationPending"
                                >
                                    <span
                                        v-if="
                                            isoDateDisplay(
                                                employeeEditDraft.effectiveDate,
                                            )
                                        "
                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                    >
                                        {{
                                            isoDateDisplay(
                                                employeeEditDraft.effectiveDate,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="flex-1 text-left text-muted-foreground"
                                    >
                                        Select effective date
                                    </span>
                                    <CalendarDays
                                        class="size-4 shrink-0 opacity-50"
                                    />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                class="w-auto overflow-hidden p-0"
                                align="start"
                            >
                                <Calendar
                                    layout="month-and-year"
                                    :model-value="
                                        calendarValueFromIsoDate(
                                            employeeEditDraft.effectiveDate,
                                        )
                                    "
                                    @update:model-value="
                                        (value) =>
                                            onEmployeeEffectiveDateSelect(
                                                value,
                                                close,
                                            )
                                    "
                                />
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                <p
                    v-if="employeeDialogNotice"
                    class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                >
                    {{ employeeDialogNotice }}
                </p>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <template v-if="employeeDialogMode === 'edit'">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="employeeMutationPending"
                        @click="cancelEmployeeEditMode"
                    >
                        Cancel Edit
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            employeeMutationPending ||
                            (selectedEmployee?.position_options?.length ??
                                0) === 0
                        "
                        @click="saveEmployeeEditDraft"
                    >
                        <Loader2
                            v-if="employeeEditSubmitting"
                            class="size-4 shrink-0 animate-spin"
                        />
                        <Save v-else class="size-4 shrink-0" />
                        {{ employeeEditSubmitting ? 'Saving...' : 'Save' }}
                    </Button>
                </template>
                <template v-else>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="employeeMutationPending"
                        @click="closeEmployeeDialog"
                    >
                        Close
                    </Button>
                    <template v-if="canManageActiveNode">
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="employeeMutationPending"
                            @click="openEmployeeRemoveConfirm"
                        >
                            <Trash2 class="size-4 shrink-0" />
                            Remove
                        </Button>
                        <Button
                            type="button"
                            :disabled="employeeMutationPending"
                            @click="enterEmployeeEditMode('view')"
                        >
                            <Pencil class="size-4 shrink-0" />
                            Edit
                        </Button>
                    </template>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="employeeRemoveConfirmOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle class="flex items-center gap-2">
                    <AlertTriangle class="size-4 shrink-0 text-destructive" />
                    Remove Employee Assignment?
                </AlertDialogTitle>
                <AlertDialogDescription>
                    <template v-if="selectedEmployee && activeNode">
                        This will remove
                        <span class="font-medium text-foreground">{{
                            selectedEmployee.full_name
                        }}</span>
                        from
                        <span class="font-medium text-foreground">{{
                            activeNode.data.fullName
                        }}</span
                        >. Their assignment to this unit will be retired and
                        removed from the chart.
                    </template>
                    <template v-else>
                        Confirm removal of this employee assignment.
                    </template>
                </AlertDialogDescription>
                <div class="grid gap-2">
                    <Label for="sheet-employee-remove-end-date">End Date</Label>
                    <Popover v-slot="{ close }">
                        <PopoverTrigger as-child>
                            <Button
                                id="sheet-employee-remove-end-date"
                                type="button"
                                variant="outline"
                                class="w-full justify-between gap-2 font-normal"
                                :disabled="employeeMutationPending"
                            >
                                <span
                                    v-if="isoDateDisplay(employeeRemoveEndDate)"
                                    class="min-w-0 flex-1 truncate text-left text-foreground"
                                >
                                    {{ isoDateDisplay(employeeRemoveEndDate) }}
                                </span>
                                <span
                                    v-else
                                    class="flex-1 text-left text-muted-foreground"
                                >
                                    Select end date
                                </span>
                                <CalendarDays
                                    class="size-4 shrink-0 opacity-50"
                                />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            class="w-auto overflow-hidden p-0"
                            align="start"
                        >
                            <Calendar
                                layout="month-and-year"
                                :min-value="todayCalendarDate"
                                :model-value="
                                    calendarValueFromIsoDate(
                                        employeeRemoveEndDate,
                                    )
                                "
                                @update:model-value="
                                    (value) =>
                                        onEmployeeRemoveEndDateSelect(
                                            value,
                                            close,
                                        )
                                "
                            />
                        </PopoverContent>
                    </Popover>
                </div>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel
                    :disabled="employeeMutationPending"
                    @click="closeEmployeeRemoveConfirm"
                >
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="employeeMutationPending"
                    @click.prevent="confirmEmployeeRemove"
                >
                    <Loader2
                        v-if="employeeRemoveSubmitting"
                        class="size-4 shrink-0 animate-spin"
                    />
                    <span>{{
                        employeeRemoveSubmitting
                            ? 'Removing...'
                            : 'Confirm Remove'
                    }}</span>
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
