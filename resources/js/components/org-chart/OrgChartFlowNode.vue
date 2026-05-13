<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { calendarDateValueToIsoYmd } from '@/lib/calendarDateValueToIsoYmd';
import type { DateValue } from '@internationalized/date';
import { Handle, Position } from '@vue-flow/core';
import {
    CalendarDays,
    Eye,
    Loader2,
    Pencil,
    PlusSquare,
    Power,
    Trash2,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, inject, onUnmounted, ref, watch } from 'vue';
import OrgChartEmployeeAddCombobox from '@/components/org-chart/OrgChartEmployeeAddCombobox.vue';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { appToast } from '@/lib/app-toast-client';
import type { OrgChartDirectChild } from '@/lib/build-organization-chart';
import type { NodeVisualStyle } from '@/lib/org-chart-node-visuals';
import { orgChartSheetBridgeKey } from '@/lib/org-chart-sheet-bridge';
import type { OrgChartAssignableEmployeeHit } from '@/lib/orgChartEmployeeSearchApi';

const props = defineProps<{
    nodeId: string;
    visual: NodeVisualStyle;
    fullName: string;
    code: string;
    isActive?: boolean;
    unitTypeColor?: string | null;
    directChildren?: OrgChartDirectChild[];
    parentUnit?: OrgChartDirectChild | null;
    nodeBucket?: 'org' | 'branch' | 'department' | 'section';
    chartBranchId?: number | null;
    chartAreas?: Array<{ id: number; code: string; name: string }>;
    chartCapabilities: {
        canManageBranch: boolean;
        canManageOrganizationNode: boolean;
    };
}>();

const sheetBridge = inject(orgChartSheetBridgeKey, null);

function normalizeHexColor(value: string | null | undefined): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(normalized) ? normalized : null;
}

const normalizedUnitTypeColor = computed(() =>
    normalizeHexColor(props.unitTypeColor),
);
const isUnitNode = computed(() => props.nodeId.startsWith('unit-'));
const unitBorderStyle = computed<Record<string, string> | undefined>(() => {
    if (!isUnitNode.value || normalizedUnitTypeColor.value === null) {
        return undefined;
    }

    return {
        borderColor: normalizedUnitTypeColor.value,
    };
});

function openThisNodeSheet(): void {
    sheetBridge?.openNodeSheet(props.nodeId);
}

function onViewDetails() {
    openThisNodeSheet();
}

const addUnitDialogOpen = ref(false);
const newUnitName = ref('');
const newUnitAlias = ref('');
const selectedAreaId = ref<string>('');
const selectedChildUnitTypeName = ref<string | null>(null);
const addUnitSubmitting = ref(false);
const addUnitError = ref<string | null>(null);

type UnitCodeAvailabilityStatus =
    | 'idle'
    | 'checking'
    | 'available'
    | 'taken'
    | 'invalid';
const unitCodeAvailability = ref<{
    status: UnitCodeAvailabilityStatus;
    message: string;
}>({
    status: 'idle',
    message: '',
});
let unitCodeCheckTimer: ReturnType<typeof setTimeout> | null = null;
let unitCodeAbortController: AbortController | null = null;
let addUnitCloseResetTimer: ReturnType<typeof setTimeout> | null = null;

const editUnitDialogOpen = ref(false);
const editUnitName = ref('');
const editUnitCode = ref('');
const editUnitSubmitting = ref(false);
const editUnitError = ref<string | null>(null);
const editUnitCodeAvailability = ref<{
    status: UnitCodeAvailabilityStatus;
    message: string;
}>({
    status: 'idle',
    message: '',
});
let editUnitCodeCheckTimer: ReturnType<typeof setTimeout> | null = null;
let editUnitCodeAbortController: AbortController | null = null;
let editUnitCloseResetTimer: ReturnType<typeof setTimeout> | null = null;

const addEmployeeDialogOpen = ref(false);
const selectedPositionId = ref<string>('');
const selectedEmployee = ref<OrgChartAssignableEmployeeHit | null>(null);
const addEmployeeIsPrimary = ref(false);
const addEmployeeSubmitting = ref(false);
const addEmployeeError = ref<string | null>(null);
const addEmployeeStartDate = ref<string>('');
let addEmployeeCloseResetTimer: ReturnType<typeof setTimeout> | null = null;
const DIALOG_CLOSE_RESET_DELAY_MS = 200;

const addEmployeeComboboxModel = computed({
    get(): OrgChartAssignableEmployeeHit | null {
        return selectedEmployee.value;
    },
    set(hit: OrgChartAssignableEmployeeHit | null) {
        selectedEmployee.value = hit;
        if (hit === null) {
            selectedPositionId.value = '';

            return;
        }

        if (hit.positions.length === 1) {
            selectedPositionId.value = String(hit.positions[0].id);

            return;
        }

        selectedPositionId.value = '';
    },
});

const addEmployeePrimaryLabel = computed(() => {
    if (addEmployeeSubmitting.value) {
        return 'Assigning…';
    }

    if (!selectedEmployee.value) {
        return 'Choose employee';
    }

    if (selectedEmployee.value.positions.length === 0) {
        return 'No position available';
    }

    if (selectedPositionId.value === '') {
        return 'Choose position';
    }

    return 'Assign employee';
});

