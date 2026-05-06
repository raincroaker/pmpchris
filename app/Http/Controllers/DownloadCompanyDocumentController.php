<?php

namespace App\Http\Controllers;

use App\Http\Requests\DownloadCompanyDocumentRequest;
use App\Models\CompanyDocument;
use App\Services\BranchContextService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadCompanyDocumentController extends Controller
{
    public function __invoke(
        DownloadCompanyDocumentRequest $request,
        CompanyDocument $companyDocument,
    ): StreamedResponse {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocument->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $disk = Storage::disk((string) $companyDocument->disk);
        $path = (string) $companyDocument->path;

        abort_unless($disk->exists($path), 404, 'Document file was not found.');

        return $disk->download($path, (string) $companyDocument->original_name);
    }
}
