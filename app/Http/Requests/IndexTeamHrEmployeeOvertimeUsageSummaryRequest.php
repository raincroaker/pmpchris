<?php

namespace App\Http\Requests;

use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamHrEmployeeOvertimeUsageSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EmployeeTeamHrPagesAccess::class)->allows($this->user(), $this);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = app(BranchContextService::class)->defaultOrganization();
        $orgId = $organization !== null ? (int) $organization->id : 0;

        return [
            'chart_branch_id' => ['required', 'integer', 'min:1'],
            'unit_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'policy_code' => [
                'required',
                'string',
                'max:40',
                Rule::exists('overtime_policies', 'code')->where(function ($query) use ($orgId): void {
                    $query->where('organization_id', $orgId)->whereNull('deleted_at');
                }),
            ],
            'exclude_employee_overtime_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function chartBranchId(): int
    {
        return (int) $this->integer('chart_branch_id');
    }

    public function unitId(): int
    {
        return (int) $this->integer('unit_id');
    }

    public function employeeId(): int
    {
        return (int) $this->integer('employee_id');
    }

    public function policyCodeTrimmed(): string
    {
        return trim((string) $this->validated('policy_code'));
    }

    public function excludeEmployeeOvertimeId(): ?int
    {
        $v = $this->input('exclude_employee_overtime_id');

        return $v === null || $v === '' ? null : (int) $v;
    }
}