const canBeRootUnitTypeNames = ['Branch'];
const allowedChildUnitTypeNamesByParentUnitTypeName = {
    Branch: ['Department', 'Section'],
    Department: ['Section'],
    Section: [],
} as const;

const allowedChildUnitTypeNames = computed<string[]>(() => {
    const bucket = props.nodeBucket;

    if (!bucket) {
        return [];
    }

    if (bucket === 'org') {
        // Top-level Organization node: allow any unit type that is eligible to be a root.
        return [...canBeRootUnitTypeNames];
    }

    if (bucket === 'branch') {
        return [...allowedChildUnitTypeNamesByParentUnitTypeName.Branch];
    }

    if (bucket === 'department') {
        return [...allowedChildUnitTypeNamesByParentUnitTypeName.Department];
    }

    // bucket === 'section'
    return [...allowedChildUnitTypeNamesByParentUnitTypeName.Section];
});

const canManageThisNode = computed(() => {
    if (!props.chartCapabilities.canManageBranch) {
        return false;
    }

    if (props.nodeBucket === 'org') {
        return props.chartCapabilities.canManageOrganizationNode;
    }

    return true;
});

const selectedArea = computed(() => {
    return (
        (props.chartAreas ?? []).find(
            (area) => String(area.id) === selectedAreaId.value,
        ) ?? null
    );
});

const isUnitActive = ref(props.isActive ?? true);
watch(
    () => props.isActive,
    (next) => {
        isUnitActive.value = next ?? true;
    },
    { immediate: true },
);

const toggleUnitStatusDialogOpen = ref(false);
const deleteUnitDialogOpen = ref(false);
const unitActionSubmitting = ref(false);

const unitStatusActionLabel = computed(() =>
    isUnitActive.value ? 'Deactivate Unit' : 'Activate Unit',
);

const unitStatusActionDescription = computed(() => {
    return isUnitActive.value
        ? 'Deactivate this unit? It will be hidden from active chart operations.'
        : 'Activate this unit? It will return to active chart operations.';
});

function onToggleUnitStatus(): void {
    if (!canManageThisNode.value || !isUnitNode.value) {
        return;
    }

    toggleUnitStatusDialogOpen.value = true;
}

function onDeleteUnit(): void {
    if (!canManageThisNode.value || !isUnitNode.value) {
        return;
    }

    deleteUnitDialogOpen.value = true;
}

function firstMutationErrorMessage(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object' || !('errors' in payload)) {
        return null;
    }

    const errors = (payload as { errors?: Record<string, string[] | string> })
        .errors;
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
        } else if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return null;
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

function resolveCsrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

const flowNodeWideChartReloadKeys = [
    'orgChart',
    'chartScope',
    'chartBranchId',
    'chartBranches',
    'chartCapabilities',
] as const;

const flowNodeChartReloadKeys = [
    'orgChart',
    'chartBranchId',
    'chartBranches',
    'chartCapabilities',
] as const;

function reloadOrganizationChartPartial(only: readonly string[]): void {
    const loadingToastId = appToast.loading('Updating chart…');
    router.reload({
        only: [...only],
        showProgress: false,
        onFinish: () => {
            appToast.dismiss(loadingToastId);
        },
        onError: () => {
            appToast.error('Could not refresh the chart.');
        },
    });
}

function refreshOrganizationChart(): void {
    reloadOrganizationChartPartial(flowNodeWideChartReloadKeys);
}

async function confirmToggleUnitStatus(): Promise<void> {
    const unitId = currentNodeUnitId();
    if (!isUnitNode.value || !props.chartBranchId || unitId === null) {
        appToast.error('Unable to update unit status right now.');
        return;
    }

    unitActionSubmitting.value = true;

    const activating = !isUnitActive.value;
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
                chart_branch_id: props.chartBranchId,
                node_id: props.nodeId,
            }),
        });

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            appToast.error(
                firstMutationErrorMessage(payload) ??
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

        const payload = await parseJsonPayload<{
            data?: { is_active?: boolean };
        }>(response);
        if (typeof payload?.data?.is_active === 'boolean') {
            isUnitActive.value = payload.data.is_active;
        }

        toggleUnitStatusDialogOpen.value = false;
        refreshOrganizationChart();
        appToast.success(activating ? 'Unit activated.' : 'Unit deactivated.');
    } catch {
        appToast.error('Unable to update unit status right now.');
    } finally {
        unitActionSubmitting.value = false;
    }
}

async function confirmDeleteUnit(): Promise<void> {
    const unitId = currentNodeUnitId();
    if (!isUnitNode.value || !props.chartBranchId || unitId === null) {
        appToast.error('Unable to delete this unit right now.');
        return;
    }

    unitActionSubmitting.value = true;

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
                chart_branch_id: props.chartBranchId,
                node_id: props.nodeId,
            }),
        });

        if (response.status === 422) {
            const payload = await parseJsonPayload<{
                errors?: Record<string, string[] | string>;
            }>(response);
            appToast.error(
                firstMutationErrorMessage(payload) ??
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

        deleteUnitDialogOpen.value = false;
        refreshOrganizationChart();
        appToast.success('Unit deletion applied.');
    } catch {
        appToast.error('Unable to delete this unit right now.');
    } finally {
        unitActionSubmitting.value = false;
    }
}

