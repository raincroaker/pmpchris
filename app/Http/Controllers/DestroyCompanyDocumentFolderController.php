<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCompanyDocumentFolderRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class DestroyCompanyDocumentFolderController extends Controller
{
    public function __invoke(
        DestroyCompanyDocumentFolderRequest $request,
        CompanyDocumentFolder $companyDocumentFolder,
    ): JsonResponse {
        $organization = app(BranchContextService::class)->defaultOrganization();
        abort_if($organization === null, 422, 'Default organization is unavailable.');

        if ((int) $companyDocumentFolder->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $folderIds = $this->collectSubtreeIds($organization->id, (int) $companyDocumentFolder->id);

        CompanyDocument::query()
            ->where('organization_id', $organization->id)
            ->whereIn('folder_id', $folderIds)
            ->delete();

        CompanyDocumentFolder::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $folderIds)
            ->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return list<int>
     */
    private function collectSubtreeIds(int $organizationId, int $rootId): array
    {
        $ids = [$rootId];
        $cursor = 0;

        while ($cursor < count($ids)) {
            $chunk = array_slice($ids, $cursor, 250);
            $cursor += count($chunk);

            /** @var Collection<int, int> $childIds */
            $childIds = CompanyDocumentFolder::query()
                ->where('organization_id', $organizationId)
                ->whereIn('parent_id', $chunk)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            foreach ($childIds as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                }
            }
        }

        return $ids;
    }
}
