<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCompanyDocumentsRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;

class CompanyDocumentsIndexController extends Controller
{
    public function __invoke(IndexCompanyDocumentsRequest $request): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        $folders = CompanyDocumentFolder::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->with(['createdByUser:id,name'])
            ->orderBy('name')
            ->get();

        $documents = CompanyDocument::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->with(['uploadedByUser:id,name', 'decidedByUser:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => [
                ...$folders->map(fn (CompanyDocumentFolder $folder): array => CompanyDocumentsDrivePresenter::folderItem($folder))->all(),
                ...$documents->map(fn (CompanyDocument $document): array => CompanyDocumentsDrivePresenter::documentItem($document))->all(),
            ],
        ]);
    }
}
