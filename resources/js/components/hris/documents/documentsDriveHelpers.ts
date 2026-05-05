import type {
    DocumentsScope,
    DriveFileItem,
    DriveFileKind,
    DriveFolderItem,
    DriveItem,
    DriveItemAccessMode,
    DriveItemInformationMock,
    DriveOwnershipChip,
    DriveSortKey,
    DriveSortOrder,
} from './documentsDriveTypes';

const OWNER_YOU = 'You';

/**
 * Session mock: normalized access mode when omitting `accessMode` on seeded items.
 * Legacy `access_request` (older mocks) is treated as `private`.
 */
export function resolveDriveItemAccessMode(
    item: Pick<DriveItem, 'accessMode' | 'visibilityLabel'>,
): DriveItemAccessMode {
    const raw = item.accessMode as DriveItemAccessMode | 'access_request' | undefined;
    if (raw === 'access_request') {
        return 'private';
    }
    if (raw !== undefined) {
        return raw;
    }

    if ((item.visibilityLabel ?? '').toLowerCase().includes('private')) {
        return 'private';
    }

    return 'public';
}

export function isDriveItemOwnedByCurrentUser(
    item: Pick<DriveItem, 'ownerLabel' | 'primaryOwnerLabel'>,
): boolean {
    return (item.primaryOwnerLabel ?? item.ownerLabel) === OWNER_YOU;
}

export function driveFileCanDownload(item: DriveFileItem): boolean {
    if (resolveDriveItemAccessMode(item) === 'public') {
        return true;
    }

    return isDriveItemOwnedByCurrentUser(item);
}

export function driveFileRequiresAccessRequest(item: DriveFileItem): boolean {
    return (
        resolveDriveItemAccessMode(item) === 'private' &&
        !isDriveItemOwnedByCurrentUser(item)
    );
}

export function accessModeLabel(mode: DriveItemAccessMode): string {
    switch (mode) {
        case 'private':
            return 'Private';
        case 'public':
            return 'Public';
    }
}

/**
 * Session defaults for newly created items (upload / new folder) — Information sheet mock.
 */
export function defaultInformationMockForNewItem(
    scope: DocumentsScope,
): DriveItemInformationMock {
    const actor = 'You';
    const base: DriveItemInformationMock = {
        primaryOwnerLabel: actor,
        createdByLabel: actor,
        lastModifiedByLabel: actor,
        tags: [],
    };

    switch (scope) {
        case 'my':
            return {
                ...base,
                visibilityLabel: 'Private',
                approverLabel: null,
                approvalStatusLabel: 'N/A',
                accessMode: 'private',
            };
        case 'team':
            return {
                ...base,
                visibilityLabel: 'Team library',
                approverLabel: 'Team head (when required)',
                approvalStatusLabel: 'Pending',
                accessMode: 'public',
            };
        case 'branch':
            return {
                ...base,
                visibilityLabel: 'Branch library',
                approverLabel: 'Branch HR manager',
                approvalStatusLabel: 'Pending',
                accessMode: 'public',
            };
        case 'company':
            return {
                ...base,
                visibilityLabel: 'Company library',
                approverLabel: 'Corporate HR lead',
                approvalStatusLabel: 'Pending',
                accessMode: 'public',
            };
        default:
            return {
                ...base,
                visibilityLabel: 'Private',
                approverLabel: null,
                approvalStatusLabel: 'N/A',
                accessMode: 'private',
            };
    }
}

export function matchesOwnershipChip(
    item: DriveItem,
    chip: DriveOwnershipChip,
): boolean {
    switch (chip) {
        case 'all':
            return true;
        case 'owned_by_me':
            return item.ownerLabel === OWNER_YOU;
        case 'shared_with_me':
            return item.sharedWithMe === true;
        case 'from_others':
            return item.ownerLabel !== OWNER_YOU;
        case 'starred':
            return item.starred === true;
        default:
            return true;
    }
}

export function findItem(
    items: DriveItem[],
    id: string,
): DriveItem | undefined {
    return items.find((i) => i.id === id);
}

/**
 * All item ids in the subtree rooted at `rootId` (including the root).
 */
export function collectSubtreeItemIds(
    items: DriveItem[],
    rootId: string,
): Set<string> {
    const result = new Set<string>();
    const queue = [rootId];

    while (queue.length > 0) {
        const id = queue.pop()!;
        result.add(id);
        for (const it of items) {
            if (it.parentId === id) {
                queue.push(it.id);
            }
        }
    }

    return result;
}

export function getChildren(
    items: DriveItem[],
    parentId: string | null,
): DriveItem[] {
    return items.filter((i) => i.parentId === parentId);
}

/**
 * Breadcrumb from root to the given folder (inclusive). Empty if folder not found.
 */
export function getBreadcrumbChain(
    items: DriveItem[],
    folderId: string | null,
): DriveFolderItem[] {
    if (folderId === null) {
        return [];
    }

    const chain: DriveFolderItem[] = [];
    let currentId: string | null = folderId;

    while (currentId !== null) {
        const node = findItem(items, currentId);
        if (!node || node.type !== 'folder') {
            break;
        }
        chain.unshift(node);
        currentId = node.parentId;
    }

    return chain;
}

export function sortDriveItems(
    items: DriveItem[],
    sortKey: DriveSortKey,
    order: DriveSortOrder,
): DriveItem[] {
    const dir = order === 'asc' ? 1 : -1;
    const sorted = [...items];

    sorted.sort((a, b) => {
        if (a.type === 'folder' && b.type !== 'folder') {
            return -1;
        }
        if (a.type !== 'folder' && b.type === 'folder') {
            return 1;
        }

        if (sortKey === 'name') {
            return (
                a.name.localeCompare(b.name, undefined, {
                    sensitivity: 'base',
                }) * dir
            );
        }

        const ta = new Date(a.modifiedAt).getTime();
        const tb = new Date(b.modifiedAt).getTime();
        return (ta - tb) * dir;
    });

    return sorted;
}

/** Max upload size for document libraries (product: 10 MB). */
export const DOCUMENT_UPLOAD_MAX_BYTES = 10 * 1024 * 1024;

const UPLOAD_ALLOWED_EXTENSIONS = [
    '.pdf',
    '.doc',
    '.docx',
    '.xls',
    '.xlsx',
    '.ppt',
    '.pptx',
] as const;

/**
 * True if the file name ends with an allowed office/PDF extension (case-insensitive).
 * Used before session mock ingest; pair with size checks in the UI.
 */
export function isAllowedDocumentUploadFile(file: File): boolean {
    const lower = file.name.toLowerCase();

    return UPLOAD_ALLOWED_EXTENSIONS.some((ext) => lower.endsWith(ext));
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function inferKindFromFileName(name: string): DriveFileKind {
    const lower = name.toLowerCase();
    if (lower.endsWith('.pdf')) {
        return 'pdf';
    }
    if (lower.endsWith('.docx') || lower.endsWith('.doc')) {
        return 'docx';
    }
    if (lower.endsWith('.xlsx') || lower.endsWith('.xls')) {
        return 'xlsx';
    }
    if (lower.endsWith('.pptx') || lower.endsWith('.ppt')) {
        return 'pptx';
    }

    return 'pdf';
}

export function driveFileKindLabel(kind: DriveFileKind): string {
    switch (kind) {
        case 'pdf':
            return 'PDF';
        case 'docx':
            return 'Word';
        case 'xlsx':
            return 'Excel';
        case 'pptx':
            return 'PowerPoint';
        default:
            return 'File';
    }
}
