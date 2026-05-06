<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyDocumentInternalMetadataRequest;
use App\Models\CompanyDocument;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Http\JsonResponse;

class UpdateCompanyDocumentInternalMetadataController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateCompanyDocumentInternalMetadataRequest $request, CompanyDocument $companyDocument): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        abort_unless(
            (int) $companyDocument->organization_id === (int) $organization->id,
            404
        );

        $validated = $request->validated();

        if (array_key_exists('submitted_at', $validated)) {
            $companyDocument->submitted_at = $validated['submitted_at'];
        }

        if (array_key_exists('decided_at', $validated)) {
            $companyDocument->decided_at = $validated['decided_at'];
        }

        if (array_key_exists('created_at', $validated)) {
            $companyDocument->created_at = $validated['created_at'];
        }

        if (array_key_exists('updated_at', $validated)) {
            $companyDocument->updated_at = $validated['updated_at'];
        }

        if (array_key_exists('status', $validated)) {
            $companyDocument->status = $validated['status'];
        }

        if (array_key_exists('access_mode', $validated)) {
            $companyDocument->access_mode = $validated['access_mode'];
        }

        if (array_key_exists('decision_note', $validated)) {
            $companyDocument->decision_note = $validated['decision_note'];
        }

        if (array_key_exists('notes', $validated)) {
            $companyDocument->notes = $validated['notes'];
        }

        if (array_key_exists('tags', $validated)) {
            $companyDocument->tags = $validated['tags'];
        }

        $companyDocument->save();
        $companyDocument->refresh();

        return response()->json([
            'data' => CompanyDocumentsDrivePresenter::documentItem($companyDocument),
        ]);
    }
}
