<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyDocumentFolderRequest;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;

class StoreCompanyDocumentFolderController extends Controller
{
    public function __invoke(StoreCompanyDocumentFolderRequest $request): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        $parentId = $request->input('parent_id');
        if ($parentId !== null) {
            $parentExists = CompanyDocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->whereKey((int) $parentId)
                ->whereNull('deleted_at')
                ->exists();
            if (! $parentExists) {
                return response()->json([
                    'message' => 'Selected parent folder is invalid.',
                    'errors' => ['parent_id' => ['Selected parent folder is invalid.']],
                ], 422);
            }
        }

        $userId = $request->user()?->id;

        $folder = CompanyDocumentFolder::query()->create([
            'organization_id' => $organization->id,
            'parent_id' => $parentId !== null ? (int) $parentId : null,
            'name' => trim((string) $request->input('name')),
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        $folder->load(['createdByUser:id,name']);

        return response()->json([
            'data' => CompanyDocumentsDrivePresenter::folderItem($folder),
        ], 201);
    }
}
