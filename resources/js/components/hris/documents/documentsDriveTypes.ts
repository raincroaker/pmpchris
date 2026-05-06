export type DocumentsScope = 'my' | 'team' | 'branch' | 'company';

/** Session mock: private items require an access request to download unless you own them. */
export type DriveItemAccessMode = 'private' | 'public';

/** Session mock: approval queue for “My documents” catalog (files you uploaded). */
export type DriveMySubmissionStatus = 'approved' | 'pending' | 'rejected';

export type DriveFileKind = 'pdf' | 'docx' | 'xlsx' | 'pptx';

/**
 * Optional mock fields for the Information sheet (no backend enforcement yet).
 */
export type DriveItemInformationMock = {
    /** Primary steward; falls back to ownerLabel in UI when omitted */
    primaryOwnerLabel?: string;
    /** Additional stewards / co-owners (session mock) */
    secondaryOwnerLabels?: string[];
    createdByLabel?: string;
    lastModifiedByLabel?: string;
    /** Category-style labels */
    tags?: string[];
    /** e.g. Private, Team library, Branch library */
    visibilityLabel?: string;
    /** `private`: others must request access to download/preview; `public`: library members may download */
    accessMode?: DriveItemAccessMode;
    /** Null = explicit “N/A” (e.g. My documents); string = named approver */
    approverLabel?: string | null;
    /** e.g. N/A, Pending, Approved, Rejected */
    approvalStatusLabel?: string;
    /** Optional uploader notes (session mock) */
    notesLabel?: string;
};

export type DriveFolderItem = {
    id: string;
    parentId: string | null;
    type: 'folder';
    name: string;
    modifiedAt: string;
    ownerLabel: string;
    /** Session mock: user starred for quick access */
    starred?: boolean;
    /** Session mock: item was shared to the current user */
    sharedWithMe?: boolean;
    /** Trash aggregate view — batch id for restore / purge */
    trashBatchId?: string;
    /** Trash aggregate view — original document library */
    trashSourceScope?: DocumentsScope;
} & DriveItemInformationMock;

export type DriveFileItem = {
    id: string;
    parentId: string | null;
    type: 'file';
    name: string;
    kind: DriveFileKind;
    sizeLabel: string;
    modifiedAt: string;
    /** Optional upload timestamp mock; falls back to modifiedAt when omitted */
    uploadedAt?: string;
    ownerLabel: string;
    starred?: boolean;
    sharedWithMe?: boolean;
    /** Session upload only — `URL.createObjectURL`; revoke when item removed */
    localPdfObjectUrl?: string;
    trashBatchId?: string;
    trashSourceScope?: DocumentsScope;
    /** My documents mock — submission state for status chips */
    mockSubmissionStatus?: DriveMySubmissionStatus;
    /** Team library mock: unit label must match branch unit select option label for filtering */
    mockTeamUnitLabel?: string | null;
    /** Team library mock: source organizational unit id for share targeting logic */
    mockTeamUnitId?: number | null;
    /** Mock: access / upload request queue (orthogonal to approval status in the UI) */
    mockOutgoingRequest?: DriveOutgoingRequestKind;
} & DriveItemInformationMock;

export type DriveItem = DriveFolderItem | DriveFileItem;

export type DriveSortKey = 'name' | 'modified';
export type DriveSortOrder = 'asc' | 'desc';
export type DriveViewMode = 'grid' | 'list';
export type DriveRecencyOrder = 'any' | 'newest' | 'oldest';

/** Toolbar filter: all items, folders only, or one office file kind (no images). */
export type DriveTypeFilter = 'all' | 'folder' | DriveFileKind;

export type DriveOwnershipChip =
    | 'all'
    | 'owned_by_me'
    | 'shared_with_me'
    | 'from_others'
    | 'starred';

/** Session mock: user-initiated request unrelated to submission status alone */
export type DriveOutgoingRequestKind = 'none' | 'upload' | 'access';

/**
 * Filter by outstanding request kind.
 * `none` = show only files with no upload/access request; `upload` / `access` = that queue.
 */
export type DriveRequestFilter = 'all' | 'none' | 'upload' | 'access';