function onEditUnit(): void {
    if (!canManageThisNode.value) {
        return;
    }

    editUnitDialogOpen.value = true;
    editUnitName.value = props.fullName;
    editUnitCode.value = props.code.toUpperCase();
    editUnitError.value = null;
    editUnitCodeAvailability.value = { status: 'idle', message: '' };
    if (editUnitCloseResetTimer !== null) {
        clearTimeout(editUnitCloseResetTimer);
        editUnitCloseResetTimer = null;
    }
}

function currentNodeUnitId(): number | null {
    if (!props.nodeId.startsWith('unit-')) {
        return null;
    }

    const id = Number.parseInt(props.nodeId.slice('unit-'.length), 10);
    return Number.isFinite(id) && id > 0 ? id : null;
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

function onAddEmployeeStartDateSelect(value: unknown, close: () => void): void {
    if (!value || Array.isArray(value) || typeof value !== 'object') {
        addEmployeeStartDate.value = '';
        close();
        return;
    }
    if (
        !('toDate' in value) ||
        typeof (value as { toDate?: unknown }).toDate !== 'function'
    ) {
        addEmployeeStartDate.value = '';
        close();
        return;
    }

    addEmployeeStartDate.value = calendarDateValueToIsoYmd(value as DateValue);
    close();
}

function resetEditUnitDialogState(): void {
    editUnitName.value = '';
    editUnitCode.value = '';
    editUnitSubmitting.value = false;
    editUnitError.value = null;
    editUnitCodeAvailability.value = { status: 'idle', message: '' };
}

function cancelEditUnitCodeCheckRequest(): void {
    if (editUnitCodeCheckTimer !== null) {
        clearTimeout(editUnitCodeCheckTimer);
        editUnitCodeCheckTimer = null;
    }

    if (editUnitCodeAbortController !== null) {
        editUnitCodeAbortController.abort();
        editUnitCodeAbortController = null;
    }
}

function scheduleEditUnitStateReset(): void {
    if (editUnitCloseResetTimer !== null) {
        clearTimeout(editUnitCloseResetTimer);
    }
    editUnitCloseResetTimer = setTimeout(() => {
        resetEditUnitDialogState();
        editUnitCloseResetTimer = null;
    }, DIALOG_CLOSE_RESET_DELAY_MS);
}

async function checkEditUnitCodeAvailability(code: string): Promise<void> {
    const unitId = currentNodeUnitId();
    if (
        !editUnitDialogOpen.value ||
        !canManageThisNode.value ||
        !props.chartBranchId ||
        unitId === null
    ) {
        return;
    }

    const normalizedCode = code.trim().toUpperCase();
    if (normalizedCode === '') {
        editUnitCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    cancelEditUnitCodeCheckRequest();
    editUnitCodeAvailability.value = {
        status: 'checking',
        message: 'Checking code availability...',
    };
    editUnitCodeAbortController = new AbortController();

    try {
        const params = new URLSearchParams({
            chart_branch_id: String(props.chartBranchId),
            node_id: props.nodeId,
            code: normalizedCode,
            ignore_unit_id: String(unitId),
        });

        const response = await fetch(
            `/organization-chart/units/check-code-availability?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
                signal: editUnitCodeAbortController.signal,
            },
        );

        if (!response.ok) {
            editUnitCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
            return;
        }

        const payload = (await response.json()) as {
            code?: { status?: UnitCodeAvailabilityStatus; message?: string };
        };
        const result = payload.code;
        if (!result?.status) {
            editUnitCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
            return;
        }

        editUnitCodeAvailability.value = {
            status: result.status,
            message: result.message ?? '',
        };
    } catch (error) {
        if ((error as { name?: string }).name === 'AbortError') {
            return;
        }
        editUnitCodeAvailability.value = {
            status: 'invalid',
            message: 'Unable to verify code right now.',
        };
    } finally {
        editUnitCodeAbortController = null;
    }
}

const canSubmitEditUnit = computed(() => {
    if (editUnitSubmitting.value) {
        return false;
    }

    if (editUnitName.value.trim() === '' || editUnitCode.value.trim() === '') {
        return false;
    }

    return (
        editUnitCodeAvailability.value.status !== 'checking' &&
        editUnitCodeAvailability.value.status !== 'taken' &&
        editUnitCodeAvailability.value.status !== 'invalid'
    );
});

async function submitEditUnit(): Promise<void> {
    const unitId = currentNodeUnitId();
    if (!canSubmitEditUnit.value || !props.chartBranchId || unitId === null) {
        return;
    }

    editUnitSubmitting.value = true;
    editUnitError.value = null;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    try {
        const response = await fetch(`/organization-chart/units/${unitId}`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                chart_branch_id: props.chartBranchId,
                node_id: props.nodeId,
                name: editUnitName.value.trim(),
                code: editUnitCode.value.trim(),
            }),
        });

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            const firstError = Object.values(payload.errors ?? {})
                .flat()
                .find((msg) => typeof msg === 'string' && msg.trim() !== '');
            editUnitError.value =
                firstError ?? 'Please check the unit details and try again.';
            appToast.error(editUnitError.value);
            return;
        }

        if (!response.ok) {
            editUnitError.value =
                response.status === 403
                    ? 'You are not allowed to edit this unit.'
                    : 'Unable to update unit right now.';
            appToast.error(editUnitError.value);
            return;
        }

        cancelEditUnitCodeCheckRequest();
        editUnitDialogOpen.value = false;
        reloadOrganizationChartPartial(flowNodeChartReloadKeys);
        appToast.success('Unit updated.');
    } catch {
        editUnitError.value = 'Unable to update unit right now.';
        appToast.error(editUnitError.value);
    } finally {
        editUnitSubmitting.value = false;
    }
}

/** Opens a placeholder dialog (no backend persistence yet). */
function onAddUnit(): void {
    if (!canManageThisNode.value) {
        return;
    }

    addUnitDialogOpen.value = true;
    const options = allowedChildUnitTypeNames.value;
    selectedChildUnitTypeName.value = options.length > 0 ? options[0] : null;
    selectedAreaId.value =
        props.nodeBucket === 'org'
            ? props.chartAreas?.[0]
                ? String(props.chartAreas[0].id)
                : ''
            : '';
    addUnitError.value = null;
    if (addUnitCloseResetTimer !== null) {
        clearTimeout(addUnitCloseResetTimer);
        addUnitCloseResetTimer = null;
    }
}

function resetAddUnitDialogState(): void {
    newUnitName.value = '';
    newUnitAlias.value = '';
    selectedAreaId.value = '';
    selectedChildUnitTypeName.value = null;
    addUnitError.value = null;
    unitCodeAvailability.value = { status: 'idle', message: '' };
}

function cancelUnitCodeCheckRequest(): void {
    if (unitCodeCheckTimer !== null) {
        clearTimeout(unitCodeCheckTimer);
        unitCodeCheckTimer = null;
    }

    if (unitCodeAbortController !== null) {
        unitCodeAbortController.abort();
        unitCodeAbortController = null;
    }
}

function scheduleAddUnitStateReset(): void {
    if (addUnitCloseResetTimer !== null) {
        clearTimeout(addUnitCloseResetTimer);
    }
    addUnitCloseResetTimer = setTimeout(() => {
        resetAddUnitDialogState();
        addUnitCloseResetTimer = null;
    }, DIALOG_CLOSE_RESET_DELAY_MS);
}

async function checkAddUnitCodeAvailability(code: string): Promise<void> {
    if (
        !addUnitDialogOpen.value ||
        !canManageThisNode.value ||
        !props.chartBranchId
    ) {
        return;
    }

    const normalizedCode = code.trim().toUpperCase();
    if (normalizedCode === '') {
        unitCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    cancelUnitCodeCheckRequest();
    unitCodeAvailability.value = {
        status: 'checking',
        message: 'Checking code availability...',
    };
    unitCodeAbortController = new AbortController();

    try {
        const params = new URLSearchParams({
            chart_branch_id: String(props.chartBranchId),
            node_id: props.nodeId,
            code: normalizedCode,
        });

        const response = await fetch(
            `/organization-chart/units/check-code-availability?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
                signal: unitCodeAbortController.signal,
            },
        );

        if (!response.ok) {
            unitCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
            return;
        }

        const payload = (await response.json()) as {
            code?: { status?: UnitCodeAvailabilityStatus; message?: string };
        };
        const result = payload.code;
        if (!result?.status) {
            unitCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
            return;
        }

        unitCodeAvailability.value = {
            status: result.status,
            message: result.message ?? '',
        };
    } catch (error) {
        if ((error as { name?: string }).name === 'AbortError') {
            return;
        }
        unitCodeAvailability.value = {
            status: 'invalid',
            message: 'Unable to verify code right now.',
        };
    } finally {
        unitCodeAbortController = null;
    }
}

const canSubmitAddUnit = computed(() => {
    if (addUnitSubmitting.value) {
        return false;
    }
    if (selectedChildUnitTypeName.value === null) {
        return false;
    }
    if (newUnitName.value.trim() === '' || newUnitAlias.value.trim() === '') {
        return false;
    }

    return (
        unitCodeAvailability.value.status !== 'checking' &&
        unitCodeAvailability.value.status !== 'taken' &&
        unitCodeAvailability.value.status !== 'invalid'
    );
});

async function submitAddUnit(): Promise<void> {
    if (!canSubmitAddUnit.value || !props.chartBranchId) {
        return;
    }

    const areaId =
        props.nodeBucket === 'org'
            ? Number.parseInt(selectedAreaId.value, 10)
            : null;
    if (
        props.nodeBucket === 'org' &&
        (!Number.isFinite(areaId ?? Number.NaN) || (areaId ?? 0) <= 0)
    ) {
        addUnitError.value = 'Please select an area.';
        appToast.error(addUnitError.value);
        return;
    }

    addUnitSubmitting.value = true;
    addUnitError.value = null;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    try {
        const response = await fetch('/organization-chart/units', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                chart_branch_id: props.chartBranchId,
                node_id: props.nodeId,
                unit_type_name: selectedChildUnitTypeName.value,
                name: newUnitName.value.trim(),
                code: newUnitAlias.value.trim(),
                area_id: props.nodeBucket === 'org' ? areaId : null,
            }),
        });

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            const firstError = Object.values(payload.errors ?? {})
                .flat()
                .find((msg) => typeof msg === 'string' && msg.trim() !== '');
            addUnitError.value =
                firstError ?? 'Please check the unit details and try again.';
            appToast.error(addUnitError.value);
            return;
        }

        if (!response.ok) {
            addUnitError.value =
                response.status === 403
                    ? 'You are not allowed to add units in this branch.'
                    : 'Unable to add unit right now.';
            appToast.error(addUnitError.value);
            return;
        }

        addUnitDialogOpen.value = false;
        router.reload({
            only: [
                'orgChart',
                'chartBranchId',
                'chartBranches',
                'chartCapabilities',
            ],
        });
        appToast.success('Unit added.');
    } catch {
        addUnitError.value = 'Unable to add unit right now.';
        appToast.error(addUnitError.value);
    } finally {
        addUnitSubmitting.value = false;
    }
}

