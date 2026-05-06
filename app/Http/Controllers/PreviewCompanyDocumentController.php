<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Services\BranchContextService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PreviewCompanyDocumentController extends Controller
{
    public function __invoke(
        PreviewCompanyDocumentRequest $request,
        CompanyDocument $companyDocument,
    ): Response {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocument->organization_id !== (int) $organization->id) {
            abort(404);
        }

        return Storage::disk((string) $companyDocument->disk)->response(
            (string) $companyDocument->path,
            (string) $companyDocument->original_name,
            ['Content-Disposition' => 'inline; filename="'.$companyDocument->original_name.'"'],
        );
    }
}
