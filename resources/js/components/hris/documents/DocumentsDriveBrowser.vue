<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    CircleCheck,
    ClipboardCheck,
    Clock,
    Download,
    ExternalLink,
    Globe,
    Info,
    FileSpreadsheet,
    FileText,
    Folder,
    FolderInput,
    FolderOpen,
    FolderPlus,
    LayoutGrid,
    List,
    MoreHorizontal,
    MoreVertical,
    Pencil,
    Presentation,
    Search,
    Share2,
    Shield,
    Star,
    StickyNote,
    Trash2,
    Undo2,
    Tags,
    Upload,
    UserCheck,
    UserPlus,
    Calendar,
    Clock3,
    HardDrive,
    User,
    X,
    XCircle,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import {
    accessModeLabel,
    collectSubtreeItemIds,
    defaultInformationMockForNewItem,
    DOCUMENT_UPLOAD_MAX_BYTES,
    driveFileCanDownload,
    driveFileRequiresAccessRequest,
    driveFileKindLabel,
    findItem,
    formatBytes,
    getBreadcrumbChain,
    getChildren,
    inferKindFromFileName,
    isAllowedDocumentUploadFile,
    matchesOwnershipChip,
    resolveDriveItemAccessMode,
    sortDriveItems,
} from '@/components/hris/documents/documentsDriveHelpers';
import {
    createInitialItems,
    storageHintLabel,
} from '@/components/hris/documents/documentsDriveMocks';
import {
    collectTrashBatchIdsForSelection,
    flattenTrashBatchesForUi,
    loadDriveFromSession,
    loadToolbarPrefs,
    loadTrashBatches,
    moveSubtreeToTrash,
    purgeTrashBatch,
    restoreTrashBatch,
    saveDriveToSession,
    saveToolbarPrefs,
    scopeLabel,
} from '@/components/hris/documents/documentsDriveSession';
import type {
    DocumentsScope,
    DriveRecencyOrder,
    DriveFileItem,
    DriveFolderItem,
    DriveItem,
    DriveMySubmissionStatus,
    DriveOutgoingRequestKind,
    DriveOwnershipChip,
    DriveSortKey,
    DriveSortOrder,
    DriveTypeFilter,
    DriveViewMode,
} from '@/components/hris/documents/documentsDriveTypes';
import HrisIndexToolbar from '@/components/hris/HrisIndexToolbar.vue';
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
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { appToast } from '@/lib/app-toast-client';
import { fetchTeamHrFormUnits } from '@/lib/teamHrFormApi';
import type { TeamHrFormUnit } from '@/lib/teamHrFormApi';
import { cn } from '@/lib/utils';
import { TEAM_DIRECTORY_UNIT_FILTER_OPTIONS } from '@/pages/Leave/teamDirectoryMockUnits';

type DocumentsAdminCan = {
    canViewDocumentAdminViewTeam?: boolean;
    canViewDocumentAdminViewBranch?: boolean;
    canViewDocumentAdminViewCompany?: boolean;
    /** Team leave/overtime HR gate — when true, team-documents unit list is branch-wide. */
    canViewEmployeeTeamLeaveOvertime?: boolean;
};

const props = withDefaults(
    defineProps<{
        /** Required for `variant="drive"` (document library scope). */
        scope?: DocumentsScope;
        variant?: 'drive' | 'trash';
    }>(),
    { variant: 'drive' },
);

const page = usePage<{
    can?: DocumentsAdminCan;
    documents?: {
        teamAssignmentUnitIds: number[];
        teamHeadUnitIds: number[];
    };
}>();

/** `all` or API id string / fallback slug from {@link branchUnitFilterOptions}. */
const teamDocumentUnitFilter = ref<string>('all');

const isTrashView = computed(() => props.variant === 'trash');

const isMyCatalogView = computed(
    () => props.scope === 'my' && !isTrashView.value,
);

/** Workspace branch root id — updates when the navbar branch switcher changes session. */
const chartBranchId = computed(() => page.props.branchContext?.id ?? null);

/** Non-HR users: unit filter is limited to active assignments in the workspace branch shared by the server. */
const restrictTeamDocumentUnitsToMyAssignments = computed(
    () => page.props.can?.canViewEmployeeTeamLeaveOvertime !== true,
);

const teamHeadUnitIdSet = computed(
    () => new Set(page.props.documents?.teamHeadUnitIds ?? []),
);

/**
 * Admin View / upload “authority” for the active library. Team: HR roles (team leave overtime gate)
 * keep branch-wide admin; unit heads only when a single unit is selected and they are head there.
 */
const canViewDocumentAdminViewForActiveScope = computed((): boolean => {
    if (isTrashView.value || isMyCatalogView.value || !props.scope) {
        return false;
    }
    const c = page.props.can;
    if (!c) {
        return false;
    }
    switch (props.scope) {
        case 'team': {
            if (c.canViewDocumentAdminViewTeam !== true) {
                return false;
            }
            if (c.canViewEmployeeTeamLeaveOvertime === true) {
                return true;
            }
            const sel = teamDocumentUnitFilter.value;
            if (sel === 'all') {
                return false;
            }
            const id = Number.parseInt(sel, 10);
            if (!Number.isFinite(id)) {
                return false;
            }

            return teamHeadUnitIdSet.value.has(id);
        }
        case 'branch':
            return c.canViewDocumentAdminViewBranch === true;
        case 'company':
            return c.canViewDocumentAdminViewCompany === true;
        default:
            return false;
    }
});

/**
 * Rename / move / delete / share (files only for share): My catalog is self-service;
 * Team / Branch / Company use the same gate as Admin View.
 */
const canManageDriveMutations = computed((): boolean => {
    if (isTrashView.value) {
        return false;
    }
    if (isMyCatalogView.value) {
        return true;
    }

    return canViewDocumentAdminViewForActiveScope.value;
});

/**
 * Download guard rail by page role/scope. Team/Branch/Company downloads require
 * the same authority gate as Admin View; My documents remains self-service.
 */
const canDownloadDriveFilesForActiveScope = computed((): boolean => {
    if (isTrashView.value) {
        return true;
    }
    if (isMyCatalogView.value) {
        return true;
    }

    return canViewDocumentAdminViewForActiveScope.value;
});

const selectionIncludesFolder = computed((): boolean => {
    for (const id of selectedIds.value) {
        const node = findItem(items.value, id);
        if (node?.type === 'folder') {
            return true;
        }
    }

    return false;
});

/** Team / Branch / Company + documents approval roles (see docs/documents-approval-spec §4). */
const showApprovalQueueModeToggle = computed(
    () => canViewDocumentAdminViewForActiveScope.value,
);

const approvalQueueMode = ref(false);

const restrictViewToQueuesOnly = computed(
    () => showApprovalQueueModeToggle.value && approvalQueueMode.value,
);

function toggleAdminView(): void {
    const next = !approvalQueueMode.value;
    approvalQueueMode.value = next;
    if (!next) {
        requestTypeView.value = 'library';
    }
}

const isTeamDriveScope = computed(
    () => props.scope === 'team' && !isTrashView.value,
);

const branchUnits = ref<TeamHrFormUnit[]>([]);
const branchUnitsLoadError = ref<string | null>(null);
const branchUnitsLoading = ref(false);

async function loadTeamDocumentsUnits(): Promise<void> {
    if (props.scope !== 'team' || isTrashView.value) {
        return;
    }

    branchUnitsLoadError.value = null;
    branchUnitsLoading.value = true;
    try {
        const { units } = await fetchTeamHrFormUnits();
        branchUnits.value = units;
    } catch {
        branchUnitsLoadError.value =
            'Could not load units for this branch.';
        branchUnits.value = [];
    } finally {
        branchUnitsLoading.value = false;
    }
}

const branchUnitFilterOptions = computed(() => {
    const base: Array<{
        value: string;
        label: string;
        code?: string | null;
    }> = [{ value: 'all', label: 'All units' }];

    const raw = branchUnits.value;
    const allowIds = page.props.documents?.teamAssignmentUnitIds ?? [];
    const onlyMyAssignments = restrictTeamDocumentUnitsToMyAssignments.value;

    const resolved =
        raw.length > 0
            ? onlyMyAssignments
                ? raw.filter((u) => allowIds.includes(u.id))
                : raw
            : [];

    if (resolved.length > 0) {
        return [
            ...base,
            ...resolved.map((u) => ({
                value: String(u.id),
                label: u.name,
                code: u.code,
            })),
        ];
    }

    if (raw.length === 0 && !onlyMyAssignments) {
        return [...base, ...TEAM_DIRECTORY_UNIT_FILTER_OPTIONS];
    }

    return base;
});

function itemMatchesTeamDocumentsUnitFilter(
    item: DriveItem,
    filterValue: string,
    options: Array<{ value: string; label: string }>,
): boolean {
    if (item.type === 'folder') {
        return true;
    }
    if (filterValue === 'all') {
        return true;
    }
    const selectedOpt = options.find((o) => o.value === filterValue);
    if (!selectedOpt) {
        return true;
    }
    const file = item as DriveFileItem;
    const mine = file.mockTeamUnitLabel?.trim();
    if (!mine) {
        return false;
    }

    return mine === selectedOpt.label;
}

function resolveDriveFileTeamUnitId(file: DriveFileItem): number | null {
    if (Number.isFinite(file.mockTeamUnitId)) {
        return file.mockTeamUnitId ?? null;
    }

    const label = file.mockTeamUnitLabel?.trim();
    if (!label) {
        return null;
    }

    const matched = branchUnits.value.find((unit) => unit.name === label);

    return matched ? matched.id : null;
}

const shareDialogTargetFiles = computed<DriveFileItem[]>(() =>
    shareTargetItemIds.value
        .map((id) => findItem(items.value, id))
        .filter((node): node is DriveFileItem => node?.type === 'file'),
);

const branchUnitById = computed(() => {
    const map = new Map<number, TeamHrFormUnit>();
    for (const unit of branchUnits.value) {
        map.set(unit.id, unit);
    }

    return map;
});

function collectDescendantUnitIds(unitId: number): number[] {
    const result: number[] = [];
    const queue = [unitId];

    while (queue.length > 0) {
        const current = queue.shift()!;
        for (const unit of branchUnits.value) {
            if (unit.parent_id === current) {
                result.push(unit.id);
                queue.push(unit.id);
            }
        }
    }

    return result;
}

const shareSourceUnitId = computed<number | null>(() => {
    const files = shareDialogTargetFiles.value;
    if (files.length === 0) {
        return null;
    }

    const fromFirst = resolveDriveFileTeamUnitId(files[0]);
    if (fromFirst === null) {
        return null;
    }

    for (const file of files.slice(1)) {
        if (resolveDriveFileTeamUnitId(file) !== fromFirst) {
            return null;
        }
    }

    return fromFirst;
});

const shareSourceUnitLabel = computed(() => {
    const sourceId = shareSourceUnitId.value;
    if (sourceId === null) {
        return null;
    }

    return branchUnitById.value.get(sourceId)?.name ?? null;
});

const shareChildrenUnitOptions = computed(() => {
    const sourceId = shareSourceUnitId.value;
    if (sourceId === null) {
        return [] as TeamHrFormUnit[];
    }

    const childIds = collectDescendantUnitIds(sourceId);

    return childIds
        .map((id) => branchUnitById.value.get(id))
        .filter((u): u is TeamHrFormUnit => u !== undefined);
});

const shareSpecificUnitOptions = computed(() =>
    branchUnits.value.filter((unit) => {
        const sourceId = shareSourceUnitId.value;
        if (sourceId === null) {
            return true;
        }

        return unit.id !== sourceId;
    }),
);

function openShareDialogForItemIds(itemIds: string[]): void {
    if (isTrashView.value || !isTeamDriveScope.value) {
        return;
    }

    const files = itemIds
        .map((id) => findItem(items.value, id))
        .filter((node): node is DriveFileItem => node?.type === 'file');

    if (files.length === 0) {
        appToast.info('Select at least one file to share.');

        return;
    }

    shareTargetItemIds.value = files.map((f) => f.id);
    shareSpecificUnitIds.value = [];
    shareExemptChildUnitIds.value = [];
    shareTargetMode.value =
        shareSourceUnitId.value !== null ? 'children' : 'specific_units';
    shareDialogOpen.value = true;
}

function closeShareDialog(): void {
    shareDialogOpen.value = false;
    shareTargetItemIds.value = [];
    shareSpecificUnitIds.value = [];
    shareExemptChildUnitIds.value = [];
    shareTargetMode.value = 'children';
}

