<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateCompanyDocumentController extends Controller
{
    public function __invoke(
        UpdateCompanyDocumentRequest $request,
        CompanyDocument $companyDocument,
    ): JsonResponse {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocument->organization_id !== (int) $organization->id) {
            abort(404);
        }

        if (! $request->exists('name') && ! $request->exists('folder_id')) {
            throw ValidationException::withMessages([
                'name' => ['Provide at least one field to update.'],
            ]);
        }

        if ($request->exists('name')) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => ['Document name is required.'],
                ]);
            }
            $companyDocument->original_name = $name;
        }

        if ($request->exists('folder_id')) {
            $folderId = $request->input('folder_id');
            if ($folderId !== null && $folderId !== '') {
                $folder = CompanyDocumentFolder::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey((int) $folderId)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $folder instanceof CompanyDocumentFolder) {
                    throw ValidationException::withMessages([
                        'folder_id' => ['Selected destination folder is invalid.'],
                    ]);
                }

                $companyDocument->folder_id = (int) $folder->id;
            } else {
                $companyDocument->folder_id = null;
            }
        }

        $companyDocument->save();
        $companyDocument->load(['uploadedByUser:id,name', 'decidedByUser:id,name']);

        return response()->json([
            'data' => CompanyDocumentsDrivePresenter::documentItem($companyDocument),
        ]);
    }
}
