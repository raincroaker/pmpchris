<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, PaginationState, Updater } from '@tanstack/vue-table';
import { Eye, MoreHorizontal, Pencil, Search } from 'lucide-vue-next';
import { computed, h, onUnmounted, ref, watch } from 'vue';
import HrisColumnFilterPopover from '@/components/hris/HrisColumnFilterPopover.vue';
import HrisServerTablePagination from '@/components/hris/HrisServerTablePagination.vue';
import HrisTanStackTable from '@/components/hris/HrisTanStackTable.vue';
import HrisUnitSelectTriggerLabel from '@/components/hris/HrisUnitSelectTriggerLabel.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useDebouncedSearchInput } from '@/composables/useDebouncedSearchInput';
import AppLayout from '@/layouts/AppLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import AdminUsersAssignedRolesHeader from '@/pages/Admin/AdminUsersAssignedRolesHeader.vue';
import AdminUsersCountColumnHeader from '@/pages/Admin/AdminUsersCountColumnHeader.vue';
import AdminUsersRoleColumnHeader from '@/pages/Admin/AdminUsersRoleColumnHeader.vue';
import type {
    AdminAccountStatusFilter,
    AdminBranchOption,
    AdminEmploymentStateFilter,
    AdminRoleFilterOption,
    AdminRoleFilters,
    AdminRoleRow,
    AdminRolesPaginator,
    AdminUnitFilterOption,
    AdminUserRow,
    AdminUsersPaginator,
} from '@/pages/Admin/adminUsersTypes';
import AdminUsersUserColumnHeader from '@/pages/Admin/AdminUsersUserColumnHeader.vue';
import {
    EMPLOYMENT_STATUS_LABEL,
    EMPLOYMENT_STATUS_VALUES,
    employmentStatusBadgeClass,
} from '@/pages/Employees/employmentStatusConstants';
import type { EmploymentStatusApi } from '@/pages/Employees/employmentStatusConstants';
import { users as adminUsers } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    roles: AdminRolesPaginator;
    users: AdminUsersPaginator;
    roleFilterOptions: AdminRoleFilterOption[];
    unitFilterOptions: AdminUnitFilterOption[];
    branchOptions: AdminBranchOption[];
    organization: { id: number; code: string; name: string } | null;
    branchScope: { id: number; code: string; name: string } | null;
    /** When false, listings and edit role picker omit `super_admin` (handled server-side too). */
    viewerIsSuperAdmin: boolean;
    /** True when current viewer can modify role assignments. */
    viewerCanManageRoles: boolean;
    /** True when current viewer can access Active/Inactive employment filters. */
    viewerCanUseEmploymentStateFilter: boolean;
    filters: AdminRoleFilters;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users & Roles', href: adminUsers() },
];

/** Lets Dialog leave animations finish before clearing row refs (avoids “pop”). */
const DIALOG_LEAVE_MS = 220;

const viewUserDialogOpen = ref(false);
const viewUserTarget = ref<AdminUserRow | null>(null);
let viewUserDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;

const editUserDialogOpen = ref(false);
const editUserTarget = ref<AdminUserRow | null>(null);
const editUserName = ref('');
const editUserEmail = ref('');
const editUserSelectedRoleId = ref<number | null>(null);
const editUserInitialRoleId = ref<number | null>(null);
const editUserSelectedBranchIds = ref<number[]>([]);
const editUserPassword = ref('');
const editUserPasswordConfirmation = ref('');
const editUserAttemptedSave = ref(false);
let editUserDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;

type AvailabilityStatus =
    | 'idle'
    | 'checking'
    | 'available'
    | 'taken'
    | 'invalid';
type AvailabilityState = {
    status: AvailabilityStatus;
    message: string;
};

const editUserEmailAvailability = ref<AvailabilityState>({
    status: 'idle',
    message: '',
});
let editUserEmailCheckTimer: ReturnType<typeof setTimeout> | undefined;
let editUserEmailAbortController: AbortController | undefined;

const editUserEmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const viewRoleUsersDialogOpen = ref(false);
const viewRoleTarget = ref<AdminRoleRow | null>(null);
const viewRoleUsersSearch = ref('');
let viewRoleUsersDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;

const editRoleDialogOpen = ref(false);
const editRoleTarget = ref<AdminRoleRow | null>(null);
const editRoleName = ref('');
const editRoleDescription = ref('');
let editRoleDialogClearTimeout: ReturnType<typeof setTimeout> | undefined;

const accountChipOptions: Array<{
    value: AdminAccountStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All' },
    { value: 'has_account', label: 'Has account' },
    { value: 'no_account', label: 'No account' },
];

const employmentStateChipOptions: Array<{
    value: AdminEmploymentStateFilter;
    label: string;
}> = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const inactiveStatusFilterOptions = EMPLOYMENT_STATUS_VALUES.filter(
    (value): value is Exclude<EmploymentStatusApi, 'active'> => value !== 'active',
).map((value) => ({
    value,
    label: EMPLOYMENT_STATUS_LABEL[value],
}));

function employmentStateChipClass(
    value: AdminEmploymentStateFilter,
): string {
    const selected = props.filters.employment_state === value;
    if (!selected) {
        return '';
    }

    if (value === 'active') {
        return 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 hover:text-white';
    }

    return 'border-amber-600 bg-amber-600 text-white hover:bg-amber-700 hover:text-white';
}

function accountStatusChipClass(value: AdminAccountStatusFilter): string {
    const selected = (props.filters.account_status ?? 'all') === value;
    if (!selected) {
        return '';
    }

    return 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground';
}

/** Matches server filter: linked employee record on the user (employee_id). */
function accountStatusForUser(row: AdminUserRow): 'with_account' | 'without_account' {
    return row.has_account ? 'with_account' : 'without_account';
}

const filteredRoleUsers = computed(() => {
    const role = viewRoleTarget.value;
    if (role === null) {
        return [];
    }

    const needle = viewRoleUsersSearch.value.trim().toLowerCase();
    if (needle === '') {
        return role.users;
    }

    return role.users.filter(
        (user) =>
            user.name.toLowerCase().includes(needle) ||
            user.email.toLowerCase().includes(needle),
    );
});

