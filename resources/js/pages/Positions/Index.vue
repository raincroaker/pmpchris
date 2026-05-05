<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Plus, Search } from 'lucide-vue-next';
import { computed, h, onUnmounted, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import type {
    PositionEmployeeListItem,
    PositionFilters,
    PositionRow,
    PositionsPaginator,
} from '@/pages/Positions/positionIndexTypes';
import PositionsIndexActionsMenu from '@/pages/Positions/PositionsIndexActionsMenu.vue';
import PositionsIndexPositionColumnHeader from '@/pages/Positions/PositionsIndexPositionColumnHeader.vue';
import PositionsIndexStatusFilterHeader from '@/pages/Positions/PositionsIndexStatusFilterHeader.vue';
import {
    employees as employeesPageRoute,
    positions as positionsRoute,
} from '@/routes';
import { employees as positionEmployeesJson } from '@/routes/positions';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    positions: PositionsPaginator;
    filters: PositionFilters;
    organization: { id: number; code: string; name: string } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Positions', href: positionsRoute() },
];

/** When true, ignore `props.filters.search` → `localSearch` sync so stale Inertia responses do not overwrite in-progress typing. */
const positionsSearchFieldFocused = ref(false);
const {
    localSearch,
    syncFromServerSearch,
    onSearchUpdate,
    onSearchKeyup,
    onSearchCommit,
} = useDebouncedSearchInput({
    initialValue: props.filters.search,
    debounceMs: 500,
    onDebouncedSearch: (value) => applyQuery({ search: value, page: 1 }),
});

const addPositionDialogOpen = ref(false);
const newPositionCode = ref('');
const newPositionTitle = ref('');
const newPositionDescription = ref('');
type PositionCodeAvailabilityStatus =
    | 'idle'
    | 'checking'
    | 'available'
    | 'taken'
    | 'invalid';
const addCodeAvailability = ref<{
    status: PositionCodeAvailabilityStatus;
    message: string;
}>({
    status: 'idle',
    message: '',
});
let addCodeCheckTimer: ReturnType<typeof setTimeout> | null = null;
let addCodeCheckAbortController: AbortController | null = null;

const editPositionDialogOpen = ref(false);
const editTargetRow = ref<PositionRow | null>(null);
const editPositionCode = ref('');
const editPositionTitle = ref('');
const editPositionDescription = ref('');
const editCodeAvailability = ref<{
    status: PositionCodeAvailabilityStatus;
    message: string;
}>({
    status: 'idle',
    message: '',
});
let editCodeCheckTimer: ReturnType<typeof setTimeout> | null = null;
let editCodeCheckAbortController: AbortController | null = null;

const deleteAlertOpen = ref(false);
const deleteTargetRow = ref<PositionRow | null>(null);

const setInactiveAlertOpen = ref(false);
const setInactiveTargetRow = ref<PositionRow | null>(null);

const employeesDialogOpen = ref(false);
const employeesDialogTarget = ref<PositionRow | null>(null);
const employeesDialogList = ref<PositionEmployeeListItem[]>([]);
const employeesDialogLoading = ref(false);
const employeesDialogError = ref<string | null>(null);
const employeesDialogSearch = ref('');

const employeesDialogFilteredList = computed((): PositionEmployeeListItem[] => {
    const needle = employeesDialogSearch.value.trim().toLowerCase();
    const rows = employeesDialogList.value;

    if (needle === '') {
        return rows;
    }

    return rows.filter((emp) => {
        const name = emp.display_name.toLowerCase();
        const id = emp.id_number.toLowerCase();

        return name.includes(needle) || id.includes(needle);
    });
});

const employeesViewAllUrl = computed(() => {
    const t = employeesDialogTarget.value;
    if (t === null) {
        return employeesPageRoute.url();
    }

    return employeesPageRoute.url({
        query: { position_id: String(t.id) },
    });
});

/** Lets Dialog / AlertDialog leave animations finish before clearing row refs (avoids “pop”). */
const DIALOG_LEAVE_MS = 220;

let editDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;
let deleteDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;
let setInactiveDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;
let employeesDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;
let employeesFetchAbort: AbortController | undefined;
const addSubmitting = ref(false);
const editSubmitting = ref(false);
const deleteSubmitting = ref(false);
const setInactiveSubmitting = ref(false);
const addCodeAvailabilityClass = computed(() => {
    return (
        {
            idle: 'text-muted-foreground',
            checking: 'text-muted-foreground',
            available: 'text-green-600 dark:text-green-400',
            taken: 'text-destructive',
            invalid: 'text-destructive',
        } as const
    )[addCodeAvailability.value.status];
});
const editCodeAvailabilityClass = computed(() => {
    return (
        {
            idle: 'text-muted-foreground',
            checking: 'text-muted-foreground',
            available: 'text-green-600 dark:text-green-400',
            taken: 'text-destructive',
            invalid: 'text-destructive',
        } as const
    )[editCodeAvailability.value.status];
});

function normalizePositionCode(code: string): string {
    return code.toUpperCase().replace(/\s+/g, '').trim();
}

function cancelAddCodeAvailabilityRequest(): void {
    if (addCodeCheckTimer !== null) {
        clearTimeout(addCodeCheckTimer);
        addCodeCheckTimer = null;
    }
    if (addCodeCheckAbortController !== null) {
        addCodeCheckAbortController.abort();
        addCodeCheckAbortController = null;
    }
}

function cancelEditCodeAvailabilityRequest(): void {
    if (editCodeCheckTimer !== null) {
        clearTimeout(editCodeCheckTimer);
        editCodeCheckTimer = null;
    }
    if (editCodeCheckAbortController !== null) {
        editCodeCheckAbortController.abort();
        editCodeCheckAbortController = null;
    }
}

async function checkPositionCodeAvailability(
    code: string,
    ignorePositionId?: number,
    signal?: AbortSignal,
): Promise<{ status: PositionCodeAvailabilityStatus; message: string }> {
    const normalizedCode = normalizePositionCode(code);
    if (normalizedCode === '') {
        return { status: 'idle', message: '' };
    }

    const params = new URLSearchParams({ code: normalizedCode });
    if (
        typeof ignorePositionId === 'number' &&
        Number.isFinite(ignorePositionId) &&
        ignorePositionId > 0
    ) {
        params.set('ignore_position_id', String(ignorePositionId));
    }

    const response = await fetch(
        `/positions/check-code-availability?${params.toString()}`,
        {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal,
        },
    );

    if (!response.ok) {
        return {
            status: 'invalid',
            message: 'Unable to verify code right now.',
        };
    }

    const payload = (await response.json()) as {
        code?: { status?: PositionCodeAvailabilityStatus; message?: string };
    };

    if (!payload.code?.status) {
        return {
            status: 'invalid',
            message: 'Unable to verify code right now.',
        };
    }

    return {
        status: payload.code.status,
        message: payload.code.message ?? '',
    };
}

function resolveCsrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