function toggleShareSpecificUnit(unitId: number, checked: boolean | 'indeterminate'): void {
    const enabled = checked === true;
    if (enabled) {
        if (!shareSpecificUnitIds.value.includes(unitId)) {
            shareSpecificUnitIds.value = [...shareSpecificUnitIds.value, unitId];
        }

        return;
    }

    shareSpecificUnitIds.value = shareSpecificUnitIds.value.filter((id) => id !== unitId);
}

function toggleShareExemptChildUnit(unitId: number, checked: boolean | 'indeterminate'): void {
    const enabled = checked === true;
    if (enabled) {
        if (!shareExemptChildUnitIds.value.includes(unitId)) {
            shareExemptChildUnitIds.value = [...shareExemptChildUnitIds.value, unitId];
        }

        return;
    }

    shareExemptChildUnitIds.value = shareExemptChildUnitIds.value.filter((id) => id !== unitId);
}

function confirmShareDialog(): void {
    if (shareDialogTargetFiles.value.length === 0) {
        appToast.error('No files selected for sharing.');

        return;
    }

    let targetUnitIds: number[] = [];
    if (shareTargetMode.value === 'children') {
        const sourceId = shareSourceUnitId.value;
        if (sourceId === null) {
            appToast.error(
                'Cannot resolve source unit for selected file(s). Use specific units instead.',
            );

            return;
        }

        const childrenIds = shareChildrenUnitOptions.value.map((u) => u.id);
        if (childrenIds.length === 0) {
            appToast.info('This unit has no child units to share to.');

            return;
        }

        targetUnitIds = childrenIds.filter(
            (id) => !shareExemptChildUnitIds.value.includes(id),
        );
    } else {
        targetUnitIds = [...shareSpecificUnitIds.value];
    }

    if (targetUnitIds.length === 0) {
        appToast.error('Choose at least one target unit.');

        return;
    }

    const targetNames = targetUnitIds
        .map((id) => branchUnitById.value.get(id)?.name)
        .filter((label): label is string => typeof label === 'string' && label !== '');
    const sharedCount = shareDialogTargetFiles.value.length;
    appToast.success(
        `Shared ${sharedCount} file${sharedCount > 1 ? 's' : ''} to ${targetNames.length} unit${targetNames.length > 1 ? 's' : ''}. (Session mock.)`,
    );
    closeShareDialog();
}

type DriveRequestTypeView = 'library' | 'upload' | 'access';
type DriveStatusChipFilter = 'all' | DriveMySubmissionStatus;

const requestTypeView = ref<DriveRequestTypeView>('library');
const statusChipFilter = ref<DriveStatusChipFilter>('all');
const uploadedRecencyOrder = ref<DriveRecencyOrder>('any');
const modifiedRecencyOrder = ref<DriveRecencyOrder>('any');

const currentFolderId = ref<string | null>(null);
const selectedIds = ref<string[]>([]);
const typeFilter = ref<DriveTypeFilter>('all');

watch(
    () => props.scope,
    (s) => {
        if (s === 'my') {
            currentFolderId.value = null;
            if (typeFilter.value === 'folder') {
                typeFilter.value = 'all';
            }
        }
        statusChipFilter.value = 'all';
        selectedIds.value = [];
        teamDocumentUnitFilter.value = 'all';

        if (isTrashView.value || !s) {
            requestTypeView.value = 'library';
            approvalQueueMode.value = false;

            return;
        }

        const prefs = loadToolbarPrefs(s);
        approvalQueueMode.value = s === 'my' ? false : prefs.approvalQueueMode;
        requestTypeView.value = prefs.requestTypeView;

        if (restrictViewToQueuesOnly.value && requestTypeView.value === 'library') {
            requestTypeView.value = 'upload';
        }

        if (s === 'team') {
            void loadTeamDocumentsUnits();
        }
    },
    { immediate: true },
);

watch(
    [approvalQueueMode, requestTypeView, () => props.scope],
    () => {
        if (isTrashView.value || !props.scope) {
            return;
        }
        const s = props.scope;
        saveToolbarPrefs(s, {
            approvalQueueMode: s === 'my' ? false : approvalQueueMode.value,
            requestTypeView: requestTypeView.value,
        });
    },
    { flush: 'post' },
);

watch(
    restrictViewToQueuesOnly,
    (queuesOnly) => {
        if (queuesOnly && requestTypeView.value === 'library') {
            requestTypeView.value = 'upload';
        }
    },
    { flush: 'sync' },
);

watch(canViewDocumentAdminViewForActiveScope, (allowed) => {
    if (!allowed) {
        approvalQueueMode.value = false;
    }
});

watch(chartBranchId, () => {
    if (props.scope !== 'team' || isTrashView.value) {
        return;
    }
    teamDocumentUnitFilter.value = 'all';
    branchUnits.value = [];
    void loadTeamDocumentsUnits();
});

watch(
    branchUnitFilterOptions,
    (opts) => {
        const allowed = new Set(opts.map((o) => o.value));
        if (!allowed.has(teamDocumentUnitFilter.value)) {
            teamDocumentUnitFilter.value = 'all';
        }
    },
    { flush: 'post' },
);

watch(requestTypeView, (next) => {
    if (next === 'library') {
        statusChipFilter.value = 'all';
    } else {
        statusChipFilter.value = 'all';
    }
    if (!isTrashView.value) {
        selectedIds.value = [];
    }
});

watch(statusChipFilter, () => {
    if (!isTrashView.value) {
        selectedIds.value = [];
    }
});

watch(teamDocumentUnitFilter, () => {
    if (!isTrashView.value) {
        selectedIds.value = [];
    }
});

watch(uploadedRecencyOrder, () => {
    if (!isTrashView.value) {
        selectedIds.value = [];
    }
});

watch(modifiedRecencyOrder, () => {
    if (!isTrashView.value) {
        selectedIds.value = [];
    }
});

function initialDriveItems(): DriveItem[] {
    if (isTrashView.value) {
        return flattenTrashBatchesForUi(loadTrashBatches());
    }

    const sc = props.scope;
    if (!sc) {
        return createInitialItems('my');
    }

    return loadDriveFromSession(sc) ?? createInitialItems(sc);
}

const items = ref<DriveItem[]>(initialDriveItems());

watch(
    items,
    (v) => {
        if (isTrashView.value || !props.scope) {
            return;
        }
        saveDriveToSession(props.scope, v);
    },
    { deep: true },
);
const searchQuery = ref('');
const ownershipChip = ref<DriveOwnershipChip>('all');
const ownershipChipOptions: {
    value: DriveOwnershipChip;
    label: string;
}[] = [
    { value: 'all', label: 'All' },
    { value: 'owned_by_me', label: 'Owned by me' },
    { value: 'shared_with_me', label: 'Shared with me' },
    { value: 'from_others', label: 'From others' },
    { value: 'starred', label: 'Starred only' },
];
const statusChipOptions: { value: DriveStatusChipFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'approved', label: 'Approved' },
    { value: 'pending', label: 'Pending' },
    { value: 'rejected', label: 'Rejected' },
];
const viewMode = ref<DriveViewMode>('grid');
const sortKey = ref<DriveSortKey>('name');
const sortOrder = ref<DriveSortOrder>('asc');

const detailSheetOpen = ref(false);
const detailTargetId = ref<string | null>(null);
/** Sheet footer ⋮ menu (mirrors row actions; primary Download stays on the bar). */
const detailOverflowMenuOpen = ref(false);

const renameOpen = ref(false);
const renameTargetId = ref<string | null>(null);
const renameValue = ref('');

const deleteOpen = ref(false);
const deleteTargetIds = ref<string[]>([]);

const restoreOpen = ref(false);
const restoreBatchIds = ref<string[]>([]);
const purgeOpen = ref(false);
const purgeBatchIds = ref<string[]>([]);

const moveOpen = ref(false);
const moveTargetIds = ref<string[]>([]);
const moveDestinationId = ref<string | null>(null);

const newFolderOpen = ref(false);
const newFolderName = ref('');

type TeamShareTargetMode = 'children' | 'specific_units';
const shareDialogOpen = ref(false);
const shareTargetMode = ref<TeamShareTargetMode>('children');
const shareTargetItemIds = ref<string[]>([]);
const shareSpecificUnitIds = ref<number[]>([]);
const shareExemptChildUnitIds = ref<number[]>([]);

const fileInputRef = ref<HTMLInputElement | null>(null);
const uploadDialogOpen = ref(false);
const uploadPendingFile = ref<File | null>(null);
/** Up to 8 tags for the upload dialog (session mock). */
const uploadTags = ref<string[]>([]);
const uploadTagDraft = ref('');
const uploadTagInputRef = ref<HTMLInputElement | null>(null);
const uploadNotes = ref('');
const UPLOAD_TAGS_MAX = 8;

watch(currentFolderId, () => {
    detailSheetOpen.value = false;
    detailTargetId.value = null;
    selectedIds.value = [];
});

watch(detailSheetOpen, (open) => {
    if (!open) {
        detailOverflowMenuOpen.value = false;
    }
});

const breadcrumbFolders = computed(() =>
    getBreadcrumbChain(items.value, currentFolderId.value),
);

/**
 * Maps a drive row to Approved | Pending | Rejected for the shared status filter.
 * Uses `mockSubmissionStatus` when set; otherwise infers from `approvalStatusLabel`.
 */
function submissionStatusForFile(item: DriveItem): DriveMySubmissionStatus {
    if (item.type !== 'file') {
        return 'approved';
    }

    if (item.mockSubmissionStatus) {
        return item.mockSubmissionStatus;
    }

    const label = (item.approvalStatusLabel ?? '').trim().toLowerCase();
    if (label.includes('reject')) {
        return 'rejected';
    }
    if (
        label.includes('pending') ||
        label.includes('draft') ||
        label.includes('review')
    ) {
        return 'pending';
    }

    return 'approved';
}

function mySubmissionStatusTooltip(
    status: DriveMySubmissionStatus | undefined,
): string {
    switch (status ?? 'approved') {
        case 'approved':
            return 'Approved';
        case 'pending':
            return 'Pending approval';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Approved';
    }
}

function mySubmissionStatusIconComponent(
    status: DriveMySubmissionStatus | undefined,
) {
    switch (status ?? 'approved') {
        case 'approved':
            return CircleCheck;
        case 'pending':
            return Clock;
        case 'rejected':
            return XCircle;
        default:
            return CircleCheck;
    }
}

function mySubmissionStatusIconClass(
    status: DriveMySubmissionStatus | undefined,
): string {
    switch (status ?? 'approved') {
        case 'approved':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'pending':
            return 'text-amber-600 dark:text-amber-400';
        case 'rejected':
            return 'text-destructive';
        default:
            return 'text-emerald-600 dark:text-emerald-400';
    }
}

/** List view (My): short status label on a colored badge. */
function mySubmissionStatusListBadgeLabel(
    status: DriveMySubmissionStatus | undefined,
): string {
    switch (status ?? 'approved') {
        case 'approved':
            return 'Approved';
        case 'pending':
            return 'Pending';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Approved';
    }
}

function mySubmissionStatusListBadgeClass(
    status: DriveMySubmissionStatus | undefined,
): string {
    switch (status ?? 'approved') {
        case 'approved':
            return 'border-emerald-500/45 bg-emerald-500/12 text-emerald-800 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-200';
        case 'pending':
            return 'border-amber-500/45 bg-amber-500/12 text-amber-900 dark:border-amber-400/40 dark:bg-amber-500/15 dark:text-amber-100';
        case 'rejected':
            return 'border-destructive/45 bg-destructive/12 text-destructive dark:text-destructive';
        default:
            return 'border-emerald-500/45 bg-emerald-500/12 text-emerald-800 dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-200';
    }
}

/**
 * My documents: flat file list with search, type, submission status, and ownership filters.
 */
function buildMyCatalogFileList(): DriveFileItem[] {
    let filtered = items.value.filter((i): i is DriveFileItem => i.type === 'file');
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        filtered = filtered.filter((i) => i.name.toLowerCase().includes(q));
    }

    const tf = typeFilter.value;
    if (tf !== 'all') {
        filtered = filtered.filter((i) => matchesTypeFilter(i, tf));
    }

    filtered = filtered.filter((i) => matchesRequestTypeView(i));
    filtered = filtered.filter((i) =>
        matchesStatusChipFilter(i, statusChipFilter.value),
    );

    const oc = ownershipChip.value;
    if (requestTypeView.value === 'library' && oc !== 'all') {
        filtered = filtered.filter((i) => matchesOwnershipChip(i, oc));
    }

    return filtered;
}

const myCatalogEmptyReason = computed((): 'none' | 'no-match' | 'empty' => {
    if (!isMyCatalogView.value) {
        return 'none';
    }
    const totalFiles = items.value.filter((i) => i.type === 'file').length;
    if (totalFiles === 0) {
        return 'empty';
    }
    if (buildMyCatalogFileList().length === 0) {
        return 'no-match';
    }

    return 'none';
});

