import type {
    DocumentsScope,
    DriveItem,
    DriveItemInformationMock,
} from './documentsDriveTypes';

function iso(daysAgo: number, hour = 10): string {
    const d = new Date();
    d.setDate(d.getDate() - daysAgo);
    d.setHours(hour, 30, 0, 0);

    return d.toISOString();
}

/**
 * Deterministic mock tree per HR document scope (flat list, parentId-linked).
 */
export function createInitialItems(scope: DocumentsScope): DriveItem[] {
    const p = `${scope}-`;

    const ownerSelf = 'You';
    const ownerTeam = 'Team · Ops';
    const ownerBranch = 'Manila Branch Admin';
    const ownerCompany = 'Corporate Communications';

    const myDocMeta = (
        extra: DriveItemInformationMock = {},
    ): DriveItemInformationMock => ({
        primaryOwnerLabel: ownerSelf,
        createdByLabel: ownerSelf,
        lastModifiedByLabel: ownerSelf,
        visibilityLabel: 'Private',
        accessMode: 'private',
        approverLabel: null,
        approvalStatusLabel: 'N/A',
        tags: [],
        ...extra,
    });

    const teamDocMeta = (
        extra: DriveItemInformationMock = {},
    ): DriveItemInformationMock => ({
        primaryOwnerLabel: ownerTeam,
        createdByLabel: ownerTeam,
        lastModifiedByLabel: ownerTeam,
        visibilityLabel: 'Team library',
        accessMode: 'public',
        approverLabel: 'Ops Head · Riley Tan',
        approvalStatusLabel: 'Approved',
        tags: [],
        ...extra,
    });

    const branchDocMeta = (
        extra: DriveItemInformationMock = {},
    ): DriveItemInformationMock => ({
        primaryOwnerLabel: ownerBranch,
        createdByLabel: ownerBranch,
        lastModifiedByLabel: ownerBranch,
        visibilityLabel: 'Branch library',
        accessMode: 'public',
        approverLabel: 'Manila Branch HR lead',
        approvalStatusLabel: 'Approved',
        tags: [],
        ...extra,
    });

    const companyDocMeta = (
        extra: DriveItemInformationMock = {},
    ): DriveItemInformationMock => ({
        primaryOwnerLabel: ownerCompany,
        createdByLabel: ownerCompany,
        lastModifiedByLabel: ownerCompany,
        visibilityLabel: 'Company library',
        accessMode: 'public',
        approverLabel: 'VP People · Morgan Yu',
        approvalStatusLabel: 'Pending',
        tags: [],
        ...extra,
    });

    switch (scope) {
        case 'my':
            return [
                {
                    id: `${p}file1`,
                    parentId: null,
                    type: 'file',
                    name: 'Employment contract.pdf',
                    kind: 'pdf',
                    sizeLabel: '412 KB',
                    modifiedAt: iso(120),
                    ownerLabel: ownerSelf,
                    starred: true,
                    mockSubmissionStatus: 'approved',
                    mockOutgoingRequest: 'none',
                    ...myDocMeta({
                        tags: ['Employment', 'Contract'],
                        approvalStatusLabel: 'Approved',
                        secondaryOwnerLabels: ['HR · Jordan Lee'],
                    }),
                },
                {
                    id: `${p}file2`,
                    parentId: null,
                    type: 'file',
                    name: 'ID verification checklist.docx',
                    kind: 'docx',
                    sizeLabel: '890 KB',
                    modifiedAt: iso(5),
                    ownerLabel: ownerSelf,
                    sharedWithMe: false,
                    mockSubmissionStatus: 'pending',
                    mockOutgoingRequest: 'upload',
                    ...myDocMeta({
                        tags: ['Verification', 'ID'],
                        approvalStatusLabel: 'Pending',
                    }),
                },
                {
                    id: `${p}file3`,
                    parentId: null,
                    type: 'file',
                    name: 'Benefits overview.docx',
                    kind: 'docx',
                    sizeLabel: '128 KB',
                    modifiedAt: iso(14),
                    ownerLabel: ownerSelf,
                    mockSubmissionStatus: 'rejected',
                    mockOutgoingRequest: 'access',
                    ...myDocMeta({
                        tags: ['Benefits'],
                        approvalStatusLabel: 'Rejected',
                    }),
                },
                {
                    id: `${p}file4`,
                    parentId: null,
                    type: 'file',
                    name: '2024_withholding.xlsx',
                    kind: 'xlsx',
                    sizeLabel: '56 KB',
                    modifiedAt: iso(200),
                    ownerLabel: ownerSelf,
                    mockSubmissionStatus: 'approved',
                    mockOutgoingRequest: 'none',
                    ...myDocMeta({
                        tags: ['Payroll', 'Tax'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
                {
                    id: `${p}file5`,
                    parentId: null,
                    type: 'file',
                    name: 'Training completion.pptx',
                    kind: 'pptx',
                    sizeLabel: '2.4 MB',
                    modifiedAt: iso(3),
                    ownerLabel: ownerSelf,
                    starred: true,
                    mockSubmissionStatus: 'pending',
                    mockOutgoingRequest: 'upload',
                    ...myDocMeta({
                        tags: ['Training'],
                        approvalStatusLabel: 'Pending',
                    }),
                },
                {
                    id: `${p}file6`,
                    parentId: null,
                    type: 'file',
                    name: 'Policy acknowledgment.pdf',
                    kind: 'pdf',
                    sizeLabel: '210 KB',
                    modifiedAt: iso(40),
                    ownerLabel: ownerSelf,
                    mockSubmissionStatus: 'approved',
                    mockOutgoingRequest: 'access',
                    ...myDocMeta({
                        tags: ['Policy'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
            ];
        case 'team':
            return [
                {
                    id: `${p}f1`,
                    parentId: null,
                    type: 'folder',
                    name: 'Roster & schedules',
                    modifiedAt: iso(1),
                    ownerLabel: ownerTeam,
                    ...teamDocMeta({ tags: ['Operations'] }),
                },
                {
                    id: `${p}f2`,
                    parentId: `${p}f1`,
                    type: 'folder',
                    name: 'Q2 2026',
                    modifiedAt: iso(7),
                    ownerLabel: ownerTeam,
                    ...teamDocMeta({ tags: ['Planning'] }),
                },
                {
                    id: `${p}file1`,
                    parentId: null,
                    type: 'file',
                    name: 'Team charter.pdf',
                    kind: 'pdf',
                    sizeLabel: '220 KB',
                    modifiedAt: iso(60),
                    ownerLabel: ownerTeam,
                    mockTeamUnitLabel: 'Head Office',
                    mockOutgoingRequest: 'none',
                    ...teamDocMeta({
                        tags: ['Policy', 'Charter'],
                        secondaryOwnerLabels: ['Ops Head · Riley Tan'],
                    }),
                },
                {
                    id: `${p}file2`,
                    parentId: `${p}f1`,
                    type: 'file',
                    name: 'On-call rotation.xlsx',
                    kind: 'xlsx',
                    sizeLabel: '34 KB',
                    modifiedAt: iso(2),
                    ownerLabel: ownerTeam,
                    mockTeamUnitLabel: 'Panabo Branch',
                    mockOutgoingRequest: 'upload',
                    ...teamDocMeta({ tags: ['Schedule'] }),
                },
                {
                    id: `${p}file3`,
                    parentId: `${p}f2`,
                    type: 'file',
                    name: 'Sprint goals.docx',
                    kind: 'docx',
                    sizeLabel: '78 KB',
                    modifiedAt: iso(4),
                    ownerLabel: ownerTeam,
                    mockTeamUnitLabel: 'Panabo — Section A',
                    mockOutgoingRequest: 'access',
                    ...teamDocMeta({
                        tags: ['Agile'],
                        approvalStatusLabel: 'Draft',
                        accessMode: 'private',
                    }),
                },
                {
                    id: `${p}file4`,
                    parentId: null,
                    type: 'file',
                    name: 'Retrospective summary.pptx',
                    kind: 'pptx',
                    sizeLabel: '1.1 MB',
                    modifiedAt: iso(10),
                    ownerLabel: ownerTeam,
                    mockTeamUnitLabel: 'Tagum Branch',
                    mockOutgoingRequest: 'none',
                    ...teamDocMeta({ tags: ['Retrospective'] }),
                },
                {
                    id: `${p}file5`,
                    parentId: null,
                    type: 'file',
                    name: 'My draft notes.docx',
                    kind: 'docx',
                    sizeLabel: '18 KB',
                    modifiedAt: iso(3),
                    ownerLabel: ownerSelf,
                    starred: true,
                    mockTeamUnitLabel: 'Head Office',
                    mockOutgoingRequest: 'upload',
                    ...teamDocMeta({
                        primaryOwnerLabel: ownerSelf,
                        createdByLabel: ownerSelf,
                        lastModifiedByLabel: ownerSelf,
                        approverLabel: 'Ops Head · Riley Tan',
                        approvalStatusLabel: 'Pending',
                        tags: ['Draft'],
                    }),
                },
            ];
        case 'branch':
            return [
                {
                    id: `${p}f1`,
                    parentId: null,
                    type: 'folder',
                    name: 'Branch policies',
                    modifiedAt: iso(3),
                    ownerLabel: ownerBranch,
                    ...branchDocMeta({ tags: ['Policy'] }),
                },
                {
                    id: `${p}f2`,
                    parentId: `${p}f1`,
                    type: 'folder',
                    name: 'Safety',
                    modifiedAt: iso(90),
                    ownerLabel: ownerBranch,
                    ...branchDocMeta({ tags: ['Safety'] }),
                },
                {
                    id: `${p}file1`,
                    parentId: null,
                    type: 'file',
                    name: 'Local holiday calendar.pdf',
                    kind: 'pdf',
                    sizeLabel: '156 KB',
                    modifiedAt: iso(20),
                    ownerLabel: ownerBranch,
                    mockOutgoingRequest: 'upload',
                    ...branchDocMeta({
                        tags: ['Calendar', 'Holiday'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
                {
                    id: `${p}file2`,
                    parentId: `${p}f1`,
                    type: 'file',
                    name: 'Dress code addendum.docx',
                    kind: 'docx',
                    sizeLabel: '41 KB',
                    modifiedAt: iso(45),
                    ownerLabel: ownerBranch,
                    mockOutgoingRequest: 'access',
                    ...branchDocMeta({
                        tags: ['HR'],
                        accessMode: 'private',
                    }),
                },
                {
                    id: `${p}file3`,
                    parentId: `${p}f2`,
                    type: 'file',
                    name: 'Incident report template.xlsx',
                    kind: 'xlsx',
                    sizeLabel: '29 KB',
                    modifiedAt: iso(8),
                    ownerLabel: ownerBranch,
                    mockOutgoingRequest: 'none',
                    ...branchDocMeta({ tags: ['Safety', 'Template'] }),
                },
                {
                    id: `${p}file4`,
                    parentId: null,
                    type: 'file',
                    name: 'Town hall deck.pptx',
                    kind: 'pptx',
                    sizeLabel: '5.2 MB',
                    modifiedAt: iso(1),
                    ownerLabel: ownerBranch,
                    mockOutgoingRequest: 'none',
                    ...branchDocMeta({ tags: ['Communications'] }),
                },
            ];
        case 'company':
            return [
                {
                    id: `${p}f1`,
                    parentId: null,
                    type: 'folder',
                    name: 'Handbook & ethics',
                    modifiedAt: iso(400),
                    ownerLabel: ownerCompany,
                    ...companyDocMeta({ tags: ['Handbook'] }),
                },
                {
                    id: `${p}f2`,
                    parentId: null,
                    type: 'folder',
                    name: 'Brand assets',
                    modifiedAt: iso(12),
                    ownerLabel: ownerCompany,
                    ...companyDocMeta({ tags: ['Brand'] }),
                },
                {
                    id: `${p}f3`,
                    parentId: `${p}f1`,
                    type: 'folder',
                    name: 'Archived versions',
                    modifiedAt: iso(500),
                    ownerLabel: ownerCompany,
                    ...companyDocMeta({
                        tags: ['Archive'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
                {
                    id: `${p}file1`,
                    parentId: `${p}f1`,
                    type: 'file',
                    name: 'Employee handbook 2026.pdf',
                    kind: 'pdf',
                    sizeLabel: '3.8 MB',
                    modifiedAt: iso(15),
                    ownerLabel: ownerCompany,
                    mockOutgoingRequest: 'access',
                    ...companyDocMeta({
                        tags: ['Handbook', 'HR'],
                        secondaryOwnerLabels: [
                            'Legal · Compliance',
                            'VP People · Morgan Yu',
                        ],
                        approvalStatusLabel: 'Pending',
                        accessMode: 'private',
                    }),
                },
                {
                    id: `${p}file2`,
                    parentId: `${p}f2`,
                    type: 'file',
                    name: 'Logo package.zip.pdf',
                    kind: 'pdf',
                    sizeLabel: '512 KB',
                    modifiedAt: iso(6),
                    ownerLabel: ownerCompany,
                    mockOutgoingRequest: 'upload',
                    ...companyDocMeta({
                        tags: ['Brand', 'Logo'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
                {
                    id: `${p}file3`,
                    parentId: null,
                    type: 'file',
                    name: 'All-hands recording notes.docx',
                    kind: 'docx',
                    sizeLabel: '95 KB',
                    modifiedAt: iso(2),
                    ownerLabel: ownerCompany,
                    mockOutgoingRequest: 'none',
                    ...companyDocMeta({ tags: ['Communications'] }),
                },
                {
                    id: `${p}file4`,
                    parentId: `${p}f3`,
                    type: 'file',
                    name: 'Handbook 2024.pdf',
                    kind: 'pdf',
                    sizeLabel: '3.1 MB',
                    modifiedAt: iso(380),
                    ownerLabel: ownerCompany,
                    ...companyDocMeta({
                        tags: ['Handbook', 'Archive'],
                        approvalStatusLabel: 'Approved',
                    }),
                },
                {
                    id: `${p}file5`,
                    parentId: `${p}f2`,
                    type: 'file',
                    name: 'Slide master.pptx',
                    kind: 'pptx',
                    sizeLabel: '890 KB',
                    modifiedAt: iso(30),
                    ownerLabel: ownerCompany,
                    ...companyDocMeta({ tags: ['Brand', 'Templates'] }),
                },
            ];
        default:
            return [];
    }
}

export function storageHintLabel(scope: DocumentsScope): string {
    switch (scope) {
        case 'my':
            return '1.2 GB used';
        case 'team':
            return '4.8 GB used';
        case 'branch':
            return '8.1 GB used';
        case 'company':
            return '42 GB used';
        default:
            return '';
    }
}