const unitCodeAvailabilityClass = computed(() => {
    return (
        {
            idle: 'text-muted-foreground',
            checking: 'text-muted-foreground',
            available: 'text-green-600 dark:text-green-400',
            taken: 'text-destructive',
            invalid: 'text-destructive',
        } as const
    )[unitCodeAvailability.value.status];
});

const editUnitCodeAvailabilityClass = computed(() => {
    return (
        {
            idle: 'text-muted-foreground',
            checking: 'text-muted-foreground',
            available: 'text-green-600 dark:text-green-400',
            taken: 'text-destructive',
            invalid: 'text-destructive',
        } as const
    )[editUnitCodeAvailability.value.status];
});

const unitFieldClearButtonClass =
    'absolute right-2 top-1/2 z-[1] inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground';

watch(newUnitAlias, (nextCode) => {
    if (!addUnitDialogOpen.value || !canManageThisNode.value) {
        return;
    }

    const normalizedCode = nextCode.trim().toUpperCase();
    if (normalizedCode === '') {
        cancelUnitCodeCheckRequest();
        unitCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    if (unitCodeCheckTimer !== null) {
        clearTimeout(unitCodeCheckTimer);
    }

    unitCodeCheckTimer = setTimeout(() => {
        void checkAddUnitCodeAvailability(normalizedCode);
    }, 300);
});

watch(addUnitDialogOpen, (isOpen) => {
    if (isOpen) {
        if (addUnitCloseResetTimer !== null) {
            clearTimeout(addUnitCloseResetTimer);
            addUnitCloseResetTimer = null;
        }
        return;
    }

    cancelUnitCodeCheckRequest();
    scheduleAddUnitStateReset();
});

watch(editUnitCode, (nextCode) => {
    if (!editUnitDialogOpen.value || !canManageThisNode.value) {
        return;
    }

    const normalizedCode = nextCode.trim().toUpperCase();
    if (normalizedCode === '') {
        cancelEditUnitCodeCheckRequest();
        editUnitCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    if (editUnitCodeCheckTimer !== null) {
        clearTimeout(editUnitCodeCheckTimer);
    }

    editUnitCodeCheckTimer = setTimeout(() => {
        void checkEditUnitCodeAvailability(normalizedCode);
    }, 300);
});

watch(editUnitDialogOpen, (isOpen) => {
    if (isOpen) {
        if (editUnitCloseResetTimer !== null) {
            clearTimeout(editUnitCloseResetTimer);
            editUnitCloseResetTimer = null;
        }
        return;
    }

    cancelEditUnitCodeCheckRequest();
    scheduleEditUnitStateReset();
});

watch(selectedChildUnitTypeName, () => {
    if (newUnitAlias.value.trim() === '') {
        return;
    }

    if (unitCodeCheckTimer !== null) {
        clearTimeout(unitCodeCheckTimer);
    }

    unitCodeCheckTimer = setTimeout(() => {
        void checkAddUnitCodeAvailability(newUnitAlias.value);
    }, 100);
});

watch(canManageThisNode, (canManage) => {
    if (canManage) {
        return;
    }

    if (addUnitDialogOpen.value) {
        addUnitDialogOpen.value = false;
    }
    cancelUnitCodeCheckRequest();
    cancelEditUnitCodeCheckRequest();
    if (editUnitDialogOpen.value) {
        editUnitDialogOpen.value = false;
    }
});

function onAddEmployee(): void {
    if (!canManageThisNode.value) {
        return;
    }

    addEmployeeDialogOpen.value = true;
    selectedPositionId.value = '';
    selectedEmployee.value = null;
    addEmployeeIsPrimary.value = false;
    addEmployeeError.value = null;
    addEmployeeStartDate.value = calendarDateValueToIsoYmd(today(getLocalTimeZone()));
    if (addEmployeeCloseResetTimer !== null) {
        clearTimeout(addEmployeeCloseResetTimer);
        addEmployeeCloseResetTimer = null;
    }
}

async function submitAddEmployee(): Promise<void> {
    if (
        addEmployeeSubmitting.value ||
        !props.chartBranchId ||
        !props.nodeId.startsWith('unit-')
    ) {
        addEmployeeError.value = 'Unable to add employee right now.';
        appToast.error(addEmployeeError.value);
        return;
    }

    const employeeId = selectedEmployee.value?.id ?? null;
    const positionId = Number.parseInt(selectedPositionId.value, 10);
    if (
        employeeId === null ||
        !Number.isFinite(positionId) ||
        positionId <= 0
    ) {
        addEmployeeError.value =
            'Please select an employee and position first.';
        appToast.error(addEmployeeError.value);
        return;
    }
    if (addEmployeeStartDate.value.trim() === '') {
        addEmployeeError.value = 'Please provide the assignment start date.';
        appToast.error(addEmployeeError.value);
        return;
    }

    addEmployeeSubmitting.value = true;
    addEmployeeError.value = null;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    try {
        const response = await fetch('/organization-chart/employees', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({
                chart_branch_id: props.chartBranchId,
                node_id: props.nodeId,
                employee_id: employeeId,
                position_id: positionId,
                start_date: addEmployeeStartDate.value,
                is_primary: addEmployeeIsPrimary.value,
                is_head: false,
            }),
        });

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            const firstError = Object.values(payload.errors ?? {})
                .flat()
                .find((msg) => typeof msg === 'string' && msg.trim() !== '');
            addEmployeeError.value =
                firstError ??
                'Please check the selected employee and try again.';
            appToast.error(addEmployeeError.value);
            return;
        }

        if (!response.ok) {
            addEmployeeError.value =
                response.status === 403
                    ? 'You are not allowed to add employees in this unit.'
                    : 'Unable to add employee right now.';
            appToast.error(addEmployeeError.value);
            return;
        }

        addEmployeeDialogOpen.value = false;
        reloadOrganizationChartPartial(flowNodeChartReloadKeys);
        appToast.success('Employee assigned to unit.');
    } catch {
        addEmployeeError.value = 'Unable to add employee right now.';
        appToast.error(addEmployeeError.value);
    } finally {
        addEmployeeSubmitting.value = false;
    }
}

