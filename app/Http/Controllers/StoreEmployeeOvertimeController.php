<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeOvertimeRequest;
use App\Models\EmployeeOvertime;
use App\Models\OvertimePolicy;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;

class StoreEmployeeOvertimeController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function __invoke(StoreEmployeeOvertimeRequest $request): RedirectResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 404);

        $validated = $request->validated();
        $policy = OvertimePolicy::query()
            ->where('organization_id', $organization->id)
            ->where('code', $validated['policy_code'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        $userId = $request->user()?->id;
        $decidedAt = $validated['decided_at'] ?? null;

        EmployeeOvertime::query()->create([
            'organization_id' => $organization->id,
            'employee_id' => $validated['employee_id'],
            'organizational_unit_id' => $validated['organizational_unit_id'] ?? null,
            'overtime_policy_id' => $policy->id,
            'ot_date' => $validated['ot_date'],
            'hours' => $validated['hours'],
            'status' => $validated['status'],
            'submitted_at' => $validated['submitted_at'],
            'decided_at' => $decidedAt,
            'approver_employee_id' => $validated['approver_employee_id'],
            'reason' => $validated['reason'] ?? null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/overtime/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('overtime.team');
    }
}
