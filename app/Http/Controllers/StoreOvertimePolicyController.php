<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimePolicyRequest;
use App\Models\OvertimePolicy;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;

class StoreOvertimePolicyController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(StoreOvertimePolicyRequest $request): RedirectResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 403);

        $validated = $request->validated();
        $userId = $request->user()?->id;

        OvertimePolicy::query()->create([
            'organization_id' => $organization->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'context' => $validated['context'],
            'rate_multiplier' => $validated['rate_multiplier'],
            'daily_threshold_hours' => $validated['daily_threshold_hours'],
            'daily_cap_hours' => $validated['daily_cap_hours'] ?? null,
            'weekly_cap_hours' => $validated['weekly_cap_hours'] ?? null,
            'requires_approval' => $validated['requires_approval'] ?? true,
            'minimum_lead_time_hours' => $validated['minimum_lead_time_hours'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        return redirect()->route('overtime.policies');
    }
}
