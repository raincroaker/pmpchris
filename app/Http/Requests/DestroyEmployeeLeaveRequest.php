<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Support\EmployeeBranchDirectoryFilter;
use Illuminate\Foundation\Http\FormRequest;

class DestroyEmployeeLeaveRequest extends FormRequest
{
    use AuthorizesTeamHrLeaveOvertimeRecords;

    public function authorize(): bool
    {
        if (! $this->userMayMutateTeamHrRecords()) {
            return false;
        }

        $organization = $this->defaultOrganization();
        if ($organization === null || $this->workspaceBranchRootId() === null) {
            return false;
        }

        $leave = $this->route('employeeLeave');
        if (! $leave instanceof EmployeeLeave) {
            return false;
        }

        if ((int) $leave->organization_id !== (int) $organization->id) {
            return false;
        }

        return $this->employeeVisibleUnderWorkspace((int) $leave->employee_id);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    private function employeeVisibleUnderWorkspace(int $employeeId): bool
    {
        $organization = $this->defaultOrganization();
        $branchRootId = $this->workspaceBranchRootId();
        if ($organization === null || $branchRootId === null) {
            return false;
        }

        return Employee::query()
            ->whereKey($employeeId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($organization, $branchRootId): void {
                EmployeeBranchDirectoryFilter::apply(
                    $query,
                    (int) $organization->id,
                    $branchRootId,
                    now()->toDateString(),
                );
            })
            ->exists();
    }
}