function scheduleAddEmployeeStateReset(): void {
    if (addEmployeeCloseResetTimer !== null) {
        clearTimeout(addEmployeeCloseResetTimer);
    }
    addEmployeeCloseResetTimer = setTimeout(() => {
        selectedPositionId.value = '';
        selectedEmployee.value = null;
        addEmployeeIsPrimary.value = false;
        addEmployeeStartDate.value = '';
        addEmployeeError.value = null;
        addEmployeeSubmitting.value = false;
        addEmployeeCloseResetTimer = null;
    }, DIALOG_CLOSE_RESET_DELAY_MS);
}

const canSubmitSelectedEmployee = computed(() => {
    if (selectedEmployee.value === null) {
        return false;
    }

    return selectedPositionId.value !== '';
});

watch(
    () => sheetBridge?.pendingNodeAction?.value ?? null,
    (payload) => {
        if (!sheetBridge || !payload) {
            return;
        }

        if (payload.nodeId !== props.nodeId) {
            return;
        }

        if (payload.kind === 'editUnit') {
            onEditUnit();
        } else if (payload.kind === 'addUnit') {
            onAddUnit();
        } else {
            onAddEmployee();
        }

        sheetBridge.consumePendingNodeAction();
    },
);

watch(addEmployeeDialogOpen, (isOpen) => {
    if (!isOpen) {
        scheduleAddEmployeeStateReset();
    } else if (addEmployeeCloseResetTimer !== null) {
        clearTimeout(addEmployeeCloseResetTimer);
        addEmployeeCloseResetTimer = null;
    }
});

