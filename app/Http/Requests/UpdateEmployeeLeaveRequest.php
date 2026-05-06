<?php

namespace App\Http\Requests;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Http\Requests\Concerns\ValidatesExplicitEmployeeLeaveDays;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Services\ScheduleAssignmentAccessService;
use App\Support\EmployeeBranchDirectoryFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeLeaveRequest extends FormRequest
{
    use AuthorizesTeamHrLeaveOvertimeRecords;
    use ValidatesExplicitEmployeeLeaveDays;

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

        return (int) $leave->organization_id === (int) $organization->id
            && $this->employeeVisibleUnderWorkspace((int) $leave->employee_id);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->defaultOrganization();
        $orgId = (int) $organization?->id;
        $branchRootId = (int) $this->workspaceBranchRootId();
        $today = now()->toDateString();

        $selectableUnitIds = app(ScheduleAssignmentAccessService::class)->teamHrFormSelectableUnitIds($orgId, $branchRootId);

        return array_merge([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($orgId, $branchRootId, $today): void {
                    $query->whereNull('deleted_at');
                    EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);
                }),
            ],
            'organizational_unit_id' => ['nullable', 'integer', Rule::in($selectableUnitIds)],
            'leave_type_code' => [
                'required',
                'string',
                'max:40',
                Rule::exists('leave_policies', 'code')->where(function ($query) use ($orgId): void {
                    $query->where('organization_id', $orgId)->whereNull('deleted_at');
                }),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day_start' => ['sometimes', 'boolean'],
            'is_half_day_end' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(EmployeeHrRecordStatus::class)],
            'submitted_at' => ['required', 'date'],
            'decided_at' => ['nullable', 'date'],
            'approver_employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($orgId, $branchRootId, $today): void {
                    $query->whereNull('deleted_at');
                    EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);
                }),
            ],
            'reason' => ['nullable', 'string', 'max:65535'],
        ], $this->explicitEmployeeLeaveDaysRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateExplicitEmployeeLeaveDaysPayload($v);
        });
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
