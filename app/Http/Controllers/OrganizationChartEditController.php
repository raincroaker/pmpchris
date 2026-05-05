<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOrganizationChartEditRequest;
use App\Services\OrganizationChartEditStructureDataService;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationChartEditController extends Controller
{
    public function __construct(
        private OrganizationChartEditStructureDataService $dataService,
    ) {}

    public function __invoke(IndexOrganizationChartEditRequest $request): Response
    {
        $payload = $this->dataService->build(
            $request->search(),
            $request->sort(),
            $request->direction(),
            $request->perPage(),
            $request->rootUnitFilter(),
            $request->user(),
        );

        return Inertia::render('OrganizationChart/Edit', [
            'organization' => $payload['organization'],
            'areas' => $payload['areas'],
            'unitTypes' => $payload['unitTypes'],
            'parentTypeOptions' => $payload['parentTypeOptions'],
            'rootUnitFilterOptions' => $payload['rootUnitFilterOptions'],
            'filters' => $payload['filters'],
        ]);
    }
}
