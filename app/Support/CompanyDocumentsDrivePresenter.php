<?php

namespace App\Support;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentFolder;

class CompanyDocumentsDrivePresenter
{
    /**
     * @return array{
     *   id: string,
     *   parentId: string|null,
     *   type: 'folder',
     *   name: string,
     *   modifiedAt: string,
     *   ownerLabel: string,
     *   visibilityLabel: string,
     *   accessMode: string,
     *   approvalStatusLabel: string,
     *   approverLabel: string|null
     * }
     */
    public static function folderItem(CompanyDocumentFolder $folder): array
    {
        return [
            'id' => self::folderUiId((int) $folder->id),
            'parentId' => $folder->parent_id !== null
                ? self::folderUiId((int) $folder->parent_id)
                : null,
            'type' => 'folder',
            'name' => (string) $folder->name,
            'modifiedAt' => $folder->updated_at?->toISOString() ?? now()->toISOString(),
            'ownerLabel' => $folder->createdByUser?->name ?? 'System',
            'visibilityLabel' => 'Company library',
            'accessMode' => 'private',
            'approvalStatusLabel' => 'Approved',
            'approverLabel' => null,
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   parentId: string|null,
     *   type: 'file',
     *   name: string,
     *   kind: 'pdf'|'docx'|'xlsx'|'pptx',
     *   sizeLabel: string,
     *   modifiedAt: string,
     *   uploadedAt: string,
     *   ownerLabel: string,
     *   visibilityLabel: string,
     *   accessMode: string,
     *   approvalStatusLabel: string,
     *   approverLabel: string|null,
     *   tags: list<string>,
     *   notesLabel: string|null
     * }
     */
    public static function documentItem(CompanyDocument $document): array
    {
        $rawTags = $document->tags;
        $tags = is_array($rawTags)
            ? array_values(array_filter(array_map(
                fn (mixed $row): string => trim((string) $row),
                $rawTags
            ), fn (string $row): bool => $row !== ''))
            : [];

        return [
            'id' => self::documentUiId((int) $document->id),
            'parentId' => $document->folder_id !== null
                ? self::folderUiId((int) $document->folder_id)
                : null,
            'type' => 'file',
            'name' => (string) $document->original_name,
            'kind' => self::documentKindFromExtension($document->extension),
            'sizeLabel' => self::formatBytes((int) $document->size_bytes),
            'modifiedAt' => $document->updated_at?->toISOString() ?? now()->toISOString(),
            'uploadedAt' => $document->submitted_at?->toISOString() ?? $document->created_at?->toISOString() ?? now()->toISOString(),
            'ownerLabel' => $document->uploadedByUser?->name ?? 'System',
            'visibilityLabel' => 'Company library',
            'accessMode' => (string) $document->access_mode,
            'approvalStatusLabel' => self::statusLabel((string) $document->status),
            'approverLabel' => $document->decidedByUser?->name,
            'tags' => $tags,
            'notesLabel' => $document->notes !== null && $document->notes !== ''
                ? (string) $document->notes
                : null,
        ];
    }

    public static function folderUiId(int $id): string
    {
        return "folder-{$id}";
    }

    public static function documentUiId(int $id): string
    {
        return "file-{$id}";
    }

    /**
     * @return 'pdf'|'docx'|'xlsx'|'pptx'
     */
    private static function documentKindFromExtension(?string $extension): string
    {
        $ext = strtolower(trim((string) $extension));
        if ($ext === 'pdf') {
            return 'pdf';
        }
        if ($ext === 'doc' || $ext === 'docx') {
            return 'docx';
        }
        if ($ext === 'xls' || $ext === 'xlsx') {
            return 'xlsx';
        }
        if ($ext === 'ppt' || $ext === 'pptx') {
            return 'pptx';
        }

        return 'pdf';
    }

    private static function statusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'approved' => 'Approved',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => 'Approved',
        };
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
