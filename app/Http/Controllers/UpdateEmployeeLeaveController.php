<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeLeaveRequest;
use App\Models\EmployeeLeave;
use App\Models\LeavePolicy;
use App\Services\BranchContextService;
use App\Services\EmployeeLeaveDaysSyncService;
use Illuminate\Http\RedirectResponse;

class UpdateEmployeeLeaveController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private EmployeeLeaveDaysSyncService $employeeLeaveDaysSyncService,
    ) {}

    public function __invoke(UpdateEmployeeLeaveRequest $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $organization = $this->branchContextService->defaultOrganization();
        abort_if($organization === null, 404);

        $validated = $request->validated();
        $policy = LeavePolicy::query()
            ->where('organization_id', $organization->id)
            ->where('code', $validated['leave_type_code'])
            ->whereNull('deleted_at')
            ->firstOrFail();

        $userId = $request->user()?->id;
        $decidedAt = $validated['decided_at'] ?? null;

        $employeeLeave->update([
            'employee_id' => $validated['employee_id'],
            'organizational_unit_id' => $validated['organizational_unit_id'] ?? null,
            'leave_policy_id' => $policy->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_half_day_start' => $validated['is_half_day_start'] ?? false,
            'is_half_day_end' => $validated['is_half_day_end'] ?? false,
            'status' => $validated['status'],
            'submitted_at' => $validated['submitted_at'],
            'decided_at' => $decidedAt,
            'approver_employee_id' => $validated['approver_employee_id'],
            'reason' => $validated['reason'] ?? null,
            'updated_by_user_id' => $userId,
        ]);

        /** @var list<array{date: string, is_half_day?: bool}>|null $explicitLeaveDays */
        $explicitLeaveDays = array_key_exists('leave_days', $validated)
            ? $validated['leave_days']
            : null;
        $this->employeeLeaveDaysSyncService->sync($employeeLeave, $explicitLeaveDays);

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/leave/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('leave.team');
    }
}
