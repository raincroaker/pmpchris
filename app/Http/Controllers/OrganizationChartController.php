<?php

namespace App\Http\Controllers;

use App\Services\OrganizationChartDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationChartController extends Controller
{
    public function __construct(
        private OrganizationChartDataService $organizationChartDataService,
    ) {}

    public function index(Request $request): Response
    {
        $payload = $this->organizationChartDataService->buildForRequest($request);

        return Inertia::render('OrganizationChart', [
            'orgChart' => $payload['orgChart'],
            'chartBranchId' => $payload['chartBranchId'],
            'chartBranches' => $payload['chartBranches'],
            'chartAreas' => $payload['chartAreas'],
            'chartCapabilities' => $payload['chartCapabilities'],
            'chartScope' => $payload['chartScope'],
            'chartUnitStatus' => $payload['chartUnitStatus'],
        ]);
    }
}
