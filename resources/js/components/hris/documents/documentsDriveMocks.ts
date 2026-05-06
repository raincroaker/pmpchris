import type {
    DocumentsScope,
    DriveItem,
} from './documentsDriveTypes';

/**
 * Deterministic mock tree per HR document scope (flat list, parentId-linked).
 */
export function createInitialItems(scope: DocumentsScope): DriveItem[] {
    void scope;

    return [];
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
