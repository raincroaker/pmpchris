<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;

class DestroyCompanyDocumentController extends Controller
{
    public function __invoke(
        DestroyCompanyDocumentRequest $request,
        CompanyDocument $companyDocument,
    ): JsonResponse {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocument->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $companyDocument->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