function openViewUserDialog(row: AdminUserRow): void {
    if (viewUserDialogClearTimeout !== undefined) {
        clearTimeout(viewUserDialogClearTimeout);
        viewUserDialogClearTimeout = undefined;
    }

    viewUserTarget.value = row;
    viewUserDialogOpen.value = true;
}

function onViewUserDialogOpenChange(open: boolean): void {
    if (open) {
        if (viewUserDialogClearTimeout !== undefined) {
            clearTimeout(viewUserDialogClearTimeout);
            viewUserDialogClearTimeout = undefined;
        }
        viewUserDialogOpen.value = true;

        return;
    }

    viewUserDialogOpen.value = false;
    if (viewUserDialogClearTimeout !== undefined) {
        clearTimeout(viewUserDialogClearTimeout);
    }

    viewUserDialogClearTimeout = setTimeout(() => {
        viewUserTarget.value = null;
        viewUserDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function openEditUserDialog(row: AdminUserRow): void {
    if (editUserDialogClearTimeout !== undefined) {
        clearTimeout(editUserDialogClearTimeout);
        editUserDialogClearTimeout = undefined;
    }

    editUserTarget.value = row;
    editUserName.value = row.name;
    editUserEmail.value = row.has_account ? row.email : '';
    editUserSelectedRoleId.value =
        preferredRoleIdForUser(row) ?? defaultEmployeeRoleId.value;
    editUserInitialRoleId.value = preferredRoleIdForUser(row);
    editUserSelectedBranchIds.value = row.assigned_branches.map(
        (branch) => branch.id,
    );
    editUserPassword.value = '';
    editUserPasswordConfirmation.value = '';
    editUserAttemptedSave.value = false;
    editUserEmailAvailability.value = { status: 'idle', message: '' };
    editUserDialogOpen.value = true;
}

function onEditUserDialogOpenChange(open: boolean): void {
    if (open) {
        if (editUserDialogClearTimeout !== undefined) {
            clearTimeout(editUserDialogClearTimeout);
            editUserDialogClearTimeout = undefined;
        }
        editUserDialogOpen.value = true;

        return;
    }

    editUserDialogOpen.value = false;
    editUserEmailAbortController?.abort();
    if (editUserEmailCheckTimer !== undefined) {
        clearTimeout(editUserEmailCheckTimer);
        editUserEmailCheckTimer = undefined;
    }
    if (editUserDialogClearTimeout !== undefined) {
        clearTimeout(editUserDialogClearTimeout);
    }

    editUserDialogClearTimeout = setTimeout(() => {
        editUserTarget.value = null;
        editUserName.value = '';
        editUserEmail.value = '';
        editUserSelectedRoleId.value = null;
        editUserInitialRoleId.value = null;
        editUserSelectedBranchIds.value = [];
        editUserPassword.value = '';
        editUserPasswordConfirmation.value = '';
        editUserAttemptedSave.value = false;
        editUserEmailAvailability.value = { status: 'idle', message: '' };
        editUserDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function submitEditUserDialog(): void {
    const row = editUserTarget.value;
    if (row === null) {
        return;
    }
    editUserAttemptedSave.value = true;

    if (isEditUserSaveDisabled.value) {
        return;
    }

    const payload: {
        email: string;
        role_id?: number | null;
        branch_ids?: number[];
        password?: string;
        password_confirmation?: string;
    } = {
        email: editUserEmail.value.trim(),
    };

    if (
        props.viewerCanManageRoles &&
        editUserSelectedRoleId.value !== editUserInitialRoleId.value
    ) {
        payload.role_id = editUserSelectedRoleId.value;
    }

    if (props.viewerCanManageRoles && isHrManagerSelected.value) {
        payload.branch_ids = editUserSelectedBranchIds.value;
    }

    if (isEditNoAccountFlow.value || editUserPassword.value.trim() !== '') {
        payload.password = editUserPassword.value;
        payload.password_confirmation = editUserPasswordConfirmation.value;
    }

    const endpoint =
        row.user_id !== null
            ? `/admin/users/${row.user_id}`
            : `/admin/users/no-account/${row.id}`;
    router.patch(endpoint, payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            appToast.success('User updated.');
            onEditUserDialogOpenChange(false);
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            const message = Array.isArray(first) ? first[0] : first;

            appToast.error(
                typeof message === 'string' && message !== ''
                    ? message
                    : 'Could not update user.',
            );
        },
    });
}

const roleOptionsForEdit = computed(() =>
    props.roleFilterOptions.map((role) => ({
        id: role.id,
        code: role.code,
        name: role.name,
    })),
);

const defaultEmployeeRoleId = computed(
    () =>
        props.roleFilterOptions.find((role) => role.code === 'employee')?.id ??
        null,
);

const isEditNoAccountFlow = computed(
    () => editUserTarget.value?.has_account === false,
);

const selectedRoleForEdit = computed(
    () =>
        roleOptionsForEdit.value.find(
            (role) => role.id === editUserSelectedRoleId.value,
        ) ?? null,
);

const isHrManagerSelected = computed(
    () => selectedRoleForEdit.value?.code === 'hr_manager',
);

const isViewerRoleManagerRestricted = computed(() => !props.viewerCanManageRoles);

const editUserPasswordPolicyChecks = computed(() => {
    const p = editUserPassword.value;

    return {
        minLength: p.length >= 8,
        hasUpper: /[A-Z]/.test(p),
        hasLower: /[a-z]/.test(p),
        hasDigit: /\d/.test(p),
    };
});

const editUserPasswordMeetsPolicy = computed(() => {
    const checks = editUserPasswordPolicyChecks.value;

    return (
        checks.minLength && checks.hasUpper && checks.hasLower && checks.hasDigit
    );
});

const editUserPasswordRequiredAndMissing = computed(
    () => isEditNoAccountFlow.value && editUserPassword.value.trim() === '',
);

const isEditUserSaveDisabled = computed(
    () =>
        (isHrManagerSelected.value &&
            editUserSelectedBranchIds.value.length === 0) ||
        editUserEmailAvailability.value.status === 'checking' ||
        editUserEmailAvailability.value.status === 'taken' ||
        editUserEmailAvailability.value.status === 'invalid' ||
        (isEditNoAccountFlow.value || editUserPassword.value.trim() !== '') &&
            !editUserPasswordMeetsPolicy.value ||
        editUserPasswordConfirmationInvalid.value ||
        (editUserAttemptedSave.value && editUserPasswordRequiredAndMissing.value),
);

const editUserPasswordConfirmationInvalid = computed(() => {
    if (editUserPassword.value === editUserPasswordConfirmation.value) {
        return false;
    }

    if (editUserAttemptedSave.value) {
        return true;
    }

    return editUserPasswordConfirmation.value.trim() !== '';
});

const editUserEmailAvailabilityClass = computed(() => {
    return (
        {
            idle: 'text-muted-foreground',
            checking: 'text-muted-foreground',
            available: 'text-green-600 dark:text-green-400',
            taken: 'text-destructive',
            invalid: 'text-destructive',
        } as const
    )[editUserEmailAvailability.value.status];
});

function userRoleBadgeClass(roleCode: string): string {
    return (
        (
            {
                super_admin: 'border-red-200 bg-red-50 text-red-700',
                hr_head: 'border-blue-200 bg-blue-50 text-blue-700',
                hr_manager: 'border-amber-200 bg-amber-50 text-amber-700',
                employee: 'border-slate-200 bg-slate-50 text-slate-700',
            } as const
        )[roleCode] ?? 'border-border/60 bg-muted text-muted-foreground'
    );
}

function preferredRoleIdForUser(user: AdminUserRow): number | null {
    const preference = [
        'hr_head',
        'hr_manager',
        'super_admin',
        'employee',
    ];

    for (const code of preference) {
        const role = user.roles.find((item) => item.code === code);
        if (role !== undefined) {
            return role.id;
        }
    }

    return user.roles[0]?.id ?? null;
}

function onEditUserRoleChange(value: unknown): void {
    if (value === null) {
        editUserSelectedRoleId.value = null;
        return;
    }

    const parsed = Number.parseInt(String(value), 10);
    editUserSelectedRoleId.value = Number.isNaN(parsed) ? null : parsed;

    if (!isHrManagerSelected.value) {
        editUserSelectedBranchIds.value = [];
    }
}

function toggleEditUserBranch(branchId: number): void {
    if (!isHrManagerSelected.value) {
        return;
    }

    if (editUserSelectedBranchIds.value.includes(branchId)) {
        editUserSelectedBranchIds.value =
            editUserSelectedBranchIds.value.filter((id) => id !== branchId);
    } else {
        editUserSelectedBranchIds.value = [
            ...editUserSelectedBranchIds.value,
            branchId,
        ];
    }
}

function openViewRoleUsersDialog(row: AdminRoleRow): void {
    if (viewRoleUsersDialogClearTimeout !== undefined) {
        clearTimeout(viewRoleUsersDialogClearTimeout);
        viewRoleUsersDialogClearTimeout = undefined;
    }

    viewRoleTarget.value = row;
    viewRoleUsersSearch.value = '';
    viewRoleUsersDialogOpen.value = true;
}

function onViewRoleUsersDialogOpenChange(open: boolean): void {
    if (open) {
        if (viewRoleUsersDialogClearTimeout !== undefined) {
            clearTimeout(viewRoleUsersDialogClearTimeout);
            viewRoleUsersDialogClearTimeout = undefined;
        }
        viewRoleUsersDialogOpen.value = true;

        return;
    }

    viewRoleUsersDialogOpen.value = false;
    if (viewRoleUsersDialogClearTimeout !== undefined) {
        clearTimeout(viewRoleUsersDialogClearTimeout);
    }

    viewRoleUsersDialogClearTimeout = setTimeout(() => {
        viewRoleTarget.value = null;
        viewRoleUsersSearch.value = '';
        viewRoleUsersDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function openEditRoleDialog(row: AdminRoleRow): void {
    if (editRoleDialogClearTimeout !== undefined) {
        clearTimeout(editRoleDialogClearTimeout);
        editRoleDialogClearTimeout = undefined;
    }

    editRoleTarget.value = row;
    editRoleName.value = row.name;
    editRoleDescription.value = row.description ?? '';
    editRoleDialogOpen.value = true;
}

function onEditRoleDialogOpenChange(open: boolean): void {
    if (open) {
        if (editRoleDialogClearTimeout !== undefined) {
            clearTimeout(editRoleDialogClearTimeout);
            editRoleDialogClearTimeout = undefined;
        }
        editRoleDialogOpen.value = true;

        return;
    }

    editRoleDialogOpen.value = false;
    if (editRoleDialogClearTimeout !== undefined) {
        clearTimeout(editRoleDialogClearTimeout);
    }

    editRoleDialogClearTimeout = setTimeout(() => {
        editRoleTarget.value = null;
        editRoleName.value = '';
        editRoleDescription.value = '';
        editRoleDialogClearTimeout = undefined;
    }, DIALOG_LEAVE_MS);
}

function submitEditRoleDialog(): void {
    onEditRoleDialogOpenChange(false);
}

function buildQuery(
    overrides: Partial<{
        view: AdminRoleFilters['view'];
        search: string;
        sort: AdminRoleFilters['sort'];
        direction: AdminRoleFilters['direction'];
        per_page: number;
        page: number;
        role_id: number | null;
        org_scope: AdminRoleFilters['org_scope'];
        unit_id: AdminRoleFilters['unit_id'];
        account_status: AdminRoleFilters['account_status'];
        employment_state: AdminRoleFilters['employment_state'];
        employment_status: AdminRoleFilters['employment_status'];
    }> = {},
): Record<string, string | number> {
    const f: AdminRoleFilters = { ...props.filters, ...overrides };
    const page =
        overrides.page !== undefined
            ? overrides.page
            : activePaginator.value.current_page;
    const q: Record<string, string | number> = {
        view: f.view,
        sort: f.sort,
        direction: f.direction,
        per_page: f.per_page,
        page,
    };
    if (f.search.trim() !== '') {
        q.search = f.search.trim();
    }
    if (f.view === 'users' && f.role_id !== null) {
        q.role_id = f.role_id;
    }
    if (f.view === 'users' && f.org_scope !== null) {
        q.org_scope = f.org_scope;
    }
    if (f.view === 'users' && f.unit_id !== null) {
        q.unit_id = f.unit_id === 'unassigned' ? 'unassigned' : f.unit_id;
    }
    if (f.view === 'users' && f.account_status !== 'all') {
        q.account_status = f.account_status;
    }
    if (f.view === 'users') {
        q.employment_state = f.employment_state;
    }
    if (f.view === 'users' && f.employment_status !== null) {
        q.employment_status = f.employment_status;
    }

    return q;
}

function applyQuery(
    overrides: Partial<{
        view: AdminRoleFilters['view'];
        search: string;
        sort: AdminRoleFilters['sort'];
        direction: AdminRoleFilters['direction'];
        per_page: number;
        page: number;
        role_id: number | null;
        org_scope: AdminRoleFilters['org_scope'];
        unit_id: AdminRoleFilters['unit_id'];
        account_status: AdminRoleFilters['account_status'];
        employment_state: AdminRoleFilters['employment_state'];
        employment_status: AdminRoleFilters['employment_status'];
    }> = {},
): void {
    router.get(
        adminUsers.url({ query: buildQuery(overrides) }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

const activeView = computed(() => props.filters.view);
const activePaginator = computed(() =>
    activeView.value === 'users' ? props.users : props.roles,
);

function onViewChange(value: string | number): void {
    const normalized = String(value);
    if (normalized !== 'users' && normalized !== 'roles') {
        return;
    }

    if (normalized === activeView.value) {
        return;
    }

    applyQuery({
        view: normalized,
        page: 1,
        role_id: null,
        org_scope: null,
        unit_id: null,
        employment_state: 'active',
        employment_status: null,
        ...(normalized === 'roles' ? { account_status: 'all' } : {}),
    });
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
        pageIndex: activePaginator.value.current_page - 1,
        pageSize: activePaginator.value.per_page,
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

function toggleSort(column: AdminRoleFilters['sort']): void {
    const same = props.filters.sort === column;
    const nextDir = same && props.filters.direction === 'asc' ? 'desc' : 'asc';
    applyQuery({ sort: column, direction: nextDir, page: 1 });
}

const rolesColumns: ColumnDef<AdminRoleRow>[] = [
    {
        id: 'role',
        accessorKey: 'name',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(AdminUsersRoleColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                onSortBy: (field: 'name' | 'code') => toggleSort(field),
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-0.5 py-0.5' }, [
                h(
                    'span',
                    { class: 'font-medium text-foreground' },
                    row.original.name,
                ),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.code,
                ),
            ]),
        enableSorting: false,
    },
    {
        id: 'description',
        accessorKey: 'description',
        meta: {
            headClass: 'min-w-[14rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Description',
            ),
        cell: ({ row }) => {
            const description = row.original.description;
            if (description === null || description.trim() === '') {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                'span',
                {
                    class: 'line-clamp-2 max-w-xl',
                    title: description,
                },
                description,
            );
        },
        enableSorting: false,
    },
    {
        id: 'users_count',
        accessorKey: 'users_count',
        meta: {
            headClass: 'min-w-[8rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(AdminUsersCountColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                label: 'Users',
                onSortBy: () => toggleSort('users_count'),
            }),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'font-medium text-foreground' },
                String(row.original.users_count),
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
            h('div', { class: 'flex justify-center' }, [
                h(
                    DropdownMenu,
                    {},
                    {
                        default: () => [
                            h(
                                DropdownMenuTrigger,
                                { asChild: true },
                                {
                                    default: () =>
                                        h(
                                            Button,
                                            {
                                                type: 'button',
                                                variant: 'ghost',
                                                size: 'icon',
                                                class: 'size-8',
                                                'aria-label': `Actions for role ${row.original.code}`,
                                            },
                                            {
                                                default: () =>
                                                    h(MoreHorizontal, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                            },
                                        ),
                                },
                            ),
                            h(
                                DropdownMenuContent,
                                { align: 'end', class: 'min-w-44' },
                                {
                                    default: () => [
                                        h(
                                            DropdownMenuItem,
                                            {
                                                onClick: () =>
                                                    openViewRoleUsersDialog(
                                                        row.original,
                                                    ),
                                            },
                                            {
                                                default: () => [
                                                    h(Eye, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                                    'View users',
                                                ],
                                            },
                                        ),
                                        h(
                                            DropdownMenuItem,
                                            {
                                                onClick: () =>
                                                    openEditRoleDialog(
                                                        row.original,
                                                    ),
                                            },
                                            {
                                                default: () => [
                                                    h(Pencil, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                                    'Edit role',
                                                ],
                                            },
                                        ),
                                    ],
                                },
                            ),
                        ],
                    },
                ),
            ]),
        enableSorting: false,
    },
];

const usersColumns: ColumnDef<AdminUserRow>[] = [
    {
        id: 'user',
        accessorKey: 'name',
        meta: {
            headClass: 'min-w-[12rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(AdminUsersUserColumnHeader, {
                sort: props.filters.sort,
                direction: props.filters.direction,
                orgScope: props.filters.org_scope,
                onSortBy: (field: 'name' | 'code') => toggleSort(field),
                'onUpdate:orgScope': (value: AdminRoleFilters['org_scope']) => {
                    applyQuery({ org_scope: value, page: 1 });
                },
            }),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-0.5 py-0.5' }, [
                h('div', { class: 'inline-flex items-center gap-2' }, [
                    h(
                        'span',
                        { class: 'font-medium text-foreground' },
                        row.original.name,
                    ),
                    row.original.scope_summary === 'Org-wide'
                        ? h(
                              Badge,
                              {
                                  variant: 'secondary',
                                  class: 'rounded-full border border-border/60 text-[11px] font-medium',
                              },
                              () => 'Org-wide',
                          )
                        : null,
                ]),
                h(
                    'span',
                    { class: 'text-xs text-muted-foreground' },
                    row.original.email,
                ),
            ]),
        enableSorting: false,
    },
    {
        id: 'employee_number',
        accessorKey: 'employee_number',
        meta: {
            headClass: 'min-w-[8rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Employee ID',
            ),
        cell: ({ row }) => {
            if (row.original.employee_number === null) {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                'span',
                { class: 'font-medium text-foreground' },
                row.original.employee_number,
            );
        },
        enableSorting: false,
    },
    {
        id: 'roles',
        accessorKey: 'roles',
        meta: {
            headClass: 'min-w-[18rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(AdminUsersAssignedRolesHeader, {
                modelValue: props.filters.role_id,
                options: props.roleFilterOptions,
                'onUpdate:modelValue': (value: number | null) => {
                    applyQuery({ role_id: value, page: 1 });
                },
            }),
        cell: ({ row }) => {
            const preview = row.original.roles.slice(0, 3);
            const extra = row.original.roles.length - preview.length;

            if (preview.length === 0) {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                'div',
                { class: 'flex flex-wrap items-center gap-1 py-0.5' },
                [
                    ...preview.map((role) =>
                        h(
                            Badge,
                            {
                                variant: 'outline',
                                class: `rounded-full text-xs font-medium ${userRoleBadgeClass(role.code)}`,
                            },
                            () => role.name,
                        ),
                    ),
                    extra > 0
                        ? h(
                              'span',
                              { class: 'text-xs text-muted-foreground' },
                              `+${extra} more`,
                          )
                        : null,
                ],
            );
        },
        enableSorting: false,
    },
    {
        id: 'employment_status',
        accessorKey: 'employment_status',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            props.filters.employment_state === 'inactive'
                ? h(HrisColumnFilterPopover, {
                      label: 'Status',
                      triggerAriaLabel:
                          props.filters.employment_status === null
                              ? 'Status filter: all inactive statuses. Open to select a specific inactive status.'
                              : `Status filter: ${EMPLOYMENT_STATUS_LABEL[props.filters.employment_status]}. Open to change status filter.`,
                      modelValue: props.filters.employment_status,
                      options: inactiveStatusFilterOptions,
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
                  })
                : h(
                      'span',
                      { class: 'font-medium text-muted-foreground' },
                      'Status',
                  ),
        cell: ({ row }) => {
            if (row.original.employment_status === null) {
                return h('span', { class: 'text-muted-foreground' }, '—');
            }

            return h(
                Badge,
                {
                    variant: 'default',
                    class: employmentStatusBadgeClass(row.original.employment_status),
                },
                () => EMPLOYMENT_STATUS_LABEL[row.original.employment_status],
            );
        },
        enableSorting: false,
    },
    {
        id: 'account_status',
        accessorKey: 'email',
        meta: {
            headClass: 'min-w-[10rem]',
            cellClass: 'align-middle',
        },
        header: () =>
            h(
                'span',
                { class: 'font-medium text-muted-foreground' },
                'Account',
            ),
        cell: ({ row }) => {
            const status = accountStatusForUser(row.original);

            if (status === 'with_account') {
                return h(
                    Badge,
                    {
                        variant: 'outline',
                        class: 'rounded-full border-emerald-200 bg-emerald-50 text-emerald-700',
                    },
                    () => 'Has account',
                );
            }

            return h(
                Badge,
                {
                    variant: 'outline',
                    class: 'rounded-full border-amber-200 bg-amber-50 text-amber-700',
                },
                () => 'No account',
            );
        },
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
            h('div', { class: 'flex justify-center' }, [
                h(
                    DropdownMenu,
                    {},
                    {
                        default: () => [
                            h(
                                DropdownMenuTrigger,
                                { asChild: true },
                                {
                                    default: () =>
                                        h(
                                            Button,
                                            {
                                                type: 'button',
                                                variant: 'ghost',
                                                size: 'icon',
                                                class: 'size-8',
                                                'aria-label': `Actions for user ${row.original.name}`,
                                            },
                                            {
                                                default: () =>
                                                    h(MoreHorizontal, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                            },
                                        ),
                                },
                            ),
                            h(
                                DropdownMenuContent,
                                { align: 'end', class: 'min-w-52' },
                                {
                                    default: () => [
                                        h(
                                            DropdownMenuItem,
                                            {
                                                onClick: () =>
                                                    openViewUserDialog(
                                                        row.original,
                                                    ),
                                            },
                                            {
                                                default: () => [
                                                    h(Eye, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                                    'View user',
                                                ],
                                            },
                                        ),
                                        h(
                                            DropdownMenuItem,
                                            {
                                                onClick: () =>
                                                    openEditUserDialog(
                                                        row.original,
                                                    ),
                                            },
                                            {
                                                default: () => [
                                                    h(Pencil, {
                                                        class: 'size-4',
                                                        'aria-hidden': true,
                                                    }),
                                                    row.original.has_account
                                                        ? 'Edit user'
                                                        : 'Create account',
                                                ],
                                            },
                                        ),
                                    ],
                                },
                            ),
                        ],
                    },
                ),
            ]),
        enableSorting: false,
    },
];

const rolesTable = useVueTable({
    get data() {
        return props.roles.data;
    },
    columns: rolesColumns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.roles.last_page,
    rowCount: props.roles.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.roles.current_page - 1,
                pageSize: props.roles.per_page,
            };
        },
    },
});

const usersTable = useVueTable({
    get data() {
        return props.users.data;
    },
    columns: usersColumns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: props.users.last_page,
    rowCount: props.users.total,
    onPaginationChange,
    state: {
        get pagination() {
            return {
                pageIndex: props.users.current_page - 1,
                pageSize: props.users.per_page,
            };
        },
    },
});

const adminUsersToolbarUnitFilterOptions = computed(() => {
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

const searchPlaceholder = computed(() =>
    activeView.value === 'users'
        ? 'Search user, email, or role…'
        : 'Search role, description, user, or email…',
);

onUnmounted(() => {
    if (viewUserDialogClearTimeout !== undefined) {
        clearTimeout(viewUserDialogClearTimeout);
    }
    if (editUserDialogClearTimeout !== undefined) {
        clearTimeout(editUserDialogClearTimeout);
    }
    if (viewRoleUsersDialogClearTimeout !== undefined) {
        clearTimeout(viewRoleUsersDialogClearTimeout);
    }
    if (editRoleDialogClearTimeout !== undefined) {
        clearTimeout(editRoleDialogClearTimeout);
    }
    if (editUserEmailCheckTimer !== undefined) {
        clearTimeout(editUserEmailCheckTimer);
    }
    editUserEmailAbortController?.abort();
});

watch(
    () => editUserEmail.value,
    (nextEmail) => {
        if (!editUserDialogOpen.value || editUserTarget.value === null) {
            return;
        }

        if (editUserEmailCheckTimer !== undefined) {
            clearTimeout(editUserEmailCheckTimer);
            editUserEmailCheckTimer = undefined;
        }

        const trimmed = nextEmail.trim();
        if (trimmed === '') {
            editUserEmailAbortController?.abort();
            editUserEmailAvailability.value = { status: 'idle', message: '' };
            return;
        }

        if (!editUserEmailPattern.test(trimmed)) {
            editUserEmailAbortController?.abort();
            editUserEmailAvailability.value = {
                status: 'invalid',
                message: 'Enter a valid email address.',
            };
            return;
        }

        editUserEmailCheckTimer = setTimeout(async () => {
            editUserEmailAbortController?.abort();
            editUserEmailAbortController = new AbortController();
            editUserEmailAvailability.value = {
                status: 'checking',
                message: 'Checking availability...',
            };

            try {
                const params = new URLSearchParams({
                    email: trimmed,
                });
                if (editUserTarget.value?.user_id !== null) {
                    params.set(
                        'ignore_user_id',
                        String(editUserTarget.value.user_id),
                    );
                }
                const response = await fetch(
                    `/admin/users/check-availability?${params.toString()}`,
                    {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                        },
                        signal: editUserEmailAbortController.signal,
                    },
                );

                if (!response.ok) {
                    editUserEmailAvailability.value = {
                        status: 'invalid',
                        message: 'Could not verify right now.',
                    };
                    return;
                }

                const data = (await response.json()) as {
                    email?: AvailabilityState;
                };

                if (data.email !== undefined) {
                    editUserEmailAvailability.value = data.email;
                    return;
                }

                editUserEmailAvailability.value = {
                    status: 'invalid',
                    message: 'Could not verify right now.',
                };
            } catch (error) {
                if ((error as { name?: string }).name === 'AbortError') {
                    return;
                }

                editUserEmailAvailability.value = {
                    status: 'invalid',
                    message: 'Could not verify right now.',
                };
            } finally {
                editUserEmailAbortController = undefined;
            }
        }, 350);
    },
);
</script>

<template>
    <Head title="Users & Roles" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Users & Roles
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage user access, role assignments, and scope visibility
                    across branches and organizational units.
                </p>
                <p v-if="organization" class="text-sm text-muted-foreground">
                    {{ organization.name }}
                    <span class="text-muted-foreground/80"
                        >({{ organization.code }})</span
                    >
                </p>
                <p
                    v-if="branchScope"
                    class="text-sm text-muted-foreground"
                >
                    Showing users for
                    <span class="font-medium text-foreground">{{
                        branchScope.name
                    }}</span>
                    <span class="text-muted-foreground/80"
                        >({{ branchScope.code }})</span
                    >.
                </p>
            </div>
            <div class="flex items-center justify-start">
                <Tabs
                    :model-value="activeView"
                    @update:model-value="onViewChange"
                >
                    <TabsList>
                        <TabsTrigger value="users"> Users </TabsTrigger>
                        <TabsTrigger value="roles"> Roles </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
            <div class="flex flex-col gap-4">
                <div class="w-full max-w-md">
                    <InputGroup class="max-w-md">
                        <InputGroupAddon align="inline-start">
                            <Search
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="admin_users_search"
                            :model-value="localSearch"
                            type="search"
                            :placeholder="searchPlaceholder"
                            autocomplete="off"
                            :aria-label="searchPlaceholder"
                            @update:model-value="onSearchUpdate"
                            @keyup="onSearchKeyup"
                            @change="onSearchCommit"
                            @search="onSearchCommit"
                        />
                    </InputGroup>
                </div>
                <div
                    v-if="activeView === 'users'"
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <Button
                            v-if="props.viewerCanUseEmploymentStateFilter"
                            v-for="opt in employmentStateChipOptions"
                            :key="`state-${opt.value}`"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 shrink-0 rounded-full px-3"
                            :class="employmentStateChipClass(opt.value)"
                            :aria-pressed="
                                (props.filters.employment_state ?? 'active') ===
                                opt.value
                            "
                            @click="
                                applyQuery({
                                    employment_state: opt.value,
                                    employment_status: null,
                                    unit_id:
                                        opt.value === 'inactive'
                                            ? null
                                            : props.filters.unit_id,
                                    page: 1,
                                })
                            "
                        >
                            {{ opt.label }}
                        </Button>
                        <Button
                            v-for="opt in accountChipOptions"
                            :key="opt.value"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 shrink-0 rounded-full px-3"
                            :class="accountStatusChipClass(opt.value)"
                            :aria-pressed="
                                (props.filters.account_status ?? 'all') ===
                                opt.value
                            "
                            @click="
                                applyQuery({
                                    account_status: opt.value,
                                    page: 1,
                                })
                            "
                        >
                            {{ opt.label }}
                        </Button>
                    </div>
                    <div
                        v-if="props.filters.employment_state !== 'inactive'"
                        class="w-full sm:w-auto sm:shrink-0"
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
                                            :options="adminUsersToolbarUnitFilterOptions"
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in adminUsersToolbarUnitFilterOptions"
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
                    </div>
                </div>
            </div>

            <div class="w-full">
                <HrisTanStackTable
                    v-if="activeView === 'users'"
                    :table="usersTable"
                    :empty-message="'No users match the current filters.'"
                />
                <HrisTanStackTable
                    v-else
                    :table="rolesTable"
                    :empty-message="'No roles match the current filters.'"
                />

                <HrisServerTablePagination
                    :total="activePaginator.total"
                    :from="activePaginator.from"
                    :to="activePaginator.to"
                    :current-page="activePaginator.current_page"
                    :last-page="activePaginator.last_page"
                    :per-page="activePaginator.per_page"
                    :can-previous-page="
                        (activeView === 'users'
                            ? usersTable
                            : rolesTable
                        ).getCanPreviousPage()
                    "
                    :can-next-page="
                        (activeView === 'users'
                            ? usersTable
                            : rolesTable
                        ).getCanNextPage()
                    "
                    @update:per-page="onPerPageChange"
                    @go-first="
                        onPaginationChange({
                            pageIndex: 0,
                            pageSize: activePaginator.per_page,
                        })
                    "
                    @go-prev="
                        (activeView === 'users'
                            ? usersTable
                            : rolesTable
                        ).previousPage()
                    "
                    @go-next="
                        (activeView === 'users'
                            ? usersTable
                            : rolesTable
                        ).nextPage()
                    "
                    @go-last="
                        onPaginationChange({
                            pageIndex: Math.max(
                                activePaginator.last_page - 1,
                                0,
                            ),
                            pageSize: activePaginator.per_page,
                        })
                    "
                />
            </div>

            <Dialog
                :open="viewUserDialogOpen"
                @update:open="onViewUserDialogOpenChange"
            >
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>User details</DialogTitle>
                        <DialogDescription>
                            Read-only profile snapshot for the current branch
                            scope.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-3 py-1 text-sm sm:grid-cols-2">
                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Name
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{ viewUserTarget?.name ?? 'Unknown user' }}
                            </p>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Email
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{ viewUserTarget?.email ?? 'No email' }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Employee ID
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{
                                    viewUserTarget?.employee_number ??
                                    'Not linked'
                                }}
                            </p>
                        </div>

                        <div class="grid gap-1.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Scope
                            </p>
                            <p
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-foreground"
                            >
                                {{
                                    viewUserTarget?.scope_summary ??
                                    'No explicit scope assigned'
                                }}
                            </p>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Roles
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <div
                                    v-if="
                                        viewUserTarget &&
                                        viewUserTarget.roles.length > 0
                                    "
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="role in viewUserTarget.roles"
                                        :key="role.id"
                                        variant="outline"
                                        :class="`rounded-full text-xs font-medium ${userRoleBadgeClass(role.code)}`"
                                    >
                                        {{ role.name }}
                                    </Badge>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">
                                    No roles assigned.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Assigned Branches
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <div
                                    v-if="
                                        viewUserTarget &&
                                        viewUserTarget.assigned_branches
                                            .length > 0
                                    "
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="branch in viewUserTarget.assigned_branches"
                                        :key="`branch-${branch.id}`"
                                        variant="outline"
                                        class="rounded-full text-xs font-medium"
                                    >
                                        {{ branch.name }}
                                        <span
                                            class="ml-1 text-[10px] text-muted-foreground"
                                            >{{ branch.code }}</span
                                        >
                                    </Badge>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">
                                    No branch affiliations.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-1.5 sm:col-span-2">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Assigned Units
                            </p>
                            <div
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2"
                            >
                                <div
                                    v-if="
                                        viewUserTarget &&
                                        viewUserTarget.assigned_units.length > 0
                                    "
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <Badge
                                        v-for="unit in viewUserTarget.assigned_units"
                                        :key="`unit-${unit.id}`"
                                        variant="outline"
                                        class="rounded-full text-xs font-medium"
                                    >
                                        {{ unit.name }}
                                        <span
                                            class="ml-1 text-[10px] text-muted-foreground"
                                            >{{ unit.code }}</span
                                        >
                                    </Badge>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">
                                    No active unit assignments.
                                </p>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="onViewUserDialogOpenChange(false)"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="editUserDialogOpen"
                @update:open="onEditUserDialogOpenChange"
            >
                <DialogContent class="overflow-visible sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                isEditNoAccountFlow
                                    ? 'Create account'
                                    : 'Edit user'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                isEditNoAccountFlow
                                    ? 'Create a login account for this employee and assign access.'
                                    : 'Update account access details and password.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <ScrollArea class="max-h-[70vh] px-1 pr-3">
                        <div class="grid gap-4 px-1 py-2">
                            <div class="grid gap-2">
                                <Label for="edit-user-name">Name</Label>
                                <Input
                                    id="edit-user-name"
                                    v-model="editUserName"
                                    readonly
                                    tabindex="-1"
                                    class="bg-muted/40 text-muted-foreground"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-user-email">Email</Label>
                                <Input
                                    id="edit-user-email"
                                    v-model="editUserEmail"
                                    type="email"
                                    autocomplete="off"
                                    :aria-invalid="
                                        editUserEmailAvailability.status ===
                                            'taken' ||
                                        editUserEmailAvailability.status ===
                                            'invalid'
                                    "
                                />
                                <p
                                    v-if="
                                        editUserEmailAvailability.status !==
                                        'idle'
                                    "
                                    class="text-xs"
                                    :class="editUserEmailAvailabilityClass"
                                >
                                    {{ editUserEmailAvailability.message }}
                                </p>
                            </div>
                            <div
                                v-if="props.viewerCanManageRoles"
                                class="grid w-full gap-2"
                            >
                                <Label for="edit-user-role">Role</Label>
                                <Select
                                    :model-value="
                                        editUserSelectedRoleId !== null
                                            ? String(editUserSelectedRoleId)
                                            : undefined
                                    "
                                    @update:model-value="onEditUserRoleChange"
                                >
                                    <SelectTrigger
                                        id="edit-user-role"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select role"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in roleOptionsForEdit"
                                            :key="role.id"
                                            :value="String(role.id)"
                                        >
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <p
                                v-else-if="isViewerRoleManagerRestricted"
                                class="rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                            >
                                Role assignment can only be changed by HR Head
                                and Super Administrator.
                            </p>
                            <div
                                v-if="
                                    props.viewerCanManageRoles &&
                                    isHrManagerSelected
                                "
                                class="grid gap-2"
                            >
                                <Label>Branches to oversee</Label>
                                <ScrollArea
                                    class="h-36 rounded-md border border-border/70 p-2"
                                >
                                    <div class="space-y-2">
                                        <label
                                            v-for="branch in branchOptions"
                                            :key="`edit-branch-${branch.id}`"
                                            class="flex cursor-pointer items-center gap-2 rounded-sm px-1 py-1 text-sm hover:bg-muted/40"
                                        >
                                            <input
                                                type="checkbox"
                                                class="size-4"
                                                :checked="
                                                    editUserSelectedBranchIds.includes(
                                                        branch.id,
                                                    )
                                                "
                                                @change="
                                                    toggleEditUserBranch(
                                                        branch.id,
                                                    )
                                                "
                                            />
                                            <span
                                                class="font-medium text-foreground"
                                                >{{ branch.name }}</span
                                            >
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{ branch.code }}</span
                                            >
                                        </label>
                                    </div>
                                </ScrollArea>
                                <p class="text-xs text-muted-foreground">
                                    HR Manager scope is branch-based.
                                </p>
                            </div>
                            <p
                                v-else-if="props.viewerCanManageRoles"
                                class="text-xs text-muted-foreground"
                            >
                                Selected role uses org-wide or
                                non-branch-limited scope.
                            </p>
                            <div class="grid gap-2">
                                <Label for="edit-user-password"
                                    >{{
                                        isEditNoAccountFlow
                                            ? 'Password'
                                            : 'New password'
                                    }}</Label
                                >
                                <Input
                                    id="edit-user-password"
                                    v-model="editUserPassword"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="
                                        isEditNoAccountFlow
                                            ? 'Set initial password'
                                            : 'Leave blank to keep current password'
                                    "
                                />
                                <div class="rounded-md border border-border/60 bg-muted/30 p-2">
                                    <p class="text-xs font-medium text-foreground">
                                        Password requirements
                                    </p>
                                    <ul class="mt-1 space-y-1 text-xs">
                                        <li
                                            :class="
                                                editUserPasswordPolicyChecks.minLength
                                                    ? 'text-green-700 dark:text-green-400'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            At least 8 characters
                                        </li>
                                        <li
                                            :class="
                                                editUserPasswordPolicyChecks.hasUpper
                                                    ? 'text-green-700 dark:text-green-400'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            At least one uppercase letter (A-Z)
                                        </li>
                                        <li
                                            :class="
                                                editUserPasswordPolicyChecks.hasLower
                                                    ? 'text-green-700 dark:text-green-400'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            At least one lowercase letter (a-z)
                                        </li>
                                        <li
                                            :class="
                                                editUserPasswordPolicyChecks.hasDigit
                                                    ? 'text-green-700 dark:text-green-400'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            At least one number (0-9)
                                        </li>
                                    </ul>
                                </div>
                                <p
                                    v-if="
                                        editUserAttemptedSave &&
                                        editUserPasswordRequiredAndMissing
                                    "
                                    class="text-xs text-destructive"
                                >
                                    Password is required.
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-user-password-confirmation">
                                    Confirm
                                    {{
                                        isEditNoAccountFlow
                                            ? 'password'
                                            : 'new password'
                                    }}
                                </Label>
                                <Input
                                    id="edit-user-password-confirmation"
                                    v-model="editUserPasswordConfirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    :aria-invalid="
                                        editUserPasswordConfirmationInvalid
                                    "
                                />
                                <p
                                    v-if="editUserPasswordConfirmationInvalid"
                                    class="text-xs text-destructive"
                                >
                                    Password confirmation does not match.
                                </p>
                            </div>
                        </div>
                    </ScrollArea>
                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="onEditUserDialogOpenChange(false)"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            :disabled="isEditUserSaveDisabled"
                            @click="submitEditUserDialog"
                        >
                            {{
                                isEditNoAccountFlow
                                    ? 'Create account'
                                    : 'Save'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="viewRoleUsersDialogOpen"
                @update:open="onViewRoleUsersDialogOpenChange"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Users
                            <template v-if="viewRoleTarget">
                                — {{ viewRoleTarget.code }}
                            </template>
                        </DialogTitle>
                        <DialogDescription>
                            Users currently assigned to this role.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-3 py-1">
                        <InputGroup class="w-full">
                            <InputGroupAddon align="inline-start">
                                <Search
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </InputGroupAddon>
                            <InputGroupInput
                                v-model="viewRoleUsersSearch"
                                type="search"
                                placeholder="Search by name or email…"
                                autocomplete="off"
                                aria-label="Filter users by name or email"
                            />
                        </InputGroup>
                        <p
                            v-if="filteredRoleUsers.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No users match your search.
                        </p>
                        <ScrollArea v-else class="h-[min(20rem,45vh)] pr-3">
                            <ul class="divide-y divide-border/60 pr-1">
                                <li
                                    v-for="user in filteredRoleUsers"
                                    :key="user.id"
                                    class="py-2 text-sm first:pt-0 last:pb-0"
                                >
                                    <div class="font-medium text-foreground">
                                        {{ user.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ user.email }}
                                    </div>
                                </li>
                            </ul>
                        </ScrollArea>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="onViewRoleUsersDialogOpenChange(false)"
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="editRoleDialogOpen"
                @update:open="onEditRoleDialogOpenChange"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit role</DialogTitle>
                        <DialogDescription>
                            UI placeholder only. Save action is not connected
                            yet.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-2">
                        <div class="grid gap-2">
                            <Label for="edit-role-code">Code</Label>
                            <Input
                                id="edit-role-code"
                                :model-value="editRoleTarget?.code ?? ''"
                                readonly
                                tabindex="-1"
                                class="bg-muted/40 text-muted-foreground"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-role-name">Name</Label>
                            <Input
                                id="edit-role-name"
                                v-model="editRoleName"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-role-description"
                                >Description</Label
                            >
                            <Textarea
                                id="edit-role-description"
                                v-model="editRoleDescription"
                                rows="4"
                                class="min-h-24 resize-y"
                            />
                        </div>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="onEditRoleDialogOpenChange(false)"
                        >
                            Cancel
                        </Button>
                        <Button type="button" @click="submitEditRoleDialog">
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
