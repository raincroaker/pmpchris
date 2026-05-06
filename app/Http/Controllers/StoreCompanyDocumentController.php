<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentApprovalAudit;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class StoreCompanyDocumentController extends Controller
{
    public function __invoke(StoreCompanyDocumentRequest $request): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        $folderId = $request->input('folder_id');
        if ($folderId !== null) {
            $folderExists = CompanyDocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->whereKey((int) $folderId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $folderExists) {
                return response()->json([
                    'message' => 'Selected destination folder is invalid.',
                    'errors' => ['folder_id' => ['Selected destination folder is invalid.']],
                ], 422);
            }
        }

        $uploadedFile = $request->file('file');
        if ($uploadedFile === null) {
            return response()->json([
                'message' => 'A file upload is required.',
                'errors' => ['file' => ['A file upload is required.']],
            ], 422);
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $storedName = Str::uuid().($extension !== '' ? ".{$extension}" : '');
        $directory = "company-documents/{$organization->id}";
        $path = $uploadedFile->storeAs($directory, $storedName, 'local');

        $userId = $request->user()?->id;
        $status = 'approved';
        $submittedAt = now();

        $realPath = $uploadedFile->getRealPath();
        $checksum = is_string($realPath) && $realPath !== ''
            ? hash_file('sha256', $realPath)
            : null;

        $document = CompanyDocument::query()->create([
            'organization_id' => $organization->id,
            'folder_id' => $folderId !== null ? (int) $folderId : null,
            'original_name' => (string) $uploadedFile->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'path' => (string) $path,
            'mime_type' => (string) $uploadedFile->getClientMimeType(),
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => (int) $uploadedFile->getSize(),
            'checksum_sha256' => $checksum,
            'uploaded_by_user_id' => $userId,
            'access_mode' => 'private',
            'status' => $status,
            'submitted_at' => $submittedAt,
            'decided_at' => $submittedAt,
            'decided_by_user_id' => $userId,
            'decision_note' => null,
            'tags' => $request->input('tags', []),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ]);

        CompanyDocumentApprovalAudit::query()->create([
            'company_document_id' => $document->id,
            'action' => 'auto_approved',
            'actor_user_id' => $userId,
            'note' => 'Auto-approved by company document administrator upload.',
            'metadata' => ['status' => $status, 'access_mode' => 'private'],
            'created_at' => now(),
        ]);

        $document->load(['uploadedByUser:id,name', 'decidedByUser:id,name']);

        return response()->json([
            'data' => CompanyDocumentsDrivePresenter::documentItem($document),
        ], 201);
    }
}
