<?php

namespace App\Http\Controllers;

use App\Models\WorkScheduleTemplate;
use App\Services\BranchContextService;
use Inertia\Inertia;
use Inertia\Response;

class WorkSchedulesController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(): Response
    {
        $organization = $this->branchContextService->defaultOrganization();

        $templates = [];
        if ($organization !== null) {
            $templates = WorkScheduleTemplate::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get()
                ->map(fn (WorkScheduleTemplate $template) => $template->toShiftRuleArray())
                ->values()
                ->all();
        }

        return Inertia::render('Attendance/Shifts', [
            'workScheduleTemplates' => $templates,
        ]);
    }
}
