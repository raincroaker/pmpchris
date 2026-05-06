import type { DriveItem } from '@/components/hris/documents/documentsDriveTypes';

type JsonValidationError = {
    message?: string;
    errors?: Record<string, string[]>;
};

function csrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
}

async function parseJsonError(response: Response, fallback: string): Promise<Error> {
    let payload: JsonValidationError | null = null;
    try {
        payload = (await response.json()) as JsonValidationError;
    } catch {
        payload = null;
    }

    let message =
        typeof payload?.message === 'string' && payload.message !== ''
            ? payload.message
            : fallback;

    const firstError = payload?.errors
        ? Object.values(payload.errors)[0]?.[0]
        : undefined;
    if (typeof firstError === 'string' && firstError !== '') {
        message = firstError;
    }

    return new Error(message);
}

export async function fetchCompanyDocumentsItems(): Promise<DriveItem[]> {
    const response = await fetch('/documents/company/items', {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to load company documents.');
    }

    const payload = (await response.json()) as { data?: DriveItem[] };

    return Array.isArray(payload.data) ? payload.data : [];
}

export async function createCompanyFolder(payload: {
    name: string;
    parentId: number | null;
}): Promise<void> {
    const response = await fetch('/documents/company/folders', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: payload.name,
            parent_id: payload.parentId,
        }),
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to create folder.');
    }
}

export async function updateCompanyFolder(
    folderId: number,
    payload: { name?: string; parentId?: number | null },
): Promise<void> {
    const response = await fetch(`/documents/company/folders/${folderId}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...(payload.name !== undefined ? { name: payload.name } : {}),
            ...(payload.parentId !== undefined
                ? { parent_id: payload.parentId }
                : {}),
        }),
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to update folder.');
    }
}

export async function deleteCompanyFolder(folderId: number): Promise<void> {
    const response = await fetch(`/documents/company/folders/${folderId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to delete folder.');
    }
}

export async function uploadCompanyDocument(payload: {
    file: File;
    folderId: number | null;
    tags: string[];
    notes: string;
}): Promise<void> {
    const formData = new FormData();
    formData.append('file', payload.file);
    if (payload.folderId !== null) {
        formData.append('folder_id', String(payload.folderId));
    }
    payload.tags.forEach((tag) => formData.append('tags[]', tag));
    if (payload.notes !== '') {
        formData.append('notes', payload.notes);
    }

    const response = await fetch('/documents/company/files', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to upload file.');
    }
}

export async function updateCompanyDocument(
    documentId: number,
    payload: { name?: string; folderId?: number | null },
): Promise<void> {
    const response = await fetch(`/documents/company/files/${documentId}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...(payload.name !== undefined ? { name: payload.name } : {}),
            ...(payload.folderId !== undefined
                ? { folder_id: payload.folderId }
                : {}),
        }),
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to update file.');
    }
}

export async function deleteCompanyDocument(documentId: number): Promise<void> {
    const response = await fetch(`/documents/company/files/${documentId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    if (!response.ok) {
        throw await parseJsonError(response, 'Unable to delete file.');
    }
}

export async function updateCompanyDocumentInternalMetadata(
    documentId: number,
    payload: {
        submittedAt?: string | null;
        decidedAt?: string | null;
        createdAt?: string | null;
        updatedAt?: string | null;
        status?: 'approved' | 'pending' | 'rejected' | 'cancelled';
        accessMode?: 'private' | 'public';
        decisionNote?: string | null;
        notes?: string | null;
        tags?: string[];
    },
): Promise<void> {
    const response = await fetch(
        `/documents/company/files/${documentId}/internal-metadata`,
        {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ...(payload.submittedAt !== undefined
                    ? { submitted_at: payload.submittedAt }
                    : {}),
                ...(payload.decidedAt !== undefined
                    ? { decided_at: payload.decidedAt }
                    : {}),
                ...(payload.createdAt !== undefined
                    ? { created_at: payload.createdAt }
                    : {}),
                ...(payload.updatedAt !== undefined
                    ? { updated_at: payload.updatedAt }
                    : {}),
                ...(payload.status !== undefined ? { status: payload.status } : {}),
                ...(payload.accessMode !== undefined
                    ? { access_mode: payload.accessMode }
                    : {}),
                ...(payload.decisionNote !== undefined
                    ? { decision_note: payload.decisionNote }
                    : {}),
                ...(payload.notes !== undefined ? { notes: payload.notes } : {}),
                ...(payload.tags !== undefined ? { tags: payload.tags } : {}),
            }),
        },
    );

    if (!response.ok) {
        throw await parseJsonError(
            response,
            'Unable to update document internal metadata.',
        );
    }
}

export function companyDocumentDownloadUrl(documentId: number): string {
    return `/documents/company/files/${documentId}/download`;
}

export function companyDocumentPreviewUrl(documentId: number): string {
    return `/documents/company/files/${documentId}/preview`;
}