const isLibraryRequestTypeView = computed(
    () => requestTypeView.value === 'library',
);

function matchesTypeFilter(item: DriveItem, filter: DriveTypeFilter): boolean {
    if (filter === 'all') {
        return true;
    }
    if (filter === 'folder') {
        return item.type === 'folder';
    }
    if (item.type === 'folder') {
        return false;
    }

    return item.kind === filter;
}

function outgoingRequestKindForItem(item: DriveItem): DriveOutgoingRequestKind {
    if (item.type !== 'file') {
        return 'none';
    }

    return item.mockOutgoingRequest ?? 'none';
}

function matchesStatusChipFilter(
    item: DriveItem,
    filter: DriveStatusChipFilter,
): boolean {
    if (item.type === 'folder') {
        return true;
    }
    if (requestTypeView.value === 'library') {
        return submissionStatusForFile(item) === 'approved';
    }
    if (filter === 'all') {
        return true;
    }
    if (outgoingRequestKindForItem(item) === 'none') {
        return false;
    }

    return submissionStatusForFile(item) === filter;
}

function matchesRequestTypeView(item: DriveItem): boolean {
    if (item.type === 'folder') {
        return true;
    }
    if (requestTypeView.value === 'library') {
        return true;
    }

    return outgoingRequestKindForItem(item) === requestTypeView.value;
}

const visibleChildren = computed(() => {
    if (isMyCatalogView.value) {
        return applyAdditionalRecencyOrder(
            sortDriveItems(
                buildMyCatalogFileList(),
                sortKey.value,
                sortOrder.value,
            ),
        );
    }

    const raw = getChildren(items.value, currentFolderId.value);
    const q = searchQuery.value.trim().toLowerCase();
    let filtered = q
        ? raw.filter((i) => i.name.toLowerCase().includes(q))
        : raw;

    const tf = typeFilter.value;
    if (tf !== 'all') {
        filtered = filtered.filter((i) => matchesTypeFilter(i, tf));
    }

    const oc = ownershipChip.value;
    if (requestTypeView.value === 'library' && oc !== 'all') {
        filtered = filtered.filter((i) => matchesOwnershipChip(i, oc));
    }

    if (!isTrashView.value) {
        filtered = filtered.filter((i) => matchesRequestTypeView(i));
        filtered = filtered.filter((i) =>
            matchesStatusChipFilter(i, statusChipFilter.value),
        );
    }

    if (
        props.scope === 'team' &&
        !isTrashView.value &&
        teamDocumentUnitFilter.value !== 'all'
    ) {
        filtered = filtered.filter((i) =>
            itemMatchesTeamDocumentsUnitFilter(
                i,
                teamDocumentUnitFilter.value,
                branchUnitFilterOptions.value,
            ),
        );
    }

    return applyAdditionalRecencyOrder(
        sortDriveItems(filtered, sortKey.value, sortOrder.value),
    );
});

const detailItem = computed(() => {
    if (detailTargetId.value === null) {
        return null;
    }

    return findItem(items.value, detailTargetId.value) ?? null;
});

const folderChildCount = computed(() => {
    if (!detailItem.value || detailItem.value.type !== 'folder') {
        return 0;
    }

    return getChildren(items.value, detailItem.value.id).length;
});

const moveDestinationOptions = computed(() => {
    const blocked = new Set<string>();
    for (const tid of moveTargetIds.value) {
        const t = findItem(items.value, tid);
        if (t?.type === 'folder') {
            collectSubtreeItemIds(items.value, tid).forEach((id) =>
                blocked.add(id),
            );
        }
        blocked.add(tid);
    }

    return items.value.filter(
        (i): i is DriveFolderItem => i.type === 'folder' && !blocked.has(i.id),
    );
});

function isSelected(id: string): boolean {
    return selectedIds.value.includes(id);
}

function toggleSelected(id: string, additive: boolean): void {
    if (additive) {
        const set = new Set(selectedIds.value);
        if (set.has(id)) {
            set.delete(id);
        } else {
            set.add(id);
        }
        selectedIds.value = [...set];
    } else {
        selectedIds.value = [id];
    }
}

function allVisibleSelected(): boolean {
    return (
        visibleChildren.value.length > 0 &&
        visibleChildren.value.every((i) => isSelected(i.id))
    );
}

function someVisibleSelected(): boolean {
    return visibleChildren.value.some((i) => isSelected(i.id));
}

