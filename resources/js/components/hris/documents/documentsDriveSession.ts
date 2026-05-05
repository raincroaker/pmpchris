import {
    collectSubtreeItemIds,
    findItem,
} from '@/components/hris/documents/documentsDriveHelpers';
import type { DocumentsScope, DriveItem } from '@/components/hris/documents/documentsDriveTypes';

const DRIVE_KEY_PREFIX = 'pmpchris:documents:drive:v2:';
const TOOLBAR_KEY_PREFIX = 'pmpchris:documents:toolbar:v1:';
const TRASH_KEY = 'pmpchris:documents:trash:v1';

export type TrashedBatch = {
    batchId: string;
    scope: DocumentsScope;
    deletedAt: string;
    items: DriveItem[];
    rootOriginalParents: Record<string, string | null>;
};

function driveStorageKey(scope: DocumentsScope): string {
    return `${DRIVE_KEY_PREFIX}${scope}`;
}

function safeParseJson<T>(raw: string | null): T | null {
    if (raw === null || raw === '') {
        return null;
    }
    try {
        return JSON.parse(raw) as T;
    } catch {
        return null;
    }
}

const REQUEST_TYPE_VIEW_VALUES = new Set(['library', 'upload', 'access']);

function toolbarStorageKey(scope: DocumentsScope): string {
    return `${TOOLBAR_KEY_PREFIX}${scope}`;
}

export type DocumentsToolbarPrefs = {
    approvalQueueMode: boolean;
    requestTypeView: 'library' | 'upload' | 'access';
};

/**
 * Restores View + Approval queues toggle per library scope (browser session).
 */
export function loadToolbarPrefs(scope: DocumentsScope): DocumentsToolbarPrefs {
    if (typeof sessionStorage === 'undefined') {
        return {
            approvalQueueMode: false,
            requestTypeView: 'library',
        };
    }

    const parsed = safeParseJson<Record<string, unknown>>(
        sessionStorage.getItem(toolbarStorageKey(scope)),
    );

    if (!parsed || typeof parsed !== 'object') {
        return {
            approvalQueueMode: false,
            requestTypeView: 'library',
        };
    }

    const approvalQueueMode = parsed.approvalQueueMode === true;
    const rawView = parsed.requestTypeView;
    const requestTypeView =
        typeof rawView === 'string' &&
        REQUEST_TYPE_VIEW_VALUES.has(rawView)
            ? (rawView as DocumentsToolbarPrefs['requestTypeView'])
            : 'library';

    return { approvalQueueMode, requestTypeView };
}

export function saveToolbarPrefs(
    scope: DocumentsScope,
    prefs: DocumentsToolbarPrefs,
): void {
    if (typeof sessionStorage === 'undefined') {
        return;
    }

    sessionStorage.setItem(
        toolbarStorageKey(scope),
        JSON.stringify(prefs),
    );
}

export function loadDriveFromSession(scope: DocumentsScope): DriveItem[] | null {
    if (typeof sessionStorage === 'undefined') {
        return null;
    }

    const parsed = safeParseJson<DriveItem[]>(
        sessionStorage.getItem(driveStorageKey(scope)),
    );

    if (!Array.isArray(parsed)) {
        return null;
    }

    return parsed;
}

export function saveDriveToSession(
    scope: DocumentsScope,
    items: DriveItem[],
): void {
    if (typeof sessionStorage === 'undefined') {
        return;
    }

    sessionStorage.setItem(
        driveStorageKey(scope),
        JSON.stringify(items),
    );
}

export function loadTrashBatches(): TrashedBatch[] {
    if (typeof sessionStorage === 'undefined') {
        return [];
    }

    const parsed = safeParseJson<TrashedBatch[]>(
        sessionStorage.getItem(TRASH_KEY),
    );

    if (!Array.isArray(parsed)) {
        return [];
    }

    return parsed.filter(
        (b) =>
            b &&
            typeof b.batchId === 'string' &&
            typeof b.scope === 'string' &&
            Array.isArray(b.items) &&
            b.rootOriginalParents &&
            typeof b.rootOriginalParents === 'object',
    );
}

function saveTrashBatches(batches: TrashedBatch[]): void {
    if (typeof sessionStorage === 'undefined') {
        return;
    }

    sessionStorage.setItem(TRASH_KEY, JSON.stringify(batches));
}

function cloneDriveItem(item: DriveItem): DriveItem {
    return structuredClone(item) as DriveItem;
}

function revokePdfUrlsInItems(items: DriveItem[]): void {
    for (const node of items) {
        if (node.type === 'file' && node.localPdfObjectUrl) {
            URL.revokeObjectURL(node.localPdfObjectUrl);
        }
    }
}

