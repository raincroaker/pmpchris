<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPositionsRequest;
use App\Models\Position;
use App\Services\BranchContextService;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PositionsIndexController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(IndexPositionsRequest $request): Response
    {
        $validated = $request->validated();
        $organization = $this->branchContextService->defaultOrganization();

        $perPage = (int) $validated['per_page'];
        $page = (int) $validated['page'];
        $search = isset($validated['search']) && $validated['search'] !== ''
            ? (string) $validated['search']
            : '';

        $filters = [
            'search' => $search,
            'status' => (string) $validated['status'],
            'sort' => (string) $validated['sort'],
            'direction' => (string) $validated['direction'],
            'per_page' => $perPage,
        ];

        if ($organization === null) {
            $emptyPaginator = new LengthAwarePaginator(
                [],
                0,
                $perPage,
                $page,
                ['path' => $request->url(), 'pageName' => 'page'],
            );
            $emptyPaginator->withQueryString();

            return Inertia::render('Positions/Index', [
                'positions' => $emptyPaginator,
                'filters' => $filters,
                'organization' => null,
            ]);
        }

        $query = Position::query()
            ->where('organization_id', $organization->id)
            ->when($validated['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($validated['status'] === 'inactive', fn ($q) => $q->where('is_active', false));

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $sortColumn = match ($validated['sort']) {
            'code' => 'code',
            'title' => 'title',
            'created_at' => 'created_at',
            'id' => 'id',
            default => 'code',
        };

        $direction = $validated['direction'] === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $direction);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        return Inertia::render('Positions/Index', [
            'positions' => $paginator,
            'filters' => $filters,
            'organization' => [
                'id' => (int) $organization->id,
                'code' => (string) $organization->code,
                'name' => (string) $organization->name,
            ],
        ]);
    }
}