function toggleSelectAll(): void {
    if (allVisibleSelected()) {
        const visible = new Set(visibleChildren.value.map((i) => i.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
    } else {
        const set = new Set(selectedIds.value);
        for (const i of visibleChildren.value) {
            set.add(i.id);
        }
        selectedIds.value = [...set];
    }
}

function onRowClick(item: DriveItem, e: MouseEvent): void {
    const selectedText = window.getSelection()?.toString() ?? '';
    if (selectedText.length > 0) {
        return;
    }

    const additive = e.metaKey || e.ctrlKey;
    toggleSelected(item.id, additive);
}

function onRowDoubleClick(item: DriveItem): void {
    if (item.type === 'folder') {
        currentFolderId.value = item.id;

        return;
    }
    detailTargetId.value = item.id;
    detailSheetOpen.value = true;
}

function goToFolder(folderId: string | null): void {
    currentFolderId.value = folderId;
}

function openDetail(item: DriveItem): void {
    detailTargetId.value = item.id;
    detailSheetOpen.value = true;
}

function formatModified(iso: string): string {
    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function primaryOwnerDisplay(item: DriveItem): string {
    return item.primaryOwnerLabel ?? item.ownerLabel;
}

function uploadDateDisplay(iso: string | undefined): string {
    if (iso === undefined || iso === '') {
        return '—';
    }

    return formatModified(iso);
}

function visibilityDisplay(item: DriveItem): string {
    return item.visibilityLabel ?? '—';
}

function accessInformationDisplay(item: DriveItem): string {
    return accessModeLabel(resolveDriveItemAccessMode(item));
}

function accessInformationIcon(item: DriveItem): Component {
    switch (resolveDriveItemAccessMode(item)) {
        case 'public':
            return Globe;
        case 'private':
            return User;
    }
}

function driveFileMayDownloadInUi(item: DriveFileItem): boolean {
    if (isTrashView.value) {
        return true;
    }

    return canDownloadDriveFilesForActiveScope.value && driveFileCanDownload(item);
}

function driveFileShowsRequestAccessInUi(item: DriveFileItem): boolean {
    if (isTrashView.value) {
        return false;
    }

    return (
        !canDownloadDriveFilesForActiveScope.value ||
        driveFileRequiresAccessRequest(item)
    );
}

function driveFileMayPreviewInUi(item: DriveFileItem): boolean {
    return driveFileMayDownloadInUi(item);
}

function requestAccessForFile(item: DriveFileItem): void {
    if (isTrashView.value) {
        return;
    }

    const ix = items.value.findIndex((i) => i.id === item.id);
    if (ix === -1) {
        return;
    }

    const cur = items.value[ix];
    if (cur.type !== 'file') {
        return;
    }

    if (cur.mockOutgoingRequest === 'access') {
        appToast.info(`You already have an access request for “${item.name}”.`);

        return;
    }

    items.value[ix] = {
        ...cur,
        mockOutgoingRequest: 'access',
    };
    appToast.success(`Access request sent for “${item.name}”. (Session mock.)`);
}

function approverInformationDisplay(item: DriveItem): string {
    if (item.approverLabel === null) {
        return 'N/A';
    }

    return item.approverLabel ?? '—';
}

function approvalStatusInformationDisplay(item: DriveItem): string {
    return item.approvalStatusLabel ?? '—';
}

function fileKindIconClass(kind: DriveFileItem['kind']): string {
    switch (kind) {
        case 'pdf':
            return 'text-red-600 dark:text-red-400';
        case 'docx':
            return 'text-blue-600 dark:text-blue-400';
        case 'xlsx':
            return 'text-emerald-600 dark:text-emerald-400';
        case 'pptx':
            return 'text-amber-600 dark:text-amber-400';
        default:
            return 'text-muted-foreground';
    }
}

function iconComponent(item: DriveItem) {
    if (item.type === 'folder') {
        return Folder;
    }
    switch (item.kind) {
        case 'xlsx':
            return FileSpreadsheet;
        case 'pptx':
            return Presentation;
        default:
            return FileText;
    }
}

function headerIconClass(item: DriveItem): string {
    if (item.type === 'folder') {
        return 'text-muted-foreground';
    }

    return fileKindIconClass(item.kind);
}

function previewIconClass(item: DriveItem): string {
    if (item.type === 'folder') {
        return 'text-muted-foreground/90';
    }

    return cn(fileKindIconClass(item.kind), 'opacity-90');
}

function openRename(item: DriveItem): void {
    renameTargetId.value = item.id;
    renameValue.value = item.name;
    renameOpen.value = true;
}

function confirmRename(): void {
    if (isTrashView.value) {
        return;
    }

    const id = renameTargetId.value;
    const name = renameValue.value.trim();
    if (!id || !name) {
        return;
    }

    const idx = items.value.findIndex((i) => i.id === id);
    if (idx === -1) {
        return;
    }

    items.value[idx] = { ...items.value[idx], name } as DriveItem;
    renameOpen.value = false;
    renameTargetId.value = null;
    appToast.success('Renamed (local preview only).');
}

function openDelete(ids: string[]): void {
    deleteTargetIds.value = ids;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    if (isTrashView.value || !props.scope) {
        return;
    }

    const { nextDriveItems } = moveSubtreeToTrash({
        scope: props.scope,
        driveItems: items.value,
        deleteRootIds: [...deleteTargetIds.value],
    });

    items.value = nextDriveItems;
    selectedIds.value = selectedIds.value.filter((id) =>
        nextDriveItems.some((i) => i.id === id),
    );
    deleteOpen.value = false;
    deleteTargetIds.value = [];
    if (
        detailTargetId.value &&
        !nextDriveItems.some((i) => i.id === detailTargetId.value)
    ) {
        detailSheetOpen.value = false;
        detailTargetId.value = null;
    }
    appToast.success('Moved to Trash (this browser session only).');
}

function reloadTrashFromSession(): void {
    items.value = flattenTrashBatchesForUi(loadTrashBatches());
    currentFolderId.value = null;
    selectedIds.value = [];
}

function openTrashRestore(ids: string[]): void {
    restoreBatchIds.value = collectTrashBatchIdsForSelection(items.value, ids);
    if (restoreBatchIds.value.length === 0) {
        return;
    }
    restoreOpen.value = true;
}

function openTrashPurge(ids: string[]): void {
    purgeBatchIds.value = collectTrashBatchIdsForSelection(items.value, ids);
    if (purgeBatchIds.value.length === 0) {
        return;
    }
    purgeOpen.value = true;
}

function confirmRestoreBatches(): void {
    for (const bid of restoreBatchIds.value) {
        restoreTrashBatch(bid);
    }
    restoreOpen.value = false;
    restoreBatchIds.value = [];
    reloadTrashFromSession();
    if (detailTargetId.value && !findItem(items.value, detailTargetId.value)) {
        detailSheetOpen.value = false;
        detailTargetId.value = null;
    }
    appToast.success('Restored to document library.');
}

function confirmPurgeBatches(): void {
    for (const bid of purgeBatchIds.value) {
        purgeTrashBatch(bid);
    }
    purgeOpen.value = false;
    purgeBatchIds.value = [];
    reloadTrashFromSession();
    if (detailTargetId.value && !findItem(items.value, detailTargetId.value)) {
        detailSheetOpen.value = false;
        detailTargetId.value = null;
    }
    appToast.success('Permanently deleted from this browser.');
}

function openMove(ids: string[]): void {
    moveTargetIds.value = ids;
    moveDestinationId.value = currentFolderId.value;
    moveOpen.value = true;
}

function confirmMove(): void {
    if (isTrashView.value) {
        return;
    }

    const dest = moveDestinationId.value;
    for (const id of moveTargetIds.value) {
        const node = findItem(items.value, id);
        if (!node) {
            continue;
        }
        if (node.type === 'folder') {
            const sub = collectSubtreeItemIds(items.value, id);
            if (dest !== null && sub.has(dest)) {
                appToast.warning(
                    'Cannot move a folder into itself or its subfolders.',
                );

                return;
            }
        }
    }

    for (const id of moveTargetIds.value) {
        const idx = items.value.findIndex((i) => i.id === id);
        if (idx === -1) {
            continue;
        }
        const node = items.value[idx];
        items.value[idx] = {
            ...node,
            parentId: dest,
            modifiedAt: new Date().toISOString(),
        } as DriveItem;
    }
    moveOpen.value = false;
    moveTargetIds.value = [];
    appToast.success('Moved (local preview only).');
}

function triggerUpload(): void {
    if (props.scope === 'my' || isTrashView.value || !props.scope) {
        return;
    }

    uploadPendingFile.value = null;
    uploadTags.value = [];
    uploadTagDraft.value = '';
    uploadNotes.value = '';
    uploadDialogOpen.value = true;
}

function commitUploadTagToken(token: string): boolean {
    const t = token.trim();
    if (t.length === 0) {
        return false;
    }
    if (uploadTags.value.length >= UPLOAD_TAGS_MAX) {
        appToast.warning(`At most ${UPLOAD_TAGS_MAX} tags.`);

        return false;
    }
    if (uploadTags.value.includes(t)) {
        return false;
    }
    uploadTags.value = [...uploadTags.value, t];

    return true;
}

function removeUploadTag(index: number): void {
    uploadTags.value = uploadTags.value.filter((_, i) => i !== index);
}

function onUploadTagInputKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const raw = uploadTagDraft.value;
        const segments = raw
            .split(',')
            .map((s) => s.trim())
            .filter((s) => s.length > 0);
        for (const seg of segments) {
            if (uploadTags.value.length >= UPLOAD_TAGS_MAX) {
                break;
            }
            commitUploadTagToken(seg);
        }
        uploadTagDraft.value = '';

        return;
    }

    if (e.key === 'Backspace' && uploadTagDraft.value === '') {
        if (uploadTags.value.length > 0) {
            uploadTags.value = uploadTags.value.slice(0, -1);
        }
    }
}

function onUploadTagPaste(e: ClipboardEvent): void {
    const text = e.clipboardData?.getData('text/plain') ?? '';
    if (!text.includes(',') && !text.includes('\n')) {
        return;
    }
    e.preventDefault();
    const pieces = text
        .split(/[,;\n]+/)
        .map((s) => s.trim())
        .filter((s) => s.length > 0);
    for (const p of pieces) {
        if (uploadTags.value.length >= UPLOAD_TAGS_MAX) {
            break;
        }
        commitUploadTagToken(p);
    }
    uploadTagDraft.value = '';
}

/**
 * Tags for the new file row, including a trailing draft if the user did not press Enter/comma.
 */
function finalizeUploadTagsList(): string[] {
    const draft = uploadTagDraft.value.trim();
    let list = [...uploadTags.value];
    if (draft.length > 0 && list.length < UPLOAD_TAGS_MAX && !list.includes(draft)) {
        list = [...list, draft];
    }

    return [...new Set(list)].slice(0, UPLOAD_TAGS_MAX);
}

function resetUploadDialogDraft(): void {
    uploadDialogOpen.value = false;
    uploadPendingFile.value = null;
    uploadTags.value = [];
    uploadTagDraft.value = '';
    uploadNotes.value = '';
}

function onUploadChange(e: Event): void {
    if (isTrashView.value || !props.scope || props.scope === 'my') {
        return;
    }

    const input = e.target as HTMLInputElement;
    const files = input.files;
    const file = files?.[0] ?? null;
    input.value = '';
    if (!file) {
        return;
    }

    if (!isAllowedDocumentUploadFile(file)) {
        appToast.error(
            'Only PDF, Word, Excel, and PowerPoint files can be uploaded.',
        );

        return;
    }

    if (file.size > DOCUMENT_UPLOAD_MAX_BYTES) {
        appToast.error('File must be 10 MB or smaller.');

        return;
    }

    uploadPendingFile.value = file;
}

function confirmUploadWithMetadata(): void {
    if (isTrashView.value || !props.scope || props.scope === 'my') {
        return;
    }
    const file = uploadPendingFile.value;
    if (!file) {
        return;
    }

    const tags = finalizeUploadTagsList();
    uploadTagDraft.value = '';
    const notesTrimmed = uploadNotes.value.trim();
    const nowIso = new Date().toISOString();
    const kind = inferKindFromFileName(file.name);
    const localPdfObjectUrl =
        kind === 'pdf' ? URL.createObjectURL(file) : undefined;
    const baseInfo = defaultInformationMockForNewItem(props.scope);
    const hasAuthority = canViewDocumentAdminViewForActiveScope.value;
    const approvalPatch = hasAuthority
        ? {
              approvalStatusLabel: 'Approved',
              approverLabel: null as string | null,
              mockSubmissionStatus: 'approved' as const,
          }
        : {};

    items.value.push({
        id: `local-${crypto.randomUUID()}`,
        parentId: null,
        type: 'file',
        name: file.name,
        kind,
        sizeLabel: formatBytes(file.size),
        modifiedAt: nowIso,
        uploadedAt: nowIso,
        ownerLabel: 'You',
        starred: false,
        sharedWithMe: false,
        ...baseInfo,
        ...approvalPatch,
        tags,
        ...(notesTrimmed.length > 0 ? { notesLabel: notesTrimmed } : {}),
        ...(localPdfObjectUrl !== undefined ? { localPdfObjectUrl } : {}),
    });

    resetUploadDialogDraft();
    appToast.success('File added with metadata (local preview only).');
}

function openNewFolder(): void {
    if (props.scope === 'my' || isTrashView.value || !props.scope) {
        return;
    }

    newFolderName.value = '';
    newFolderOpen.value = true;
}

function confirmNewFolder(): void {
    if (isTrashView.value || !props.scope || props.scope === 'my') {
        return;
    }

    const name = newFolderName.value.trim();
    if (!name) {
        return;
    }

    items.value.push({
        id: `folder-${crypto.randomUUID()}`,
        parentId: currentFolderId.value,
        type: 'folder',
        name,
        modifiedAt: new Date().toISOString(),
        ownerLabel: 'You',
        starred: false,
        sharedWithMe: false,
        ...defaultInformationMockForNewItem(props.scope),
    });
    newFolderOpen.value = false;
    newFolderName.value = '';
    appToast.success('Folder created (local preview only).');
}

function downloadItem(item: DriveFileItem): void {
    if (!isTrashView.value && !canDownloadDriveFilesForActiveScope.value) {
        requestAccessForFile(item);

        return;
    }

    if (!isTrashView.value && !driveFileCanDownload(item)) {
        appToast.info('Request access before downloading. (Session mock.)');

        return;
    }

    appToast.info(`Download is not wired yet — "${item.name}"`);
}

function openUploadedPdfInNewTab(item: DriveFileItem): void {
    if (!isTrashView.value && !driveFileCanDownload(item)) {
        appToast.info('Request access to preview this file. (Session mock.)');

        return;
    }

    if (item.kind !== 'pdf') {
        return;
    }
    if (!item.localPdfObjectUrl) {
        appToast.info(
            'Preview in a new tab is available for PDFs you upload in this session.',
        );

        return;
    }
    window.open(item.localPdfObjectUrl, '_blank', 'noopener,noreferrer');
}

function shareSelectedFiles(): void {
    openShareDialogForItemIds(selectedIds.value);
}

function shareDetailFile(): void {
    if (detailItem.value?.type !== 'file') {
        return;
    }

    openShareDialogForItemIds([detailItem.value.id]);
}

function openFolderFromDetail(): void {
    if (detailItem.value?.type !== 'folder') {
        return;
    }
    currentFolderId.value = detailItem.value.id;
    detailSheetOpen.value = false;
}

function detailOverflowPreviewPdf(): void {
    detailOverflowMenuOpen.value = false;
    if (detailItem.value?.type === 'file' && detailItem.value.kind === 'pdf') {
        openUploadedPdfInNewTab(detailItem.value);
    }
}

function detailOverflowOpenFolder(): void {
    detailOverflowMenuOpen.value = false;
    openFolderFromDetail();
}

function detailOverflowRename(): void {
    detailOverflowMenuOpen.value = false;
    if (detailItem.value) {
        openRename(detailItem.value);
    }
}

function detailOverflowMove(): void {
    detailOverflowMenuOpen.value = false;
    if (detailItem.value) {
        openMove([detailItem.value.id]);
    }
}

function detailOverflowDelete(): void {
    detailOverflowMenuOpen.value = false;
    if (!detailItem.value) {
        return;
    }
    if (isTrashView.value) {
        openTrashPurge([detailItem.value.id]);

        return;
    }
    openDelete([detailItem.value.id]);
}

function detailOverflowRestore(): void {
    detailOverflowMenuOpen.value = false;
    if (detailItem.value) {
        openTrashRestore([detailItem.value.id]);
    }
}

const driveSelectionBarVisible = computed(
    () => !isTrashView.value && selectedIds.value.length >= 2,
);

const trashSelectionBarVisible = computed(
    () => isTrashView.value && selectedIds.value.length >= 1,
);

const toolbarStorageHint = computed(() =>
    isTrashView.value
        ? 'Trash is kept in this browser session only.'
        : storageHintLabel(props.scope ?? 'my'),
);

const sortControlModel = computed({
    get: (): string => `${sortKey.value}-${sortOrder.value}`,
    set: (v: string) => {
        const [k, o] = v.split('-') as [DriveSortKey, DriveSortOrder];
        sortKey.value = k;
        sortOrder.value = o;
    },
});

function timestampForModified(item: DriveItem): number {
    return Date.parse(item.modifiedAt) || 0;
}

function timestampForUploaded(item: DriveItem): number {
    if (item.type !== 'file') {
        return timestampForModified(item);
    }

    return Date.parse(item.uploadedAt ?? item.modifiedAt) || 0;
}

function compareByRecencyOrder(
    a: DriveItem,
    b: DriveItem,
    order: DriveRecencyOrder,
    timestampGetter: (item: DriveItem) => number,
): number {
    if (order === 'any') {
        return 0;
    }

    const ta = timestampGetter(a);
    const tb = timestampGetter(b);
    if (ta === tb) {
        return 0;
    }

    return order === 'newest' ? tb - ta : ta - tb;
}

function applyAdditionalRecencyOrder(items: DriveItem[]): DriveItem[] {
    if (
        uploadedRecencyOrder.value === 'any' &&
        modifiedRecencyOrder.value === 'any'
    ) {
        return items;
    }

    return [...items].sort((a, b) => {
        const byUploaded = compareByRecencyOrder(
            a,
            b,
            uploadedRecencyOrder.value,
            timestampForUploaded,
        );
        if (byUploaded !== 0) {
            return byUploaded;
        }

        return compareByRecencyOrder(
            a,
            b,
            modifiedRecencyOrder.value,
            timestampForModified,
        );
    });
}

function setRowCheckbox(itemId: string, v: boolean | 'indeterminate'): void {
    const on = v === true;
    const next = new Set(selectedIds.value);
    if (on) {
        next.add(itemId);
    } else {
        next.delete(itemId);
    }
    selectedIds.value = [...next];
}

function toggleStarred(itemId: string): void {
    if (isTrashView.value) {
        return;
    }

    const idx = items.value.findIndex((i) => i.id === itemId);
    if (idx === -1) {
        return;
    }

    const node = items.value[idx];
    if (props.scope === 'my' && submissionStatusForFile(node) !== 'approved') {
        return;
    }
    items.value[idx] = {
        ...node,
        starred: !node.starred,
    } as DriveItem;
}

/** Mouse click leaves focus on the button and keeps focus-within open; blur real clicks only (detail 0 = keyboard). */
function toggleStarredFromUi(itemId: string, event: MouseEvent): void {
    toggleStarred(itemId);
    if (event.detail !== 0) {
        (event.currentTarget as HTMLButtonElement | null)?.blur();
    }
}

/** Grid preview corner: opacity only (tile already has space). */
function starOverlayVisibilityClass(item: DriveItem): string {
    const base = 'transition-opacity duration-150';
    if (item.starred === true || isSelected(item.id)) {
        return `${base} pointer-events-auto opacity-100`;
    }

    return `${base} pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100 focus-within:pointer-events-auto focus-within:opacity-100`;
}

/** Icon-only star control: no circular plate; keyboard focus uses focus-visible ring (Button ghost reset). */
const starToggleButtonClass =
    'inline-flex size-8 shrink-0 items-center justify-center rounded-sm border-0 bg-transparent p-0 shadow-none hover:bg-transparent focus-visible:ring-2 focus-visible:ring-ring/55 focus-visible:ring-offset-2 ring-offset-background [&_svg]:pointer-events-none';

/** List name row: collapse width when unstarred so the name stays left-aligned until hover/focus. */
function listStarSlotClass(item: DriveItem): string {
    const base =
        'flex shrink-0 items-center justify-center overflow-hidden transition-[width,opacity] duration-150';
    if (item.starred === true || isSelected(item.id)) {
        return `${base} pointer-events-auto w-8 opacity-100`;
    }

    return `${base} pointer-events-none w-0 opacity-0 group-hover:pointer-events-auto group-hover:w-8 group-hover:opacity-100 focus-within:pointer-events-auto focus-within:w-8 focus-within:opacity-100`;
}

/** Grid name row: reserve checkbox width only when visible to reduce early truncation. */
function gridCheckboxSlotClass(item: DriveItem): string {
    const base =
        'flex items-center overflow-hidden transition-[width,opacity] duration-150';
    if (isSelected(item.id)) {
        return `${base} pointer-events-auto w-4 opacity-100`;
    }

    return `${base} pointer-events-none w-0 opacity-0 group-hover:pointer-events-auto group-hover:w-4 group-hover:opacity-100`;
}
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="flex min-h-0 flex-1 flex-col gap-4">
        <input
            v-if="!isTrashView"
            ref="fileInputRef"
            type="file"
            class="sr-only"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
            title="PDF, Word, Excel, or PowerPoint only — max 10 MB — one file per upload"
            @change="onUploadChange"
        />

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
                            id="documents_drive_search"
                            v-model="searchQuery"
                            type="search"
                            :placeholder="
                                isTrashView
                                    ? 'Search in Trash…'
                                    : 'Search in this folder…'
                            "
                            autocomplete="off"
                            :aria-label="
                                isTrashView
                                    ? 'Search items in Trash'
                                    : 'Search files and folders in this folder'
                            "
                        />
                    </InputGroup>
                </div>
            </template>
            <template #end>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <template v-if="isTrashView">
                        <Select v-model="sortControlModel">
                            <SelectTrigger class="h-9 w-[140px]">
                                <SelectValue placeholder="Sort" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="name-asc"
                                    >Name (A–Z)</SelectItem
                                >
                                <SelectItem value="name-desc"
                                    >Name (Z–A)</SelectItem
                                >
                                <SelectItem value="modified-desc"
                                    >Modified (newest)</SelectItem
                                >
                                <SelectItem value="modified-asc"
                                    >Modified (oldest)</SelectItem
                                >
                            </SelectContent>
                        </Select>

                        <Select v-model="typeFilter">
                            <SelectTrigger
                                class="h-9 w-[140px]"
                                aria-label="Filter by file type"
                            >
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem
                                    v-if="!isMyCatalogView"
                                    value="folder"
                                    >Folders</SelectItem
                                >
                                <SelectItem value="docx">Word</SelectItem>
                                <SelectItem value="pdf">PDF</SelectItem>
                                <SelectItem value="xlsx">Excel</SelectItem>
                                <SelectItem value="pptx">PowerPoint</SelectItem>
                            </SelectContent>
                        </Select>
                    </template>
                    <template v-else>
                        <div class="flex shrink-0 items-center gap-2">
                            <Label
                                for="documents-drive-request-type-view"
                                class="whitespace-nowrap text-sm text-muted-foreground"
                            >
                                View
                            </Label>
                            <NativeSelect
                                id="documents-drive-request-type-view"
                                v-model="requestTypeView"
                                class="h-9 min-w-[160px] sm:w-[180px] max-sm:max-w-full"
                                aria-label="View"
                            >
                                <option
                                    v-if="!restrictViewToQueuesOnly"
                                    value="library"
                                >
                                    Files
                                </option>
                                <option value="upload">Upload Requests</option>
                                <option value="access">Access Requests</option>
                            </NativeSelect>
                        </div>

                        <div
                            v-if="!isMyCatalogView"
                            class="flex shrink-0 items-center gap-2"
                        >
                            <Button
                                v-if="showApprovalQueueModeToggle"
                                id="documents-drive-admin-view"
                                type="button"
                                size="sm"
                                variant="outline"
                                :class="
                                    cn(
                                        'h-9 shrink-0 gap-2 px-3',
                                        approvalQueueMode
                                            ? 'border-violet-600 bg-violet-600 text-white shadow-sm hover:bg-violet-600/90 hover:text-white dark:border-violet-500 dark:bg-violet-600 dark:hover:bg-violet-600/90'
                                            : 'border-violet-400/70 text-violet-800 hover:bg-violet-500/12 dark:border-violet-500/55 dark:text-violet-200 dark:hover:bg-violet-500/15',
                                    )
                                "
                                :aria-pressed="approvalQueueMode"
                                :aria-label="
                                    approvalQueueMode
                                        ? 'Admin View on — queues only'
                                        : 'Admin View off — full library'
                                "
                                @click="toggleAdminView"
                            >
                                <Shield class="size-4 shrink-0" aria-hidden="true" />
                                Admin View
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button type="button" class="shrink-0">
                                        <FolderPlus class="size-4" />
                                        <span class="mr-1">New</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    class="min-w-48"
                                    side="bottom"
                                >
                                    <DropdownMenuItem @click="openNewFolder">
                                        <Folder class="size-4" />
                                        New folder
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="triggerUpload">
                                        <Upload class="size-4" />
                                        File upload
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </template>
                </div>
            </template>
        </HrisIndexToolbar>

        <div
            v-if="!isTrashView"
            class="mt-2 flex flex-col gap-3"
        >
            <div
                class="order-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-3"
            >
                <div
                v-if="!isLibraryRequestTypeView"
                    class="flex flex-wrap items-center gap-2"
                    role="toolbar"
                    aria-label="Filter by status"
                >
                    <Button
                        v-for="opt in statusChipOptions"
                        :key="opt.value"
                        type="button"
                        :variant="
                            statusChipFilter === opt.value ? 'default' : 'outline'
                        "
                        size="sm"
                        class="shrink-0 rounded-full px-4"
                        @click="statusChipFilter = opt.value"
                    >
                        {{ opt.label }}
                    </Button>
                </div>
                <div
                    v-if="isLibraryRequestTypeView"
                    class="flex flex-wrap items-center gap-2"
                    role="toolbar"
                    aria-label="Filter by ownership"
                >
                    <Button
                        v-for="opt in ownershipChipOptions"
                        :key="opt.value"
                        type="button"
                        :variant="
                            ownershipChip === opt.value ? 'default' : 'outline'
                        "
                        size="sm"
                        class="shrink-0 rounded-full px-4"
                        @click="ownershipChip = opt.value"
                    >
                        {{ opt.label }}
                    </Button>
                </div>
                <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                    <span
                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        aria-live="polite"
                    >
                        {{ toolbarStorageHint }}
                    </span>
                    <div
                        class="flex items-center rounded-lg border border-border/70 p-0.5"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2"
                            :class="
                                viewMode === 'grid' ? 'bg-muted shadow-sm' : ''
                            "
                            :aria-pressed="viewMode === 'grid'"
                            @click="viewMode = 'grid'"
                        >
                            <LayoutGrid class="size-4" />
                            <span class="sr-only">Grid view</span>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2"
                            :class="
                                viewMode === 'list' ? 'bg-muted shadow-sm' : ''
                            "
                            :aria-pressed="viewMode === 'list'"
                            @click="viewMode = 'list'"
                        >
                            <List class="size-4" />
                            <span class="sr-only">List view</span>
                        </Button>
                    </div>
                </div>
            </div>
            <div
                class="order-1 flex flex-wrap items-end justify-between gap-x-3 gap-y-3"
            >
                <div
                    class="flex flex-wrap items-center gap-3 gap-y-2"
                    role="toolbar"
                    aria-label="Refine list"
                >
                    <div class="flex shrink-0 items-center gap-2">
                        <Label
                            for="documents-drive-type-filter"
                            class="whitespace-nowrap text-sm text-muted-foreground"
                        >
                            Type
                        </Label>
                        <Select v-model="typeFilter">
                            <SelectTrigger
                                id="documents-drive-type-filter"
                                class="h-9 w-[140px]"
                                aria-label="Filter by file type"
                            >
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem
                                    v-if="!isMyCatalogView"
                                    value="folder"
                                    >Folders</SelectItem
                                >
                                <SelectItem value="docx">Word</SelectItem>
                                <SelectItem value="pdf">PDF</SelectItem>
                                <SelectItem value="xlsx">Excel</SelectItem>
                                <SelectItem value="pptx">PowerPoint</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Label
                            for="documents-drive-sort"
                            class="whitespace-nowrap text-sm text-muted-foreground"
                        >
                            Sort
                        </Label>
                        <Select v-model="sortControlModel">
                            <SelectTrigger
                                id="documents-drive-sort"
                                class="h-9 w-[140px]"
                                aria-label="Sort items"
                            >
                                <SelectValue placeholder="Sort" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="name-asc"
                                    >Name (A–Z)</SelectItem
                                >
                                <SelectItem value="name-desc"
                                    >Name (Z–A)</SelectItem
                                >
                                <SelectItem value="modified-desc"
                                    >Modified (newest)</SelectItem
                                >
                                <SelectItem value="modified-asc"
                                    >Modified (oldest)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Label
                            for="documents-drive-uploaded-order"
                            class="whitespace-nowrap text-sm text-muted-foreground"
                        >
                            Uploaded
                        </Label>
                        <Select v-model="uploadedRecencyOrder">
                            <SelectTrigger
                                id="documents-drive-uploaded-order"
                                class="h-9 w-[140px]"
                                aria-label="Order by upload recency"
                            >
                                <SelectValue placeholder="Uploaded" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any</SelectItem>
                                <SelectItem value="newest"
                                    >Newest first</SelectItem
                                >
                                <SelectItem value="oldest"
                                    >Oldest first</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Label
                            for="documents-drive-modified-order"
                            class="whitespace-nowrap text-sm text-muted-foreground"
                        >
                            Modified
                        </Label>
                        <Select v-model="modifiedRecencyOrder">
                            <SelectTrigger
                                id="documents-drive-modified-order"
                                class="h-9 w-[140px]"
                                aria-label="Order by modification recency"
                            >
                                <SelectValue placeholder="Modified" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any</SelectItem>
                                <SelectItem value="newest"
                                    >Newest first</SelectItem
                                >
                                <SelectItem value="oldest"
                                    >Oldest first</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center justify-end gap-3 gap-y-2"
                >
                    <div
                        v-if="isTeamDriveScope"
                        class="flex min-w-0 items-center gap-2"
                    >
                        <Label
                            for="documents-drive-team-unit"
                            class="whitespace-nowrap text-sm text-muted-foreground"
                        >
                            Unit
                        </Label>
                        <Select v-model="teamDocumentUnitFilter">
                            <SelectTrigger
                                id="documents-drive-team-unit"
                                class="h-9 w-full min-w-56 justify-between text-start font-normal sm:w-56"
                                :disabled="branchUnitsLoading"
                                aria-label="Filter by unit"
                            >
                                <SelectValue placeholder="All units">
                                    <template #default="{ modelValue }">
                                        <HrisUnitSelectTriggerLabel
                                            :select-model-value="modelValue"
                                            :options="branchUnitFilterOptions"
                                        />
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in branchUnitFilterOptions"
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
        </div>

        <Breadcrumb v-if="!isTrashView" class="mt-2">
            <BreadcrumbList>
                <BreadcrumbItem>
                    <BreadcrumbLink as-child>
                        <button
                            type="button"
                            class="hover:text-foreground"
                            @click="goToFolder(null)"
                        >
                            {{ isTrashView ? 'Trash' : 'Home' }}
                        </button>
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <template v-for="folder in breadcrumbFolders" :key="folder.id">
                    <BreadcrumbSeparator>
                        <ChevronRight class="size-4" />
                    </BreadcrumbSeparator>
                    <BreadcrumbItem>
                        <BreadcrumbLink
                            v-if="
                                folder.id !==
                                breadcrumbFolders[breadcrumbFolders.length - 1]
                                    ?.id
                            "
                            as-child
                        >
                            <button
                                type="button"
                                class="hover:text-foreground"
                                @click="goToFolder(folder.id)"
                            >
                                {{ folder.name }}
                            </button>
                        </BreadcrumbLink>
                        <BreadcrumbPage v-else>{{
                            folder.name
                        }}</BreadcrumbPage>
                    </BreadcrumbItem>
                </template>
            </BreadcrumbList>
        </Breadcrumb>

        <div
            v-if="driveSelectionBarVisible"
            class="flex flex-wrap items-center gap-2 rounded-lg bg-muted/30 px-3 py-2 text-sm"
        >
            <span class="text-muted-foreground">
                {{ selectedIds.length }} selected
            </span>
            <template v-if="canManageDriveMutations">
                <Separator orientation="vertical" class="hidden h-6 sm:block" />
                <Button
                    v-if="!selectionIncludesFolder"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    @click="shareSelectedFiles"
                >
                    <Share2 class="size-4" aria-hidden="true" />
                    <span>Share</span>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    @click="openMove(selectedIds)"
                >
                    <FolderInput class="size-4" aria-hidden="true" />
                    <span>Move</span>
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    class="shrink-0"
                    @click="openDelete(selectedIds)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    <span>Move to Trash</span>
                </Button>
            </template>
        </div>

        <div
            v-if="trashSelectionBarVisible"
            class="flex flex-wrap items-center gap-2 rounded-lg bg-muted/30 px-3 py-2 text-sm"
        >
            <span class="text-muted-foreground">
                {{ selectedIds.length }} selected
            </span>
            <Separator orientation="vertical" class="hidden h-6 sm:block" />
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="openTrashRestore(selectedIds)"
            >
                <Undo2 class="size-4" aria-hidden="true" />
                <span>Restore</span>
            </Button>
            <Button
                type="button"
                variant="destructive"
                size="sm"
                class="shrink-0"
                @click="openTrashPurge(selectedIds)"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                <span>Delete forever</span>
            </Button>
        </div>

        <div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
            <div>
                <div
                    v-if="visibleChildren.length === 0"
                    class="flex min-h-[200px] flex-col items-center justify-center gap-2 py-12 text-center"
                >
                    <template v-if="isTrashView">
                        <Trash2
                            class="size-12 text-muted-foreground/50"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium text-foreground">
                            Nothing in Trash
                        </p>
                        <p class="max-w-sm text-xs text-muted-foreground">
                            Deleted items from your document libraries appear
                            here for this browser session.
                        </p>
                    </template>
                    <template v-else-if="isMyCatalogView">
                        <FileText
                            class="size-12 text-muted-foreground/50"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium text-foreground">
                            <template v-if="myCatalogEmptyReason === 'empty'">
                                No uploads yet
                            </template>
                            <template
                                v-else-if="myCatalogEmptyReason === 'no-match'"
                            >
                                No documents match this status
                            </template>
                        </p>
                        <p class="max-w-sm text-xs text-muted-foreground">
                            <template v-if="myCatalogEmptyReason === 'empty'">
                                Files you upload from Team, Branch, or Company
                                libraries will appear here (session preview).
                            </template>
                            <template v-else>
                                Try another status filter or clear the search.
                            </template>
                        </p>
                    </template>
                    <template v-else>
                        <Folder
                            class="size-12 text-muted-foreground/50"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium text-foreground">
                            This folder is empty
                        </p>
                        <p class="max-w-sm text-xs text-muted-foreground">
                            Upload files or create a folder — changes stay in
                            this browser session only.
                        </p>
                        <div class="mt-2 flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="shrink-0"
                                @click="openNewFolder"
                            >
                                <FolderPlus
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span>New folder</span>
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                class="shrink-0"
                                @click="triggerUpload"
                            >
                                <Upload class="size-4" aria-hidden="true" />
                                <span>Upload</span>
                            </Button>
                        </div>
                    </template>
                </div>

                <!-- Grid (Drive-style tile: header strip + large preview) -->
                <div
                    v-else-if="viewMode === 'grid'"
                    class="grid grid-cols-[repeat(auto-fill,minmax(11.5rem,1fr))] gap-3"
                >
                    <div
                        v-for="item in visibleChildren"
                        :key="item.id"
                        class="group flex min-w-0 flex-col overflow-hidden rounded-xl border border-border/50 bg-card text-left shadow-sm transition select-none hover:shadow-md"
                        :class="
                            isSelected(item.id)
                                ? 'border-primary ring-2 ring-primary/25'
                                : ''
                        "
                        role="button"
                        tabindex="0"
                        @click="onRowClick(item, $event)"
                        @dblclick.prevent="onRowDoubleClick(item)"
                        @keydown.enter.prevent="onRowDoubleClick(item)"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-border/40 bg-muted/10 py-1.5 pr-2 pl-3"
                        >
                            <component
                                :is="iconComponent(item)"
                                class="size-4 shrink-0"
                                :class="headerIconClass(item)"
                                aria-hidden="true"
                            />
                            <div
                                class="min-w-0 flex flex-1 flex-col gap-0.5 overflow-hidden"
                            >
                                <span
                                    class="truncate text-sm font-medium text-foreground"
                                    :title="item.name"
                                >
                                    {{ item.name }}
                                </span>
                                <Badge
                                    v-if="
                                        isTrashView && item.trashSourceScope
                                    "
                                    variant="secondary"
                                    class="w-fit max-w-full truncate text-[10px] font-normal"
                                >
                                    {{ scopeLabel(item.trashSourceScope) }}
                                </Badge>
                            </div>
                            <div
                                class="flex shrink-0 items-center gap-0.5"
                                @click.stop
                            >
                                <div
                                    :class="gridCheckboxSlotClass(item)"
                                >
                                    <Checkbox
                                        :model-value="isSelected(item.id)"
                                        class="size-3.5 border-border"
                                        @update:model-value="
                                            setRowCheckbox(item.id, $event)
                                        "
                                    />
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 shrink-0"
                                            :aria-label="`Actions for ${item.name}`"
                                        >
                                            <MoreVertical
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="start"
                                        class="min-w-44"
                                        side="right"
                                    >
                                        <template v-if="isTrashView">
                                            <DropdownMenuItem
                                                @click="openDetail(item)"
                                            >
                                                <Info
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Information
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    item.type === 'file' &&
                                                    item.kind === 'pdf'
                                                "
                                                @click="
                                                    openUploadedPdfInNewTab(
                                                        item,
                                                    )
                                                "
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Preview
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="item.type === 'file'"
                                                @click="downloadItem(item)"
                                            >
                                                <Download
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Download
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="
                                                    openTrashRestore([
                                                        item.id,
                                                    ])
                                                "
                                            >
                                                <Undo2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Restore
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @click="
                                                    openTrashPurge([item.id])
                                                "
                                            >
                                                <Trash2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Delete forever
                                            </DropdownMenuItem>
                                        </template>
                                        <template v-else>
                                            <DropdownMenuItem
                                                @click="openDetail(item)"
                                            >
                                                <Info
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Information
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    item.type === 'file' &&
                                                    item.kind === 'pdf' &&
                                                    driveFileMayPreviewInUi(item)
                                                "
                                                @click="
                                                    openUploadedPdfInNewTab(
                                                        item,
                                                    )
                                                "
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Preview
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    item.type === 'file' &&
                                                    driveFileMayDownloadInUi(item)
                                                "
                                                @click="downloadItem(item)"
                                            >
                                                <Download
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Download
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    item.type === 'file' &&
                                                    driveFileShowsRequestAccessInUi(
                                                        item,
                                                    )
                                                "
                                                @click="
                                                    requestAccessForFile(item)
                                                "
                                            >
                                                <UserPlus
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Request access
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                @click="openRename(item)"
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Rename
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                @click="openMove([item.id])"
                                            >
                                                <FolderInput
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Move
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator
                                                v-if="canManageDriveMutations"
                                            />
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                variant="destructive"
                                                @click="openDelete([item.id])"
                                            >
                                                <Trash2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Move to Trash
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                        <div
                            class="relative flex aspect-5/4 min-h-30 items-center justify-center bg-background"
                        >
                            <component
                                :is="iconComponent(item)"
                                class="size-16 shrink-0 sm:size-20"
                                :class="previewIconClass(item)"
                                aria-hidden="true"
                            />
                            <div
                                v-if="
                                    isMyCatalogView && item.type === 'file'
                                "
                                class="pointer-events-auto absolute left-2 bottom-2 z-10"
                                @click.stop
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :class="cn(starToggleButtonClass)"
                                            :aria-label="
                                                mySubmissionStatusTooltip(
                                                    item.mockSubmissionStatus,
                                                )
                                            "
                                        >
                                            <component
                                                :is="
                                                    mySubmissionStatusIconComponent(
                                                        item.mockSubmissionStatus,
                                                    )
                                                "
                                                class="size-4 shrink-0"
                                                :class="
                                                    mySubmissionStatusIconClass(
                                                        item.mockSubmissionStatus,
                                                    )
                                                "
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        {{
                                            mySubmissionStatusTooltip(
                                                item.mockSubmissionStatus,
                                            )
                                        }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                            <div
                                v-if="!isTrashView && !isMyCatalogView"
                                class="absolute right-2 bottom-2 z-10"
                                :class="starOverlayVisibilityClass(item)"
                                @click.stop
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="cn(starToggleButtonClass)"
                                    :aria-pressed="item.starred === true"
                                    :aria-label="
                                        item.starred
                                            ? `Remove star from ${item.name}`
                                            : `Star ${item.name}`
                                    "
                                    @click="
                                        toggleStarredFromUi(item.id, $event)
                                    "
                                >
                                    <Star
                                        class="size-4"
                                        :class="
                                            item.starred
                                                ? 'fill-amber-400 text-amber-500 dark:fill-amber-400/90 dark:text-amber-400'
                                                : 'text-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                            <div
                                v-else-if="
                                    isMyCatalogView &&
                                    item.type === 'file' &&
                                    submissionStatusForFile(item) ===
                                        'approved'
                                "
                                class="absolute right-2 bottom-2 z-10"
                                :class="starOverlayVisibilityClass(item)"
                                @click.stop
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="cn(starToggleButtonClass)"
                                    :aria-pressed="item.starred === true"
                                    :aria-label="
                                        item.starred
                                            ? `Remove star from ${item.name}`
                                            : `Star ${item.name}`
                                    "
                                    @click="
                                        toggleStarredFromUi(item.id, $event)
                                    "
                                >
                                    <Star
                                        class="size-4"
                                        :class="
                                            item.starred
                                                ? 'fill-amber-400 text-amber-500 dark:fill-amber-400/90 dark:text-amber-400'
                                                : 'text-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List -->
                <div v-else class="overflow-x-auto">
                    <table
                        class="w-full min-w-[640px] text-left text-sm select-none"
                    >
                        <thead>
                            <tr
                                class="border-b border-border/70 text-sm font-medium text-muted-foreground"
                            >
                                <th class="w-10 px-2 py-2">
                                    <Checkbox
                                        :model-value="
                                            allVisibleSelected()
                                                ? true
                                                : someVisibleSelected()
                                                  ? 'indeterminate'
                                                  : false
                                        "
                                        @update:model-value="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-2 py-2">Name</th>
                                <th
                                    v-if="isTrashView"
                                    class="hidden px-2 py-2 sm:table-cell"
                                >
                                    Source
                                </th>
                                <th
                                    v-if="isMyCatalogView"
                                    class="hidden w-[120px] min-w-28 px-2 py-2 text-center md:table-cell"
                                >
                                    Status
                                </th>
                                <th
                                    class="hidden px-2 py-2 text-center md:table-cell"
                                >
                                    Owner
                                </th>
                                <th class="hidden px-2 py-2 lg:table-cell">
                                    Modified
                                </th>
                                <th class="hidden px-2 py-2 sm:table-cell">
                                    Size
                                </th>
                                <th class="w-12 px-2 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in visibleChildren"
                                :key="item.id"
                                class="group cursor-pointer border-b border-border/40 transition hover:bg-muted/40"
                                :class="
                                    isSelected(item.id) ? 'bg-muted/50' : ''
                                "
                                @click="onRowClick(item, $event)"
                                @dblclick.prevent="onRowDoubleClick(item)"
                            >
                                <td class="px-2 py-2 align-middle" @click.stop>
                                    <Checkbox
                                        :model-value="isSelected(item.id)"
                                        @update:model-value="
                                            setRowCheckbox(item.id, $event)
                                        "
                                    />
                                </td>
                                <td class="px-2 py-2 align-middle">
                                    <div
                                        class="flex max-w-md items-center gap-1.5 sm:gap-2"
                                    >
                                        <component
                                            :is="iconComponent(item)"
                                            class="size-5 shrink-0"
                                            :class="
                                                item.type === 'file'
                                                    ? fileKindIconClass(
                                                          item.kind,
                                                      )
                                                    : 'text-sky-600 dark:text-sky-400'
                                            "
                                        />
                                        <div
                                            v-if="
                                                !isTrashView &&
                                                (!isMyCatalogView ||
                                                    (item.type === 'file' &&
                                                        submissionStatusForFile(
                                                            item,
                                                        ) === 'approved'))
                                            "
                                            :class="listStarSlotClass(item)"
                                            @click.stop
                                        >
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                :class="
                                                    cn(starToggleButtonClass)
                                                "
                                                :aria-pressed="
                                                    item.starred === true
                                                "
                                                :aria-label="
                                                    item.starred
                                                        ? `Remove star from ${item.name}`
                                                        : `Star ${item.name}`
                                                "
                                                @click="
                                                    toggleStarredFromUi(
                                                        item.id,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <Star
                                                    class="size-4"
                                                    :class="
                                                        item.starred
                                                            ? 'fill-amber-400 text-amber-500 dark:fill-amber-400/90 dark:text-amber-400'
                                                            : 'text-muted-foreground'
                                                    "
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm font-medium text-foreground"
                                            >{{ item.name }}</span
                                        >
                                    </div>
                                </td>
                                <td
                                    v-if="isTrashView"
                                    class="hidden px-2 py-2 align-middle text-muted-foreground sm:table-cell"
                                >
                                    {{
                                        item.trashSourceScope
                                            ? scopeLabel(item.trashSourceScope)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    v-if="isMyCatalogView"
                                    class="hidden w-[120px] min-w-28 px-2 py-2 align-middle md:table-cell"
                                    @click.stop
                                >
                                    <div
                                        class="flex justify-center text-center"
                                    >
                                        <Badge
                                            v-if="item.type === 'file'"
                                            variant="outline"
                                            class="text-xs font-normal"
                                            :class="
                                                mySubmissionStatusListBadgeClass(
                                                    item.mockSubmissionStatus,
                                                )
                                            "
                                        >
                                            {{
                                                mySubmissionStatusListBadgeLabel(
                                                    item.mockSubmissionStatus,
                                                )
                                            }}
                                        </Badge>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </div>
                                </td>
                                <td
                                    class="hidden px-2 py-2 align-middle text-center text-muted-foreground md:table-cell"
                                >
                                    {{ item.ownerLabel }}
                                </td>
                                <td
                                    class="hidden px-2 py-2 align-middle text-muted-foreground lg:table-cell"
                                >
                                    {{ formatModified(item.modifiedAt) }}
                                </td>
                                <td
                                    class="hidden px-2 py-2 align-middle text-muted-foreground sm:table-cell"
                                >
                                    {{
                                        item.type === 'folder'
                                            ? '—'
                                            : item.sizeLabel
                                    }}
                                </td>
                                <td class="px-2 py-2 align-middle" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                :aria-label="`Actions for ${item.name}`"
                                            >
                                                <MoreHorizontal
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="start"
                                            class="min-w-44"
                                            side="right"
                                        >
                                            <template v-if="isTrashView">
                                                <DropdownMenuItem
                                                    @click="openDetail(item)"
                                                >
                                                    <Info
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Information
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        item.type === 'file' &&
                                                        item.kind === 'pdf'
                                                    "
                                                    @click="
                                                        openUploadedPdfInNewTab(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    <ExternalLink
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Preview
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="item.type === 'file'"
                                                    @click="downloadItem(item)"
                                                >
                                                    <Download
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Download
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    @click="
                                                        openTrashRestore([
                                                            item.id,
                                                        ])
                                                    "
                                                >
                                                    <Undo2
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Restore
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    @click="
                                                        openTrashPurge([
                                                            item.id,
                                                        ])
                                                    "
                                                >
                                                    <Trash2
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Delete forever
                                                </DropdownMenuItem>
                                            </template>
                                            <template v-else>
                                                <DropdownMenuItem
                                                    @click="openDetail(item)"
                                                >
                                                    <Info
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Information
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        item.type === 'file' &&
                                                        item.kind === 'pdf' &&
                                                        driveFileMayPreviewInUi(
                                                            item,
                                                        )
                                                    "
                                                    @click="
                                                        openUploadedPdfInNewTab(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    <ExternalLink
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Preview
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        item.type === 'file' &&
                                                        driveFileMayDownloadInUi(
                                                            item,
                                                        )
                                                    "
                                                    @click="downloadItem(item)"
                                                >
                                                    <Download
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Download
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        item.type === 'file' &&
                                                        driveFileShowsRequestAccessInUi(
                                                            item,
                                                        )
                                                    "
                                                    @click="
                                                        requestAccessForFile(item)
                                                    "
                                                >
                                                    <UserPlus
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Request access
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        canManageDriveMutations
                                                    "
                                                    @click="openRename(item)"
                                                >
                                                    <Pencil
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Rename
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="
                                                        canManageDriveMutations
                                                    "
                                                    @click="
                                                        openMove([item.id])
                                                    "
                                                >
                                                    <FolderInput
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Move
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator
                                                    v-if="
                                                        canManageDriveMutations
                                                    "
                                                />
                                                <DropdownMenuItem
                                                    v-if="
                                                        canManageDriveMutations
                                                    "
                                                    variant="destructive"
                                                    @click="
                                                        openDelete([item.id])
                                                    "
                                                >
                                                    <Trash2
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Move to Trash
                                                </DropdownMenuItem>
                                            </template>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detail sheet (layout aligned with calendar sheets: cards + footer actions) -->
        <Sheet v-model:open="detailSheetOpen">
            <SheetContent
                side="right"
                class="flex max-h-dvh w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg"
            >
                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div
                        class="flex min-h-0 flex-1 flex-col gap-0 overflow-hidden px-4 pb-4 text-sm"
                    >
                        <SheetHeader class="space-y-0 px-0 pt-4 pb-0 text-left">
                            <SheetTitle
                                class="pr-8 text-base leading-snug font-semibold text-foreground"
                            >
                                {{ detailItem?.name ?? 'Details' }}
                            </SheetTitle>
                            <SheetDescription class="mt-2">
                                <template v-if="isTrashView">
                                    Items stay in Trash for this browser session
                                    until you restore them or delete forever.
                                </template>
                                <template v-else>
                                    Session mock — metadata for UI only.
                                    Sharing is by employee access, not a URL.
                                    Ownership & approval rules apply when
                                    wired to the server.
                                </template>
                            </SheetDescription>
                        </SheetHeader>

                        <div class="mt-4 flex min-h-0 flex-1 flex-col">
                            <ScrollArea
                                v-if="detailItem"
                                class="min-h-0 flex-1"
                            >
                                <div class="space-y-3 px-1.5 pr-3 pb-8">
                                    <div
                                        class="rounded-lg border border-border/60 bg-muted/20 p-4"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Overview
                                        </p>
                                        <div
                                            class="mt-3 flex min-h-[120px] items-center justify-center rounded-md bg-muted/30"
                                        >
                                            <component
                                                :is="iconComponent(detailItem)"
                                                class="size-14 shrink-0 opacity-90 sm:size-16"
                                                :class="
                                                    detailItem.type === 'file'
                                                        ? fileKindIconClass(
                                                              detailItem.kind,
                                                          )
                                                        : 'text-sky-600 dark:text-sky-400'
                                                "
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <p
                                            class="mt-3 text-center text-xs text-muted-foreground"
                                        >
                                            {{
                                                detailItem.type === 'folder'
                                                    ? 'Folder'
                                                    : driveFileKindLabel(
                                                          detailItem.kind,
                                                      )
                                            }}
                                            · thumbnail placeholder
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            File properties
                                        </p>
                                        <dl
                                            class="mt-3 space-y-3 text-foreground"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <component
                                                        :is="
                                                            detailItem.type ===
                                                            'folder'
                                                                ? Folder
                                                                : FileText
                                                        "
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Type</span>
                                                </dt>
                                                <dd
                                                    class="text-right text-sm font-medium"
                                                >
                                                    {{
                                                        detailItem.type ===
                                                        'folder'
                                                            ? 'Folder'
                                                            : driveFileKindLabel(
                                                                  detailItem.kind,
                                                              )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <Clock3
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Modified</span>
                                                </dt>
                                                <dd
                                                    class="text-right text-sm font-medium tabular-nums"
                                                >
                                                    {{
                                                        formatModified(
                                                            detailItem.modifiedAt,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="
                                                    detailItem.type === 'file'
                                                "
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <Calendar
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Upload date</span>
                                                </dt>
                                                <dd
                                                    class="text-right text-sm font-medium tabular-nums"
                                                >
                                                    {{
                                                        uploadDateDisplay(
                                                            detailItem.uploadedAt ??
                                                                detailItem.modifiedAt,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="
                                                    detailItem.type === 'file'
                                                "
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <HardDrive
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Size</span>
                                                </dt>
                                                <dd
                                                    class="text-right text-sm font-medium tabular-nums"
                                                >
                                                    {{ detailItem.sizeLabel }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="
                                                    detailItem.type === 'folder'
                                                "
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <FolderOpen
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Items</span>
                                                </dt>
                                                <dd
                                                    class="text-right text-sm font-medium tabular-nums"
                                                >
                                                    {{ folderChildCount }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div
                                        class="rounded-lg border border-border/60 bg-muted/20 p-3"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Information
                                        </p>
                                        <dl
                                            class="mt-3 space-y-3 text-foreground"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <User
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Owner</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[60%] text-right text-sm font-medium"
                                                >
                                                    {{
                                                        primaryOwnerDisplay(
                                                            detailItem,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 shrink-0 items-center gap-2 text-muted-foreground"
                                                >
                                                    <Tags
                                                        class="size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Tags</span>
                                                </dt>
                                                <dd
                                                    class="flex min-w-0 flex-1 flex-wrap justify-end gap-1.5 sm:max-w-[60%]"
                                                >
                                                    <template
                                                        v-if="
                                                            detailItem.tags
                                                                ?.length
                                                        "
                                                    >
                                                        <Badge
                                                            v-for="tag in detailItem.tags"
                                                            :key="tag"
                                                            variant="secondary"
                                                            class="text-xs font-normal"
                                                        >
                                                            {{ tag }}
                                                        </Badge>
                                                    </template>
                                                    <span
                                                        v-else
                                                        class="text-sm text-muted-foreground"
                                                        >—</span
                                                    >
                                                </dd>
                                            </div>
                                            <div
                                                v-if="
                                                    detailItem.type === 'file' &&
                                                    (detailItem.notesLabel ?? '')
                                                        .trim().length > 0
                                                "
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <StickyNote
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Notes</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[min(100%,24rem)] text-right text-sm font-medium whitespace-pre-wrap"
                                                >
                                                    {{ detailItem.notesLabel }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <Shield
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Visibility</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[60%] text-right text-sm font-medium"
                                                >
                                                    {{
                                                        visibilityDisplay(
                                                            detailItem,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <component
                                                        :is="
                                                            accessInformationIcon(
                                                                detailItem,
                                                            )
                                                        "
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Access</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[60%] text-right text-sm font-medium"
                                                >
                                                    {{
                                                        accessInformationDisplay(
                                                            detailItem,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <UserCheck
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Approver</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[60%] text-right text-sm font-medium"
                                                >
                                                    {{
                                                        approverInformationDisplay(
                                                            detailItem,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex items-start justify-between gap-4"
                                            >
                                                <dt
                                                    class="flex min-w-0 items-start gap-2 text-muted-foreground"
                                                >
                                                    <ClipboardCheck
                                                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span>Approval status</span>
                                                </dt>
                                                <dd
                                                    class="max-w-[60%] text-right text-sm font-medium"
                                                >
                                                    {{
                                                        approvalStatusInformationDisplay(
                                                            detailItem,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </ScrollArea>
                        </div>
                    </div>

                    <div
                        v-if="detailItem"
                        class="shrink-0 border-t border-border/60 px-4 py-3"
                    >
                        <div
                            class="flex w-full flex-nowrap items-stretch gap-2"
                        >
                            <div class="flex shrink-0 items-center self-center">
                                <DropdownMenu
                                    v-model:open="detailOverflowMenuOpen"
                                >
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="size-9 shrink-0 rounded-md"
                                            aria-label="More actions"
                                        >
                                            <MoreVertical
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="min-w-44"
                                        side="top"
                                        :side-offset="6"
                                        :align-flip="false"
                                        :side-flip="false"
                                    >
                                        <template v-if="isTrashView">
                                            <DropdownMenuItem
                                                v-if="
                                                    detailItem.type === 'folder'
                                                "
                                                @click="detailOverflowOpenFolder"
                                            >
                                                <FolderOpen
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Open folder
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    detailItem.type ===
                                                        'file' &&
                                                    detailItem.kind === 'pdf'
                                                "
                                                @click="detailOverflowPreviewPdf"
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Preview
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="detailOverflowRestore"
                                            >
                                                <Undo2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Restore
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @click="detailOverflowDelete"
                                            >
                                                <Trash2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Delete forever
                                            </DropdownMenuItem>
                                        </template>
                                        <template v-else>
                                            <DropdownMenuItem
                                                v-if="
                                                    detailItem.type === 'folder'
                                                "
                                                @click="detailOverflowOpenFolder"
                                            >
                                                <FolderOpen
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Open folder
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    detailItem.type ===
                                                        'file' &&
                                                    detailItem.kind === 'pdf' &&
                                                    driveFileMayPreviewInUi(
                                                        detailItem,
                                                    )
                                                "
                                                @click="detailOverflowPreviewPdf"
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Preview
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                @click="detailOverflowRename"
                                            >
                                                <Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Rename
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                @click="detailOverflowMove"
                                            >
                                                <FolderInput
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Move
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator
                                                v-if="canManageDriveMutations"
                                            />
                                            <DropdownMenuItem
                                                v-if="canManageDriveMutations"
                                                variant="destructive"
                                                @click="detailOverflowDelete"
                                            >
                                                <Trash2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Move to Trash
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <template v-if="isTrashView">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="detailOverflowRestore"
                                >
                                    <Undo2 class="size-4" aria-hidden="true" />
                                    Restore
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="detailOverflowDelete"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    Delete forever
                                </Button>
                                <Button
                                    v-if="detailItem.type === 'file'"
                                    type="button"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="downloadItem(detailItem)"
                                >
                                    <Download
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Download
                                </Button>
                            </template>
                            <template v-else>
                                <Button
                                    v-if="
                                        detailItem.type === 'file' &&
                                        canManageDriveMutations
                                    "
                                    type="button"
                                    variant="outline"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="shareDetailFile"
                                >
                                    <Share2 class="size-4" aria-hidden="true" />
                                    Share
                                </Button>
                                <Button
                                    v-if="
                                        detailItem.type === 'file' &&
                                        driveFileShowsRequestAccessInUi(
                                            detailItem,
                                        )
                                    "
                                    type="button"
                                    variant="outline"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="
                                        requestAccessForFile(detailItem)
                                    "
                                >
                                    <UserPlus
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Request access
                                </Button>
                                <Button
                                    v-if="
                                        detailItem.type === 'file' &&
                                        driveFileMayDownloadInUi(detailItem)
                                    "
                                    type="button"
                                    class="inline-flex min-h-9 min-w-0 flex-1 basis-0 justify-center gap-2"
                                    @click="downloadItem(detailItem)"
                                >
                                    <Download
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Download
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <Dialog v-model:open="shareDialogOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Share files</DialogTitle>
                    <DialogDescription>
                        Share only within units in the current branch. Links are not
                        public in this session preview.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-2">
                    <div class="rounded-md border bg-muted/20 px-3 py-2 text-sm">
                        <p class="font-medium text-foreground">
                            {{ shareDialogTargetFiles.length }} file{{
                                shareDialogTargetFiles.length === 1 ? '' : 's'
                            }}
                        </p>
                        <p
                            v-if="shareSourceUnitLabel"
                            class="text-muted-foreground"
                        >
                            Source unit: {{ shareSourceUnitLabel }}
                        </p>
                        <p v-else class="text-muted-foreground">
                            Source unit is unavailable for one or more selected
                            files.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>Share mode</Label>
                        <div class="grid gap-2">
                            <label
                                class="flex cursor-pointer items-start gap-2 rounded-md border px-3 py-2"
                            >
                                <Checkbox
                                    :model-value="shareTargetMode === 'children'"
                                    :disabled="shareSourceUnitId === null"
                                    @update:model-value="
                                        (next) => {
                                            if (next === true) {
                                                shareTargetMode = 'children';
                                            }
                                        }
                                    "
                                />
                                <div class="grid gap-0.5">
                                    <span class="text-sm font-medium"
                                        >All child units</span
                                    >
                                    <span class="text-xs text-muted-foreground">
                                        Share to all descendants of the source
                                        unit, with optional exemptions.
                                    </span>
                                </div>
                            </label>
                            <label
                                class="flex cursor-pointer items-start gap-2 rounded-md border px-3 py-2"
                            >
                                <Checkbox
                                    :model-value="
                                        shareTargetMode === 'specific_units'
                                    "
                                    @update:model-value="
                                        (next) => {
                                            if (next === true) {
                                                shareTargetMode =
                                                    'specific_units';
                                            }
                                        }
                                    "
                                />
                                <div class="grid gap-0.5">
                                    <span class="text-sm font-medium"
                                        >Specific units</span
                                    >
                                    <span class="text-xs text-muted-foreground">
                                        Pick any same-branch units (neighbor,
                                        parent, or other related units).
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="shareTargetMode === 'children'"
                        class="grid gap-2 rounded-md border px-3 py-3"
                    >
                        <div class="grid gap-0.5">
                            <p class="text-sm font-medium">
                                Exempt child units
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Leave unchecked to include all child units.
                            </p>
                        </div>
                        <div
                            v-if="shareChildrenUnitOptions.length > 0"
                            class="grid max-h-44 gap-2 overflow-auto pr-1"
                        >
                            <label
                                v-for="unit in shareChildrenUnitOptions"
                                :key="`share-children-${unit.id}`"
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <Checkbox
                                    :model-value="
                                        shareExemptChildUnitIds.includes(unit.id)
                                    "
                                    @update:model-value="
                                        toggleShareExemptChildUnit(
                                            unit.id,
                                            $event,
                                        )
                                    "
                                />
                                <span class="text-sm">{{ unit.name }}</span>
                                <span
                                    v-if="unit.code"
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ unit.code }}</span
                                >
                            </label>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            No child units available from the selected source
                            unit.
                        </p>
                    </div>

                    <div
                        v-else
                        class="grid gap-2 rounded-md border px-3 py-3"
                    >
                        <div class="grid gap-0.5">
                            <p class="text-sm font-medium">Choose units</p>
                            <p class="text-xs text-muted-foreground">
                                Only same-branch units are listed.
                            </p>
                        </div>
                        <div class="grid max-h-44 gap-2 overflow-auto pr-1">
                            <label
                                v-for="unit in shareSpecificUnitOptions"
                                :key="`share-specific-${unit.id}`"
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <Checkbox
                                    :model-value="
                                        shareSpecificUnitIds.includes(unit.id)
                                    "
                                    @update:model-value="
                                        toggleShareSpecificUnit(unit.id, $event)
                                    "
                                />
                                <span class="text-sm">{{ unit.name }}</span>
                                <span
                                    v-if="unit.code"
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ unit.code }}</span
                                >
                            </label>
                        </div>
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        @click="closeShareDialog"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="shrink-0"
                        @click="confirmShareDialog"
                    >
                        Share
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Upload file + metadata (session mock; visibility & sharing are managed elsewhere). -->
        <Dialog v-model:open="uploadDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Upload file</DialogTitle>
                    <DialogDescription>
                        One file per upload. PDF, Word, Excel, or PowerPoint —
                        maximum 10 MB. Library is the page you are on.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 py-2">
                    <div class="grid gap-1.5">
                        <Label for="drive-upload-browse">File</Label>
                        <div class="flex min-w-0 items-center gap-2">
                            <div
                                class="min-w-0 flex-1 cursor-pointer rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring/55 focus-visible:ring-offset-2 ring-offset-background"
                                role="button"
                                tabindex="0"
                                aria-label="Select file for upload"
                                @click="fileInputRef?.click()"
                                @keydown.enter.prevent="fileInputRef?.click()"
                                @keydown.space.prevent="fileInputRef?.click()"
                            >
                                <InputGroup
                                    class="min-h-9 w-full items-center"
                                >
                                    <InputGroupAddon align="inline-start">
                                        <FileText
                                            class="text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </InputGroupAddon>
                                    <div
                                        class="flex min-w-0 flex-1 items-center border-0 bg-transparent py-1.5 pl-1.5 pr-2"
                                    >
                                        <p
                                            class="min-w-0 flex-1 truncate text-left text-sm"
                                        >
                                            <template v-if="uploadPendingFile">
                                                <span class="text-foreground">{{
                                                    uploadPendingFile.name
                                                }}</span>
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    ·
                                                    {{
                                                        formatBytes(
                                                            uploadPendingFile.size,
                                                        )
                                                    }}
                                                </span>
                                            </template>
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                No file selected
                                            </span>
                                        </p>
                                    </div>
                                </InputGroup>
                            </div>
                            <Button
                                id="drive-upload-browse"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="inline-flex h-9 shrink-0 items-center gap-1.5"
                                @click="fileInputRef?.click()"
                            >
                                <Upload
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Upload
                            </Button>
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="drive-upload-tags-input">Tags (optional)</Label>
                        <div
                            class="flex min-h-9 cursor-text flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-2 py-1 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
                            role="group"
                            aria-label="Tags"
                            @click="uploadTagInputRef?.focus()"
                        >
                            <Badge
                                v-for="(tag, idx) in uploadTags"
                                :key="`${tag}-${idx}`"
                                variant="secondary"
                                class="inline-flex max-w-full items-center gap-0.5 py-0.5 pr-0.5 pl-2 font-normal"
                            >
                                <span class="max-w-48 truncate">{{ tag }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-6 shrink-0 rounded-sm text-muted-foreground hover:text-foreground"
                                    :aria-label="`Remove tag ${tag}`"
                                    @click.stop="removeUploadTag(idx)"
                                >
                                    <X class="size-3.5" aria-hidden="true" />
                                </Button>
                            </Badge>
                            <input
                                id="drive-upload-tags-input"
                                ref="uploadTagInputRef"
                                v-model="uploadTagDraft"
                                type="text"
                                class="min-w-24 flex-1 border-0 bg-transparent py-1 text-sm outline-none placeholder:text-muted-foreground"
                                :placeholder="
                                    uploadTags.length === 0
                                        ? 'Type a tag, comma or Enter…'
                                        : 'Add another…'
                                "
                                autocomplete="off"
                                @keydown="onUploadTagInputKeydown"
                                @paste="onUploadTagPaste"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Comma or Enter adds tags. Up to {{ UPLOAD_TAGS_MAX }}.
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="drive-upload-notes">Notes (optional)</Label>
                        <Textarea
                            id="drive-upload-notes"
                            v-model="uploadNotes"
                            class="min-h-20"
                            placeholder="Optional context for approvers or your records (session mock)"
                        />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        @click="resetUploadDialogDraft"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="shrink-0"
                        :disabled="uploadPendingFile === null"
                        @click="confirmUploadWithMetadata"
                    >
                        Upload
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Rename -->
        <Dialog v-model:open="renameOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Rename</DialogTitle>
                    <DialogDescription>
                        Changes apply only in this browser session.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2 py-2">
                    <Label for="drive-rename-input">Name</Label>
                    <Input
                        id="drive-rename-input"
                        v-model="renameValue"
                        autocomplete="off"
                        @keydown.enter.prevent="confirmRename"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        @click="renameOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="shrink-0"
                        @click="confirmRename"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Move -->
        <Dialog v-model:open="moveOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Move to</DialogTitle>
                    <DialogDescription>
                        Choose a destination folder. Root is “Home”.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2 py-2">
                    <Label for="drive-move-dest">Destination</Label>
                    <Select
                        :model-value="
                            moveDestinationId === null
                                ? '__root__'
                                : moveDestinationId
                        "
                        @update:model-value="
                            (v) => {
                                moveDestinationId =
                                    v === '__root__' ? null : String(v);
                            }
                        "
                    >
                        <SelectTrigger id="drive-move-dest">
                            <SelectValue placeholder="Select folder" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__root__"
                                >Home (root)</SelectItem
                            >
                            <SelectItem
                                v-for="f in moveDestinationOptions"
                                :key="f.id"
                                :value="f.id"
                            >
                                {{ f.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        @click="moveOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="button" class="shrink-0" @click="confirmMove">
                        Move here
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- New folder -->
        <Dialog v-model:open="newFolderOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>New folder</DialogTitle>
                    <DialogDescription>
                        Created under the current folder (session only).
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2 py-2">
                    <Label for="drive-new-folder">Folder name</Label>
                    <Input
                        id="drive-new-folder"
                        v-model="newFolderName"
                        autocomplete="off"
                        placeholder="Untitled folder"
                        @keydown.enter.prevent="confirmNewFolder"
                    />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0"
                        @click="newFolderOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        class="shrink-0"
                        @click="confirmNewFolder"
                    >
                        Create
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Move to Trash -->
        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Move to Trash?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ deleteTargetIds.length }} item<span
                            v-if="deleteTargetIds.length !== 1"
                            >s</span
                        >
                        will move to Trash. You can restore from Documents →
                        Trash while this browser session lasts. Nothing is
                        deleted on the server.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel class="shrink-0"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction class="shrink-0" @click="confirmDelete">
                        Move to Trash
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Restore from Trash -->
        <AlertDialog v-model:open="restoreOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Restore items?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This restores
                        {{ restoreBatchIds.length }} deleted group<span
                            v-if="restoreBatchIds.length !== 1"
                            >s</span
                        >
                        to their document libraries (session storage only).
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel class="shrink-0"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="shrink-0"
                        @click="confirmRestoreBatches"
                    >
                        Restore
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete forever (Trash) -->
        <AlertDialog v-model:open="purgeOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete forever?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Permanently remove
                        {{ purgeBatchIds.length }} group<span
                            v-if="purgeBatchIds.length !== 1"
                            >s</span
                        >
                        from Trash. Uploaded PDF previews in this session will
                        be revoked. This cannot be undone in the browser.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel class="shrink-0"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="shrink-0 bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="confirmPurgeBatches"
                    >
                        Delete forever
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
        </div>
    </TooltipProvider>
</template>
