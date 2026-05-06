<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardOverviewService $dashboardOverviewService,
    ) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->dashboardOverviewService->buildForUser($request->user()));
    }
}