function firstErrorMessage(payload: unknown): string | null {
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

watch(
    () => props.filters.search,
    (s) => {
        if (positionsSearchFieldFocused.value) {
            return;
        }

        syncFromServerSearch(s);
    },
);

watch(newPositionCode, (nextValue) => {
    const normalized = normalizePositionCode(nextValue);
    if (nextValue !== normalized) {
        newPositionCode.value = normalized;
        return;
    }

    if (!addPositionDialogOpen.value) {
        return;
    }

    cancelAddCodeAvailabilityRequest();
    if (normalized === '') {
        addCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    addCodeAvailability.value = {
        status: 'checking',
        message: 'Checking code availability...',
    };
    addCodeCheckTimer = setTimeout(async () => {
        try {
            addCodeCheckAbortController = new AbortController();
            const result = await checkPositionCodeAvailability(
                normalized,
                undefined,
                addCodeCheckAbortController.signal,
            );
            if (newPositionCode.value === normalized) {
                addCodeAvailability.value = result;
            }
        } catch (error) {
            if ((error as { name?: string }).name === 'AbortError') {
                return;
            }
            addCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
        } finally {
            addCodeCheckAbortController = null;
        }
    }, 300);
});

watch(editPositionCode, (nextValue) => {
    const normalized = normalizePositionCode(nextValue);
    if (nextValue !== normalized) {
        editPositionCode.value = normalized;
        return;
    }

    if (!editPositionDialogOpen.value) {
        return;
    }

    const target = editTargetRow.value;
    cancelEditCodeAvailabilityRequest();
    if (normalized === '' || target === null) {
        editCodeAvailability.value = { status: 'idle', message: '' };
        return;
    }

    editCodeAvailability.value = {
        status: 'checking',
        message: 'Checking code availability...',
    };
    editCodeCheckTimer = setTimeout(async () => {
        try {
            editCodeCheckAbortController = new AbortController();
            const result = await checkPositionCodeAvailability(
                normalized,
                target.id,
                editCodeCheckAbortController.signal,
            );
            if (editPositionCode.value === normalized) {
                editCodeAvailability.value = result;
            }
        } catch (error) {
            if ((error as { name?: string }).name === 'AbortError') {
                return;
            }
            editCodeAvailability.value = {
                status: 'invalid',
                message: 'Unable to verify code right now.',
            };
        } finally {
            editCodeCheckAbortController = null;
        }
    }, 300);
});

function buildQuery(
    overrides: Partial<{
        search: string;
        status: PositionFilters['status'];
        sort: PositionFilters['sort'];
        direction: PositionFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): Record<string, string | number> {
    const f: PositionFilters = { ...props.filters, ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : props.positions.current_page;
    const q: Record<string, string | number> = {
        status: f.status,
        sort: f.sort,
        direction: f.direction,
        per_page: f.per_page,
        page,
    };
    if (f.search.trim() !== '') {
        q.search = f.search.trim();
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        search: string;
        status: PositionFilters['status'];
        sort: PositionFilters['sort'];
        direction: PositionFilters['direction'];
        per_page: number;
        page: number;
    }> = {},
): void {
    router.get(
        positionsRoute.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onPerPageChange(value: number): void {
    applyQuery({ per_page: value, page: 1 });
}

function toggleSort(column: 'code' | 'title'): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

function onPaginationChange(updater: Updater<PaginationState>): void {
    const prev: PaginationState = {
        pageIndex: props.positions.current_page - 1,
        pageSize: props.positions.per_page,
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

function onAddPositionClick(): void {
    newPositionCode.value = '';
    newPositionTitle.value = '';
    newPositionDescription.value = '';
    addCodeAvailability.value = { status: 'idle', message: '' };
    addPositionDialogOpen.value = true;
}

function closeAddPositionDialog(): void {
    cancelAddCodeAvailabilityRequest();
    addCodeAvailability.value = { status: 'idle', message: '' };
    addPositionDialogOpen.value = false;
}

function submitAddPosition(): void {
    if (addSubmitting.value || props.organization === null) {
        return;
    }

    const code = newPositionCode.value.trim().toUpperCase();
    const title = newPositionTitle.value.trim();
    const description = newPositionDescription.value.trim();

    if (code === '' || title === '') {
        appToast.error('Code and title are required.');
        return;
    }
    if (
        addCodeAvailability.value.status === 'checking' ||
        addCodeAvailability.value.status === 'taken' ||
        addCodeAvailability.value.status === 'invalid'
    ) {
        appToast.error(
            addCodeAvailability.value.message ||
                'Please use an available code.',
        );
        return;
    }

    addSubmitting.value = true;

    const csrfToken = resolveCsrfToken();
    fetch('/positions', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({
            code,
            title,
            description: description === '' ? null : description,
        }),
    })
        .then(async (response) => {
            if (response.status === 422) {
                const payload = (await response.json()) as {
                    errors?: Record<string, string[] | string>;
                };
                appToast.error(
                    firstErrorMessage(payload) ?? 'Could not create position.',
                );
                return;
            }

            if (!response.ok) {
                appToast.error(
                    response.status === 403
                        ? 'You are not allowed to create positions.'
                        : 'Could not create position.',
                );
                return;
            }

            closeAddPositionDialog();
            appToast.success('Position created.');
            applyQuery({ page: 1 });
        })
        .catch(() => {
            appToast.error('Could not create position.');
        })
        .finally(() => {
            addSubmitting.value = false;
        });
}

function openEditPositionDialog(row: PositionRow): void {
    if (editDialogClearTimeout !== undefined) {
        clearTimeout(editDialogClearTimeout);
        editDialogClearTimeout = undefined;
    }

    editTargetRow.value = row;
    editPositionCode.value = row.code;
    editPositionTitle.value = row.title;
    editPositionDescription.value = row.description ?? '';
    editCodeAvailability.value = { status: 'idle', message: '' };
    editPositionDialogOpen.value = true;
}

function onEditPositionDialogOpenChange(open: boolean): void {
    if (open) {
        if (editDialogClearTimeout !== undefined) {
            clearTimeout(editDialogClearTimeout);
            editDialogClearTimeout = undefined;
        }

        editPositionDialogOpen.value = true;

        return;
    }

    cancelEditCodeAvailabilityRequest();
    editCodeAvailability.value = { status: 'idle', message: '' };
    editPositionDialogOpen.value = false;
    if (editDialogClearTimeout !== undefined) {
        clearTimeout(editDialogClearTimeout);
    }

    editDialogClearTimeout = setTimeout(() => {
        editTargetRow.value = null;
        editDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function closeEditPositionDialog(): void {
    onEditPositionDialogOpenChange(false);
}

function submitEditPosition(): void {
    const target = editTargetRow.value;
    if (editSubmitting.value || !target) {
        return;
    }

    const title = editPositionTitle.value.trim();
    const code = normalizePositionCode(editPositionCode.value);
    const description = editPositionDescription.value.trim();

    if (code === '' || title === '') {
        appToast.error('Code and title are required.');
        return;
    }
    if (
        editCodeAvailability.value.status === 'checking' ||
        editCodeAvailability.value.status === 'taken' ||
        editCodeAvailability.value.status === 'invalid'
    ) {
        appToast.error(
            editCodeAvailability.value.message ||
                'Please use an available code.',
        );
        return;
    }

    editSubmitting.value = true;
    const csrfToken = resolveCsrfToken();

    fetch(`/positions/${target.id}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({
            code,
            title,
            description: description === '' ? null : description,
        }),
    })
        .then(async (response) => {
            if (response.status === 422) {
                const payload = (await response.json()) as {
                    errors?: Record<string, string[] | string>;
                };
                appToast.error(
                    firstErrorMessage(payload) ?? 'Could not update position.',
                );
                return;
            }

            if (!response.ok) {
                appToast.error(
                    response.status === 403
                        ? 'You are not allowed to update this position.'
                        : 'Could not update position.',
                );
                return;
            }

            closeEditPositionDialog();
            appToast.success('Position updated.');
            applyQuery();
        })
        .catch(() => {
            appToast.error('Could not update position.');
        })
        .finally(() => {
            editSubmitting.value = false;
        });
}

function openDeletePositionDialog(row: PositionRow): void {
    if (deleteDialogClearTimeout !== undefined) {
        clearTimeout(deleteDialogClearTimeout);
        deleteDialogClearTimeout = undefined;
    }

    deleteTargetRow.value = row;
    deleteAlertOpen.value = true;
}

function onDeleteAlertOpenChange(open: boolean): void {
    if (open) {
        if (deleteDialogClearTimeout !== undefined) {
            clearTimeout(deleteDialogClearTimeout);
            deleteDialogClearTimeout = undefined;
        }

        deleteAlertOpen.value = true;

        return;
    }

    deleteAlertOpen.value = false;
    if (deleteDialogClearTimeout !== undefined) {
        clearTimeout(deleteDialogClearTimeout);
    }

    deleteDialogClearTimeout = setTimeout(() => {
        deleteTargetRow.value = null;
        deleteDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

async function confirmDeletePosition(): Promise<void> {
    const target = deleteTargetRow.value;
    if (deleteSubmitting.value || !target) {
        return;
    }

    deleteSubmitting.value = true;

    try {
        const csrfToken = resolveCsrfToken();
        const response = await fetch(`/positions/${target.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
        });

        if (response.status === 422) {
            const payload = (await response.json()) as {
                errors?: Record<string, string[] | string>;
            };
            appToast.error(
                firstErrorMessage(payload) ?? 'Could not delete position.',
            );
            return;
        }

        if (!response.ok) {
            appToast.error(
                response.status === 403
                    ? 'You are not allowed to delete this position.'
                    : 'Could not delete position.',
            );
            return;
        }

        onDeleteAlertOpenChange(false);
        appToast.success('Position deleted.');
        applyQuery();
    } catch {
        appToast.error('Could not delete position.');
    } finally {
        deleteSubmitting.value = false;
    }
}

function openSetInactivePositionDialog(row: PositionRow): void {
    if (setInactiveDialogClearTimeout !== undefined) {
        clearTimeout(setInactiveDialogClearTimeout);
        setInactiveDialogClearTimeout = undefined;
    }

    setInactiveTargetRow.value = row;
    setInactiveAlertOpen.value = true;
}

function onSetInactiveAlertOpenChange(open: boolean): void {
    if (open) {
        if (setInactiveDialogClearTimeout !== undefined) {
            clearTimeout(setInactiveDialogClearTimeout);
            setInactiveDialogClearTimeout = undefined;
        }

        setInactiveAlertOpen.value = true;

        return;
    }

    setInactiveAlertOpen.value = false;
    if (setInactiveDialogClearTimeout !== undefined) {
        clearTimeout(setInactiveDialogClearTimeout);
    }

    setInactiveDialogClearTimeout = setTimeout(() => {
        setInactiveTargetRow.value = null;
        setInactiveDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function confirmSetInactivePosition(): void {
    const target = setInactiveTargetRow.value;
    if (setInactiveSubmitting.value || !target) {
        return;
    }

    setInactiveSubmitting.value = true;
    const csrfToken = resolveCsrfToken();

    fetch(`/positions/${target.id}/deactivate`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
    })
        .then(async (response) => {
            if (response.status === 422) {
                const payload = (await response.json()) as {
                    errors?: Record<string, string[] | string>;
                };
                appToast.error(
                    firstErrorMessage(payload) ??
                        'Could not set position inactive.',
                );
                return;
            }

            if (!response.ok) {
                appToast.error(
                    response.status === 403
                        ? 'You are not allowed to update this position.'
                        : 'Could not set position inactive.',
                );
                return;
            }

            onSetInactiveAlertOpenChange(false);
            appToast.success('Position set to inactive.');
            applyQuery();
        })
        .catch(() => {
            appToast.error('Could not set position inactive.');
        })
        .finally(() => {
            setInactiveSubmitting.value = false;
        });
}

async function openEmployeesForPositionDialog(row: PositionRow): Promise<void> {
    if (employeesDialogClearTimeout !== undefined) {
        clearTimeout(employeesDialogClearTimeout);
        employeesDialogClearTimeout = undefined;
    }

    employeesFetchAbort?.abort();
    employeesFetchAbort = new AbortController();

    employeesDialogTarget.value = row;
    employeesDialogOpen.value = true;
    employeesDialogLoading.value = true;
    employeesDialogError.value = null;
    employeesDialogList.value = [];
    employeesDialogSearch.value = '';

    try {
        const url = positionEmployeesJson.url(row.id);
        const response = await fetch(url, {
            credentials: 'same-origin',
            signal: employeesFetchAbort.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Request failed');
        }

        const payload = (await response.json()) as {
            employees: PositionEmployeeListItem[];
        };

        employeesDialogList.value = payload.employees;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        employeesDialogError.value =
            'Could not load employees for this position.';
    } finally {
        employeesDialogLoading.value = false;
    }
}

function onEmployeesDialogOpenChange(open: boolean): void {
    if (open) {
        if (employeesDialogClearTimeout !== undefined) {
            clearTimeout(employeesDialogClearTimeout);
            employeesDialogClearTimeout = undefined;
        }

        employeesDialogOpen.value = true;

        return;
    }

    employeesFetchAbort?.abort();
    employeesFetchAbort = undefined;
    employeesDialogOpen.value = false;

    if (employeesDialogClearTimeout !== undefined) {
        clearTimeout(employeesDialogClearTimeout);
    }

    employeesDialogClearTimeout = setTimeout(() => {
        employeesDialogTarget.value = null;
        employeesDialogList.value = [];
        employeesDialogError.value = null;
        employeesDialogLoading.value = false;
        employeesDialogSearch.value = '';
        employeesDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function closeEmployeesForPositionDialog(): void {
    onEmployeesDialogOpenChange(false);
}

const columns: ColumnDef<PositionRow>[] = [
    {
        accessorKey: 'title',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-top',
        },
        header: () =>
            h(PositionsIndexPositionColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                onSortBy: (field: 'code' | 'title') => toggleSort(field),
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-0.5 py-0.5' }, [
                h(
                    'span',
                    { class: 'font-medium text-foreground' },
                    row.original.title,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.code,
                ),
            ]),
    },
    {
        accessorKey: 'description',
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Description',
            ),
        cell: ({ row }) => {
            const d = row.original.description;
            if (d === null || d.trim() === '') {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                'span',
                {
                    class: 'line-clamp-2 max-w-md',
                    title: d,
                },
                d,
            );
        },
        enableSorting: false,
    },
    {
        id: 'status',
        accessorKey: 'is_active',
        meta: {
            headClass: 'min-w-28',
        },
        header: () =>
            h(PositionsIndexStatusFilterHeader, {
                modelValue: props.filters.status,
                'onUpdate:modelValue': (v: PositionFilters['status']) => {
                    applyQuery({ status: v, page: 1 });
                },
            }),
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
            headClass: 'w-[100px] text-center',
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
            h(PositionsIndexActionsMenu, {
                row: row.original,
                onEdit: openEditPositionDialog,
                onDelete: openDeletePositionDialog,
                onSetInactive: openSetInactivePositionDialog,
                onShowEmployees: openEmployeesForPositionDialog,
            }),
        enableSorting: false,
    },
];

const table = useVueTable({
    get data() {
        return props.positions.data;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.positions.last_page,
    rowCount: props.positions.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.positions.current_page - 1,
                pageSize: props.positions.per_page,
            };
        },
    },
});

onUnmounted(() => {
    if (editDialogClearTimeout !== undefined) {
        clearTimeout(editDialogClearTimeout);
    }

    if (deleteDialogClearTimeout !== undefined) {
        clearTimeout(deleteDialogClearTimeout);
    }

    if (setInactiveDialogClearTimeout !== undefined) {
        clearTimeout(setInactiveDialogClearTimeout);
    }

    if (employeesDialogClearTimeout !== undefined) {
        clearTimeout(employeesDialogClearTimeout);
    }

    cancelAddCodeAvailabilityRequest();
    cancelEditCodeAvailabilityRequest();
    employeesFetchAbort?.abort();
});
</script>

<template>
    <Head title="Positions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div>
                <h1 class="text-xl font-semibold text-foreground">Positions</h1>
                <p v-if="organization" class="text-sm text-muted-foreground">
                    {{ organization.name }}
                    <span class="text-muted-foreground/80"
                        >({{ organization.code }})</span
                    >
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    No default organization is configured. Position rows cannot
                    be loaded until HR sets a default org.
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
                                id="positions_search"
                                :model-value="localSearch"
                                type="search"
                                placeholder="Search code, title, or description…"
                                autocomplete="off"
                                aria-label="Search positions by code, title, or description"
                                @focus="positionsSearchFieldFocused = true"
                                @blur="positionsSearchFieldFocused = false"
                                @update:model-value="onSearchUpdate"
                                @keyup="onSearchKeyup"
                                @change="onSearchCommit"
                                @search="onSearchCommit"
                            />
                        </InputGroup>
                    </div>
                </template>
                <template #end>
                    <Button
                        type="button"
                        class="shrink-0"
                        @click="onAddPositionClick"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        <span class="mr-1">Add Position</span>
                    </Button>
                </template>
            </HrisIndexToolbar>

            <div class="w-full">
                <HrisTanStackTable
                    :table="table"
                    empty-message="No positions match the current filters."
                />

                <HrisServerTablePagination
                    :total="positions.total"
                    :from="positions.from"
                    :to="positions.to"
                    :current-page="positions.current_page"
                    :last-page="positions.last_page"
                    :per-page="positions.per_page"
                    :can-previous-page="table.getCanPreviousPage()"
                    :can-next-page="table.getCanNextPage()"
                    @update:per-page="onPerPageChange"
                    @go-first="
                        onPaginationChange({
                            pageIndex: 0,
                            pageSize: positions.per_page,
                        })
                    "
                    @go-prev="table.previousPage()"
                    @go-next="table.nextPage()"
                    @go-last="
                        onPaginationChange({
                            pageIndex: Math.max(positions.last_page - 1, 0),
                            pageSize: positions.per_page,
                        })
                    "
                />
            </div>

            <Dialog v-model:open="addPositionDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add position</DialogTitle>
                        <DialogDescription>
                            Placeholder form. Backend connection coming soon.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-2">
                        <p
                            v-if="organization"
                            class="text-sm text-muted-foreground"
                        >
                            Organization:
                            <span class="font-medium text-foreground">
                                {{ organization.name }}
                            </span>
                            <span class="text-muted-foreground/80">
                                ({{ organization.code }})
                            </span>
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            No default organization is configured. Positions
                            cannot be created until HR sets a default org.
                        </p>

                        <div class="grid gap-2">
                            <Label for="add-position-code">Code</Label>
                            <Input
                                id="add-position-code"
                                v-model="newPositionCode"
                                placeholder="e.g. HR-MGR"
                                autocomplete="off"
                            />
                            <p
                                v-if="addCodeAvailability.status !== 'idle'"
                                class="text-xs"
                                :class="addCodeAvailabilityClass"
                            >
                                {{ addCodeAvailability.message }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="add-position-title">Title</Label>
                            <Input
                                id="add-position-title"
                                v-model="newPositionTitle"
                                placeholder="e.g. HR Manager"
                                autocomplete="off"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="add-position-description"
                                >Description</Label
                            >
                            <Textarea
                                id="add-position-description"
                                v-model="newPositionDescription"
                                placeholder="Optional"
                                rows="4"
                                class="min-h-24 resize-y"
                            />
                        </div>
                    </div>

                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="addSubmitting"
                            @click="closeAddPositionDialog"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            :disabled="
                                organization === null ||
                                addSubmitting ||
                                addCodeAvailability.status === 'checking' ||
                                addCodeAvailability.status === 'taken' ||
                                addCodeAvailability.status === 'invalid' ||
                                newPositionCode.trim() === '' ||
                                newPositionTitle.trim() === ''
                            "
                            @click="submitAddPosition"
                        >
                            {{ addSubmitting ? 'Adding...' : 'Add position' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="editPositionDialogOpen"
                @update:open="onEditPositionDialogOpenChange"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit position</DialogTitle>
                        <DialogDescription>
                            Placeholder form. Backend connection coming soon.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-2">
                        <p
                            v-if="organization"
                            class="text-sm text-muted-foreground"
                        >
                            Organization:
                            <span class="font-medium text-foreground">
                                {{ organization.name }}
                            </span>
                            <span class="text-muted-foreground/80">
                                ({{ organization.code }})
                            </span>
                        </p>

                        <div class="grid gap-2">
                            <Label for="edit-position-code">Code</Label>
                            <Input
                                id="edit-position-code"
                                v-model="editPositionCode"
                                autocomplete="off"
                            />
                            <p
                                v-if="editCodeAvailability.status !== 'idle'"
                                class="text-xs"
                                :class="editCodeAvailabilityClass"
                            >
                                {{ editCodeAvailability.message }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-position-title">Title</Label>
                            <Input
                                id="edit-position-title"
                                v-model="editPositionTitle"
                                placeholder="e.g. HR Manager"
                                autocomplete="off"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-position-description"
                                >Description</Label
                            >
                            <Textarea
                                id="edit-position-description"
                                v-model="editPositionDescription"
                                placeholder="Optional"
                                rows="4"
                                class="min-h-24 resize-y"
                            />
                        </div>
                    </div>

                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="editSubmitting"
                            @click="closeEditPositionDialog"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            :disabled="
                                editSubmitting ||
                                editPositionCode.trim() === '' ||
                                editPositionTitle.trim() === '' ||
                                editCodeAvailability.status === 'checking' ||
                                editCodeAvailability.status === 'taken' ||
                                editCodeAvailability.status === 'invalid'
                            "
                            @click="submitEditPosition"
                        >
                            {{ editSubmitting ? 'Saving...' : 'Save' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog
                :open="deleteAlertOpen"
                @update:open="onDeleteAlertOpenChange"
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete position?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will remove
                            <span class="font-medium text-foreground">
                                {{ deleteTargetRow?.title }}
                            </span>
                            ({{ deleteTargetRow?.code }}) when the workflow is
                            connected. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel type="button">
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            class="bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:bg-destructive/60 dark:hover:bg-destructive/90"
                            :disabled="deleteSubmitting"
                            @click="confirmDeletePosition"
                        >
                            {{ deleteSubmitting ? 'Deleting...' : 'Delete' }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                :open="setInactiveAlertOpen"
                @update:open="onSetInactiveAlertOpenChange"
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle
                            >Set position inactive?</AlertDialogTitle
                        >
                        <AlertDialogDescription>
                            <span class="font-medium text-foreground">
                                {{ setInactiveTargetRow?.title }}
                            </span>
                            ({{ setInactiveTargetRow?.code }}) will be marked
                            inactive when the workflow is connected.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel type="button">
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            class="bg-amber-600 text-white shadow-xs hover:bg-amber-600/90 focus-visible:ring-amber-600/30 dark:bg-amber-600 dark:hover:bg-amber-600/90"
                            :disabled="setInactiveSubmitting"
                            @click="confirmSetInactivePosition"
                        >
                            {{
                                setInactiveSubmitting
                                    ? 'Setting inactive...'
                                    : 'Set inactive'
                            }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog
                :open="employeesDialogOpen"
                @update:open="onEmployeesDialogOpenChange"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Employees
                            <template v-if="employeesDialogTarget">
                                — {{ employeesDialogTarget.code }}
                            </template>
                        </DialogTitle>
                        <DialogDescription>
                            People currently assigned to this position in the
                            default organization.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-3 py-1">
                        <p
                            v-if="employeesDialogLoading"
                            class="text-sm text-muted-foreground"
                        >
                            Loading…
                        </p>
                        <p
                            v-else-if="employeesDialogError"
                            class="text-sm text-destructive"
                        >
                            {{ employeesDialogError }}
                        </p>
                        <p
                            v-else-if="employeesDialogList.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No employees are currently assigned to this
                            position.
                        </p>
                        <template v-else>
                            <InputGroup class="w-full">
                                <InputGroupAddon align="inline-start">
                                    <Search
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </InputGroupAddon>
                                <InputGroupInput
                                    v-model="employeesDialogSearch"
                                    type="search"
                                    placeholder="Search by name or ID…"
                                    autocomplete="off"
                                    aria-label="Filter employees by name or company ID"
                                />
                            </InputGroup>
                            <p
                                v-if="employeesDialogFilteredList.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No employees match your search.
                            </p>
                            <ScrollArea v-else class="h-[min(20rem,45vh)] pr-3">
                                <ul class="divide-y divide-border/60 pr-1">
                                    <li
                                        v-for="emp in employeesDialogFilteredList"
                                        :key="emp.id"
                                        class="py-2 text-sm first:pt-0 last:pb-0"
                                    >
                                        <div
                                            class="font-medium text-foreground"
                                        >
                                            {{ emp.display_name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ emp.id_number }}
                                        </div>
                                    </li>
                                </ul>
                            </ScrollArea>
                        </template>
                    </div>

                    <DialogFooter class="gap-2 sm:justify-end sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeEmployeesForPositionDialog"
                        >
                            Cancel
                        </Button>
                        <Button type="button" :as-child="true">
                            <a
                                class="inline-flex items-center justify-center"
                                :href="employeesViewAllUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View All
                            </a>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
