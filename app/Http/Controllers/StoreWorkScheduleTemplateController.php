<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkScheduleTemplateRequest;
use App\Models\WorkScheduleTemplate;
use App\Services\BranchContextService;
use App\Support\WorkSchedulePayloadPreparer;
use Illuminate\Http\JsonResponse;

class StoreWorkScheduleTemplateController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(StoreWorkScheduleTemplateRequest $request): JsonResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 403);

        $validated = WorkSchedulePayloadPreparer::fromValidated($request->validated());

        $template = WorkScheduleTemplate::query()->create([
            ...$validated,
            'organization_id' => $organization->id,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => $template->toShiftRuleArray(),
        ], 201);
    }
}
