<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyDocumentFolderRequest;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateCompanyDocumentFolderController extends Controller
{
    public function __invoke(
        UpdateCompanyDocumentFolderRequest $request,
        CompanyDocumentFolder $companyDocumentFolder,
    ): JsonResponse {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocumentFolder->organization_id !== (int) $organization->id) {
            abort(404);
        }

        if (! $request->exists('name') && ! $request->exists('parent_id')) {
            throw ValidationException::withMessages([
                'name' => ['Provide at least one field to update.'],
            ]);
        }

        if ($request->exists('name')) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => ['Folder name is required.'],
                ]);
            }
            $companyDocumentFolder->name = $name;
        }

        if ($request->exists('parent_id')) {
            $parentId = $request->input('parent_id');
            if ($parentId === null || $parentId === '') {
                $companyDocumentFolder->parent_id = null;
            } else {
                $targetParent = CompanyDocumentFolder::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey((int) $parentId)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $targetParent instanceof CompanyDocumentFolder) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['Selected parent folder is invalid.'],
                    ]);
                }

                if ((int) $targetParent->id === (int) $companyDocumentFolder->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['Folder cannot be moved into itself.'],
                    ]);
                }

                $companyDocumentFolder->parent_id = (int) $targetParent->id;
            }
        }

        $companyDocumentFolder->updated_by_user_id = $request->user()?->id;
        $companyDocumentFolder->save();
        $companyDocumentFolder->load(['createdByUser:id,name']);

        return response()->json([
            'data' => CompanyDocumentsDrivePresenter::folderItem($companyDocumentFolder),
        ]);
    }
}