function stripTrashAnnotations(item: DriveItem): DriveItem {
    const next = cloneDriveItem(item);
    delete next.trashBatchId;
    delete next.trashSourceScope;

    return next;
}

/**
 * Flatten all trash batches into one navigable list (trash root = `parentId === null`).
 */
export function flattenTrashBatchesForUi(batches: TrashedBatch[]): DriveItem[] {
    const out: DriveItem[] = [];

    for (const batch of batches) {
        for (const raw of batch.items) {
            const item = cloneDriveItem(raw);
            item.trashBatchId = batch.batchId;
            item.trashSourceScope = batch.scope;
            out.push(item);
        }
    }

    return out;
}

export function moveSubtreeToTrash(params: {
    scope: DocumentsScope;
    driveItems: DriveItem[];
    deleteRootIds: string[];
}): { nextDriveItems: DriveItem[]; batch: TrashedBatch } {
    const { scope, driveItems, deleteRootIds } = params;
    const toRemove = new Set<string>();

    for (const rootId of deleteRootIds) {
        const node = findItem(driveItems, rootId);
        if (!node) {
            continue;
        }
        if (node.type === 'folder') {
            collectSubtreeItemIds(driveItems, rootId).forEach((id) =>
                toRemove.add(id),
            );
        } else {
            toRemove.add(rootId);
        }
    }

    const rootOriginalParents: Record<string, string | null> = {};
    for (const rootId of deleteRootIds) {
        const node = findItem(driveItems, rootId);
        if (node) {
            rootOriginalParents[rootId] = node.parentId;
        }
    }

    const subtreeItems = driveItems.filter((i) => toRemove.has(i.id));
    const removedIds = toRemove;

    const normalizedTrashItems: DriveItem[] = subtreeItems.map((item) => {
        const cloned = cloneDriveItem(item);
        const parentStaysInDrive =
            cloned.parentId !== null && !removedIds.has(cloned.parentId);
        if (parentStaysInDrive) {
            cloned.parentId = null;
        }

        return cloned;
    });

    const batch: TrashedBatch = {
        batchId: crypto.randomUUID(),
        scope,
        deletedAt: new Date().toISOString(),
        items: normalizedTrashItems,
        rootOriginalParents,
    };

    const nextDriveItems = driveItems.filter((i) => !toRemove.has(i.id));

    const batches = loadTrashBatches();
    batches.push(batch);
    saveTrashBatches(batches);

    return { nextDriveItems, batch };
}

export function restoreTrashBatch(batchId: string): boolean {
    const batches = loadTrashBatches();
    const idx = batches.findIndex((b) => b.batchId === batchId);
    if (idx === -1) {
        return false;
    }

    const batch = batches[idx];
    const driveKey = driveStorageKey(batch.scope);
    const existingRaw =
        typeof sessionStorage !== 'undefined'
            ? sessionStorage.getItem(driveKey)
            : null;
    const existing = safeParseJson<DriveItem[]>(existingRaw) ?? [];

    const restored: DriveItem[] = batch.items.map((item) => {
        const clone = stripTrashAnnotations(item);
        if (Object.prototype.hasOwnProperty.call(batch.rootOriginalParents, clone.id)) {
            clone.parentId = batch.rootOriginalParents[clone.id] ?? null;
        }

        return clone;
    });

    const merged = [...existing, ...restored];
    if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem(driveKey, JSON.stringify(merged));
    }

    batches.splice(idx, 1);
    saveTrashBatches(batches);

    return true;
}

export function purgeTrashBatch(batchId: string): boolean {
    const batches = loadTrashBatches();
    const idx = batches.findIndex((b) => b.batchId === batchId);
    if (idx === -1) {
        return false;
    }

    const batch = batches[idx];
    revokePdfUrlsInItems(batch.items);
    batches.splice(idx, 1);
    saveTrashBatches(batches);

    return true;
}

export function scopeLabel(scope: DocumentsScope): string {
    switch (scope) {
        case 'my':
            return 'My Documents';
        case 'team':
            return 'Team Documents';
        case 'branch':
            return 'Branch Documents';
        case 'company':
            return 'Company Documents';
        default:
            return scope;
    }
}

export function collectTrashBatchIdsForSelection(
    items: DriveItem[],
    selectedIds: string[],
): string[] {
    const ids = new Set<string>();
    for (const id of selectedIds) {
        const node = findItem(items, id);
        if (node?.trashBatchId) {
            ids.add(node.trashBatchId);
        }
    }

    return [...ids];
}