onUnmounted(() => {
    cancelUnitCodeCheckRequest();
    cancelEditUnitCodeCheckRequest();
    if (addUnitCloseResetTimer !== null) {
        clearTimeout(addUnitCloseResetTimer);
    }
    if (editUnitCloseResetTimer !== null) {
        clearTimeout(editUnitCloseResetTimer);
    }
    if (addEmployeeCloseResetTimer !== null) {
        clearTimeout(addEmployeeCloseResetTimer);
    }
});
</script>

<template>
    <div>
        <div
            data-org-chart-node
            class="cursor-pointer"
            :class="[
                'group/node nodrag pointer-events-auto relative flex w-[260px] flex-col rounded-sm border p-4 shadow-sm',
                visual.cardClass,
            ]"
            :style="unitBorderStyle"
            @click="openThisNodeSheet"
        >
            <div class="flex items-center justify-between gap-2">
                <div
                    :class="[
                        'text-[11px] font-medium tracking-wide uppercase',
                        visual.unitLabelClass,
                    ]"
                >
                    {{ visual.unitLabel }}
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <button
                            type="button"
                            class="nodrag nopan pointer-events-auto inline-flex h-6 w-6 items-center justify-center rounded-sm text-muted-foreground/90 transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Node actions"
                            @click.stop
                            @pointerdown.stop
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 5.25a1.5 1.5 0 1 0 0 3a1.5 1.5 0 0 0 0-3ZM12 10.5a1.5 1.5 0 1 0 0 3a1.5 1.5 0 0 0 0-3ZM12 15.75a1.5 1.5 0 1 0 0 3a1.5 1.5 0 0 0 0-3Z"
                                />
                            </svg>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side="right"
                        align="start"
                        class="w-52 min-w-52"
                    >
                        <DropdownMenuItem
                            class="nodrag nopan"
                            @select="onViewDetails"
                        >
                            <Eye />
                            View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canManageThisNode"
                            class="nodrag nopan"
                            @select="onEditUnit"
                        >
                            <Pencil />
                            Edit Unit
                        </DropdownMenuItem>
                        <template v-if="canManageThisNode">
                            <DropdownMenuSeparator class="bg-border/80" />
                            <DropdownMenuItem
                                class="nodrag nopan"
                                @select="onAddUnit"
                            >
                                <PlusSquare />
                                Add Unit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="nodrag nopan"
                                @select="onAddEmployee"
                            >
                                <UserPlus />
                                Add Employee
                            </DropdownMenuItem>
                            <template v-if="isUnitNode">
                                <DropdownMenuSeparator class="bg-border/80" />
                                <DropdownMenuItem
                                    class="nodrag nopan"
                                    @select="onToggleUnitStatus"
                                >
                                    <Power />
                                    {{ unitStatusActionLabel }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    class="nodrag nopan text-destructive focus:text-destructive"
                                    @select="onDeleteUnit"
                                >
                                    <Trash2 />
                                    Delete Unit
                                </DropdownMenuItem>
                            </template>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div
                :class="[
                    'mt-2 truncate text-base leading-tight font-semibold',
                    visual.nameClass,
                ]"
            >
                {{ fullName }}
            </div>
            <Badge
                variant="secondary"
                :class="[
                    'mt-2 w-fit font-mono text-[11px] tracking-[0.12em] uppercase',
                    visual.codeClass,
                ]"
            >
                {{ code }}
            </Badge>

            <Handle id="target-top" type="target" :position="Position.Top" />
            <Handle
                id="source-bottom"
                type="source"
                :position="Position.Bottom"
            />
        </div>

        <Dialog v-model:open="toggleUnitStatusDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ unitStatusActionLabel }}</DialogTitle>
                    <DialogDescription>
                        {{ unitStatusActionDescription }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="unitActionSubmitting"
                        @click="toggleUnitStatusDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="unitActionSubmitting"
                        @click="confirmToggleUnitStatus"
                    >
                        {{ unitStatusActionLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteUnitDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Unit</DialogTitle>
                    <DialogDescription>
                        This is a frontend-only placeholder for now. Backend
                        validation and delete rules will be wired next.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="unitActionSubmitting"
                        @click="deleteUnitDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="unitActionSubmitting"
                        @click="confirmDeleteUnit"
                    >
                        Delete Unit
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editUnitDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Unit</DialogTitle>
                    <DialogDescription>
                        Update this unit name and code.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div class="grid gap-2">
                        <Label for="edit-unit-name">Name</Label>
                        <div class="relative">
                            <Input
                                id="edit-unit-name"
                                v-model="editUnitName"
                                class="pr-9"
                                placeholder="e.g. Talent & Recruitment"
                                autocomplete="organization"
                            />
                            <button
                                v-if="editUnitName !== ''"
                                type="button"
                                :class="unitFieldClearButtonClass"
                                aria-label="Clear edit unit name"
                                @click="editUnitName = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-unit-code">Code</Label>
                        <div class="relative">
                            <Input
                                id="edit-unit-code"
                                v-model="editUnitCode"
                                class="pr-9"
                                placeholder="e.g. HR-REC"
                                autocomplete="off"
                            />
                            <button
                                v-if="editUnitCode !== ''"
                                type="button"
                                :class="unitFieldClearButtonClass"
                                aria-label="Clear edit unit code"
                                @click="editUnitCode = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p
                            v-if="editUnitCodeAvailability.status !== 'idle'"
                            class="text-xs"
                            :class="editUnitCodeAvailabilityClass"
                        >
                            {{ editUnitCodeAvailability.message }}
                        </p>
                        <p
                            v-if="editUnitError"
                            class="text-xs text-destructive"
                        >
                            {{ editUnitError }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="editUnitDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="!canSubmitEditUnit"
                        @click="submitEditUnit"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="addUnitDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Unit</DialogTitle>
                    <DialogDescription>
                        Placeholder form. Backend connection coming soon.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <p class="text-sm text-muted-foreground">
                        Parent:
                        <span class="font-medium text-foreground">{{
                            fullName
                        }}</span>
                    </p>

                    <div class="grid gap-2">
                        <Label for="unit-type">Unit Type</Label>
                        <Select
                            v-model="selectedChildUnitTypeName"
                            :disabled="allowedChildUnitTypeNames.length === 0"
                        >
                            <SelectTrigger id="unit-type" class="w-full">
                                <SelectValue placeholder="Select unit type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="typeName in allowedChildUnitTypeNames"
                                    :key="typeName"
                                    :value="typeName"
                                >
                                    {{ typeName }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label
                            v-if="nodeBucket === 'org'"
                            for="unit-area-select"
                        >
                            Area
                        </Label>
                        <Select
                            v-if="nodeBucket === 'org'"
                            v-model="selectedAreaId"
                            :disabled="
                                addUnitSubmitting ||
                                (chartAreas?.length ?? 0) === 0
                            "
                        >
                            <SelectTrigger id="unit-area-select" class="w-full">
                                <SelectValue v-if="selectedArea" as-child>
                                    <div class="flex min-w-0 items-end gap-2">
                                        <span
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ selectedArea.name }}
                                        </span>
                                        <span
                                            class="self-end pb-px font-mono text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                                        >
                                            {{ selectedArea.code }}
                                        </span>
                                    </div>
                                </SelectValue>
                                <SelectValue
                                    v-else
                                    :placeholder="
                                        (chartAreas?.length ?? 0) > 0
                                            ? 'Select area'
                                            : 'No areas available'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="area in chartAreas ?? []"
                                    :key="area.id"
                                    :value="String(area.id)"
                                >
                                    <div class="flex min-w-0 items-end gap-2">
                                        <span
                                            class="truncate text-sm font-medium text-foreground"
                                        >
                                            {{ area.name }}
                                        </span>
                                        <span
                                            class="self-end pb-px font-mono text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                                        >
                                            {{ area.code }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="
                                nodeBucket === 'org' &&
                                (chartAreas?.length ?? 0) === 0
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Add an area first from Edit Structure.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-name">Name</Label>
                        <div class="relative">
                            <Input
                                id="unit-name"
                                v-model="newUnitName"
                                class="pr-9"
                                placeholder="e.g. Talent & Recruitment"
                                autocomplete="organization"
                            />
                            <button
                                v-if="newUnitName !== ''"
                                type="button"
                                :class="unitFieldClearButtonClass"
                                aria-label="Clear unit name"
                                @click="newUnitName = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="unit-code">Code</Label>
                        <div class="relative">
                            <Input
                                id="unit-code"
                                v-model="newUnitAlias"
                                class="pr-9"
                                placeholder="e.g. HR-REC"
                                autocomplete="off"
                            />
                            <button
                                v-if="newUnitAlias !== ''"
                                type="button"
                                :class="unitFieldClearButtonClass"
                                aria-label="Clear unit code"
                                @click="newUnitAlias = ''"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p
                            v-if="unitCodeAvailability.status !== 'idle'"
                            class="text-xs"
                            :class="unitCodeAvailabilityClass"
                        >
                            {{ unitCodeAvailability.message }}
                        </p>
                        <p v-if="addUnitError" class="text-xs text-destructive">
                            {{ addUnitError }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="addUnitDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="!canSubmitAddUnit"
                        @click="submitAddUnit"
                    >
                        Add Unit
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="addEmployeeDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add Employee</DialogTitle>
                    <DialogDescription>
                        Find an employee by name or ID (at least 2 characters),
                        pick the position for this unit, and set the assignment
                        start date.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <p class="text-sm text-muted-foreground">
                        Unit:
                        <span class="font-medium text-foreground">{{
                            fullName
                        }}</span>
                    </p>

                    <div class="grid gap-2">
                        <Label for="employee-search">Employee</Label>
                        <OrgChartEmployeeAddCombobox
                            id="employee-search"
                            v-model="addEmployeeComboboxModel"
                            :chart-branch-id="chartBranchId ?? null"
                            :node-id="nodeId"
                            :disabled="!canManageThisNode"
                            placeholder="Search by name or employee ID…"
                        />
                    </div>

                    <div v-if="selectedEmployee" class="grid gap-2">
                        <Label for="employee-position-select">Position</Label>
                        <Select
                            v-model="selectedPositionId"
                            :disabled="addEmployeeSubmitting"
                        >
                            <SelectTrigger
                                id="employee-position-select"
                                class="w-full"
                            >
                                <SelectValue
                                    :placeholder="
                                        selectedEmployee.positions.length > 0
                                            ? 'Select one position'
                                            : 'No active positions available'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="position in selectedEmployee.positions"
                                    :key="position.id"
                                    :value="String(position.id)"
                                >
                                    {{ position.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="selectedEmployee.positions.length === 0"
                            class="text-xs text-muted-foreground"
                        >
                            This employee has no active position available for
                            assignment.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <div
                            class="mt-1 flex items-center justify-between rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <p class="text-sm text-foreground">
                                Primary assignment designation
                            </p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        addEmployeeIsPrimary
                                            ? 'Primary'
                                            : 'Not primary'
                                    }}
                                </span>
                                <Switch
                                    id="employee-assignment-is-primary"
                                    v-model="addEmployeeIsPrimary"
                                    :disabled="addEmployeeSubmitting"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="employee-start-date">Start Date</Label>
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button
                                    id="employee-start-date"
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-between gap-2 font-normal"
                                    :disabled="addEmployeeSubmitting"
                                >
                                    <span
                                        v-if="
                                            isoDateDisplay(addEmployeeStartDate)
                                        "
                                        class="min-w-0 flex-1 truncate text-left text-foreground"
                                    >
                                        {{
                                            isoDateDisplay(addEmployeeStartDate)
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="flex-1 text-left text-muted-foreground"
                                    >
                                        Select start date
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
                                            addEmployeeStartDate,
                                        )
                                    "
                                    @update:model-value="
                                        (value) =>
                                            onAddEmployeeStartDateSelect(
                                                value,
                                                close,
                                            )
                                    "
                                />
                            </PopoverContent>
                        </Popover>
                    </div>
                    <p v-if="addEmployeeError" class="text-xs text-destructive">
                        {{ addEmployeeError }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="addEmployeeSubmitting"
                        @click="addEmployeeDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            !canSubmitSelectedEmployee || addEmployeeSubmitting
                        "
                        @click="submitAddEmployee"
                    >
                        <Loader2
                            v-if="addEmployeeSubmitting"
                            class="size-4 animate-spin"
                        />
                        <span>{{ addEmployeePrimaryLabel }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
[data-org-chart-node] :deep(.vue-flow__handle) {
    box-sizing: border-box;
    width: 2rem;
    height: 0.5rem;
    min-width: 1.5rem !important;
    min-height: 0.5rem !important;
    border-radius: 9999px;
    border: none;
    background-color: color-mix(
        in oklch,
        var(--muted-foreground) 25%,
        var(--muted)
    );
    pointer-events: none;
}
</style>
