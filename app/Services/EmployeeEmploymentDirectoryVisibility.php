<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Support\EmployeeBranchDirectoryFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class EmployeeEmploymentDirectoryVisibility
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * Whether the employee is visible under the same rules as the Employees index / employment history
     * (directory scope, include historical links).
     */
    public function isEmployeeVisibleInDirectory(Request $request, Employee $employee): bool
    {
        return $this->matchesDirectoryEmployeeId($request, (int) $employee->getKey());
    }

    /**
     * Whether the employment's employee is visible under the same rules as the employment history index
     * (directory scope, include historical links).
     */
    public function isEmploymentEmployeeVisible(Request $request, EmployeeEmployment $employment): bool
    {
        return $this->matchesDirectoryEmployeeId($request, (int) $employment->employee_id);
    }

    private function matchesDirectoryEmployeeId(Request $request, int $employeeId): bool
    {
        $organization = $this->branchContextService->defaultOrganization();
        if ($organization === null) {
            return false;
        }

        $todayString = Carbon::today()->toDateString();
        $orgId = (int) $organization->id;
        $branchRootId = $this->resolveBranchRootIdForEmployeeDirectory($request, $organization);

        $query = Employee::query()
            ->select('employees.id')
            ->whereKey($employeeId)
            ->whereNull('employees.deleted_at');

        EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $todayString, true);

        return $query->exists();
    }

    private function resolveBranchRootIdForEmployeeDirectory(Request $request, Organization $organization): ?int
    {
        $user = $request->user();
        if ($user === null || ! $user->mustSelectBranch()) {
            return null;
        }

        $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);
        if ($branchId <= 0 || ! $this->branchContextService->isValidSessionBranchId($branchId)) {
            return null;
        }

        $root = $this->branchContextService->findSelectableBranchRoot($branchId, $organization);
        if ($root === null || (int) $root->organization_id !== (int) $organization->id) {
            return null;
        }

        return $branchId;
    }
}
