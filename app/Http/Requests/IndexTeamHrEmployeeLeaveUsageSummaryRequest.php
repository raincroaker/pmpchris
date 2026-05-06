<?php

namespace App\Http\Requests;

use App\Services\BranchContextService;
use App\Services\EmployeeTeamHrPagesAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamHrEmployeeLeaveUsageSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EmployeeTeamHrPagesAccess::class)->allows($this->user(), $this);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = app(BranchContextService::class)->defaultOrganization();

        $orgId = $organization !== null ? (int) $organization->id : 0;

        return [
            'chart_branch_id' => ['required', 'integer', 'min:1'],
            'unit_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'leave_type_code' => [
                'required',
                'string',
                'max:40',
                Rule::exists('leave_policies', 'code')->where(function ($query) use ($orgId): void {
                    $query->where('organization_id', $orgId)->whereNull('deleted_at');
                }),
            ],
            'exclude_employee_leave_id' => ['nullable', 'integer', 'min:1'],
            'draft_dates' => ['sometimes', 'array', 'max:400'],
            'draft_dates.*' => ['date_format:Y-m-d'],
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

    public function leaveTypeCodeTrimmed(): string
    {
        return trim((string) $this->validated('leave_type_code'));
    }

    /**
     * @return list<string>
     */
    public function draftDatesYmd(): array
    {
        $raw = $this->input('draft_dates', []);

        return is_array($raw)
            ? array_values(array_map(static fn ($d): string => substr((string) $d, 0, 10), $raw))
            : [];
    }

    public function excludeEmployeeLeaveId(): ?int
    {
        $v = $this->input('exclude_employee_leave_id');

        return $v === null || $v === '' ? null : (int) $v;
    }
}
