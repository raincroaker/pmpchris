<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCompanyDocumentsRequest;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentFolder;
use App\Services\BranchContextService;
use App\Support\CompanyDocumentsDrivePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CompanyDocumentsIndexController extends Controller
{
    public function __invoke(IndexCompanyDocumentsRequest $request): JsonResponse
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        if ($organization === null) {
            return response()->json(['data' => []]);
        }

        $validated = $request->validated();
        $query = trim((string) ($validated['q'] ?? ''));
        $type = (string) ($validated['type'] ?? 'all');
        $sortKey = (string) ($validated['sort_key'] ?? 'name');
        $sortOrder = (string) ($validated['sort_order'] ?? 'asc');
        $sortDirection = $sortOrder === 'desc' ? -1 : 1;

        $includeFolders = in_array($type, ['all', 'folder'], true);
        $includeDocuments = $type !== 'folder';

        $folders = collect();
        if ($includeFolders) {
            $folders = CompanyDocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at', 'and', false)
                ->when(
                    $query !== '',
                    fn (Builder $builder): Builder => $builder->where(
                        'name',
                        'like',
                        "%{$query}%"
                    )
                )
                ->with(['createdByUser:id,name'])
                ->get();
        }

        $documents = collect();
        if ($includeDocuments) {
            $documents = CompanyDocument::query()
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at', 'and', false)
                ->when(
                    $type !== 'all',
                    fn (Builder $builder): Builder => $builder->where(
                        'extension',
                        $type
                    )
                )
                ->when(
                    $query !== '',
                    fn (Builder $builder): Builder => $builder->where(
                        fn (Builder $nested): Builder => $nested
                            ->where('original_name', 'like', "%{$query}%")
                            ->orWhere('notes', 'like', "%{$query}%")
                    )
                )
                ->with(['uploadedByUser:id,name', 'decidedByUser:id,name'])
                ->get();
        }

        /** @var Collection<int, array<string, mixed>> $items */
        $items = collect([
            ...$folders->map(
                fn (CompanyDocumentFolder $folder): array => CompanyDocumentsDrivePresenter::folderItem($folder)
            )->all(),
            ...$documents->map(
                fn (CompanyDocument $document): array => CompanyDocumentsDrivePresenter::documentItem($document)
            )->all(),
        ])->sort(function (array $left, array $right) use ($sortKey, $sortDirection): int {
            if ($sortKey === 'modified') {
                $leftValue = strtotime((string) ($left['modifiedAt'] ?? '')) ?: 0;
                $rightValue = strtotime((string) ($right['modifiedAt'] ?? '')) ?: 0;
            } else {
                $leftValue = mb_strtolower(trim((string) ($left['name'] ?? '')));
                $rightValue = mb_strtolower(trim((string) ($right['name'] ?? '')));
            }

            if ($leftValue === $rightValue) {
                return 0;
            }

            return ($leftValue <=> $rightValue) * $sortDirection;
        })->values();

        return response()->json([
            'data' => [
                ...$items->all(),
            ],
        ]);
    }
}
