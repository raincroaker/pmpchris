<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeavePolicyRequest;
use App\Models\LeavePolicy;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;

class StoreLeavePolicyController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(StoreLeavePolicyRequest $request): RedirectResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 403);

        $validated = $request->validated();
        $useAccrual = $validated['use_accrual'] ?? false;
        $carryoverAllowed = $validated['carryover_allowed'] ?? false;
        $userId = $request->user()?->id;

        LeavePolicy::query()->create([
            'organization_id' => $organization->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'annual_entitlement' => $validated['annual_entitlement'],
            'use_accrual' => $useAccrual,
            'accrual_cadence' => $useAccrual ? ($validated['accrual_cadence'] ?? null) : null,
            'accrual_per_period' => $useAccrual ? ($validated['accrual_per_period'] ?? null) : null,
            'max_balance' => $validated['max_balance'] ?? null,
            'carryover_allowed' => $carryoverAllowed,
            'carryover_cap' => $carryoverAllowed ? ($validated['carryover_cap'] ?? null) : null,
            'paid' => $validated['paid'] ?? true,
            'requires_approval' => $validated['requires_approval'] ?? true,
            'applies_after_months' => $validated['applies_after_months'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        return redirect()->route('leave.policies');
    }
}
