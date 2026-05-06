<?php

namespace App\Http\Requests;

use App\Enums\EmployeeHrRecordStatus;
use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Services\ScheduleAssignmentAccessService;
use App\Support\EmployeeBranchDirectoryFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeOvertimeRequest extends FormRequest
{
    use AuthorizesTeamHrLeaveOvertimeRecords;

    public function authorize(): bool
    {
        if (! $this->userMayMutateTeamHrRecords()) {
            return false;
        }

        $organization = $this->defaultOrganization();

        return $organization !== null && $this->workspaceBranchRootId() !== null;
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

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($orgId, $branchRootId, $today): void {
                    $query->whereNull('deleted_at');
                    EmployeeBranchDirectoryFilter::apply($query, $orgId, $branchRootId, $today);
                }),
            ],
            'organizational_unit_id' => ['nullable', 'integer', Rule::in($selectableUnitIds)],
            'policy_code' => [
                'required',
                'string',
                'max:40',
                Rule::exists('overtime_policies', 'code')->where(function ($query) use ($orgId): void {
                    $query->where('organization_id', $orgId)->whereNull('deleted_at');
                }),
            ],
            'ot_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
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
        ];
    }
}
