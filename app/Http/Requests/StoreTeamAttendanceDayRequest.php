<?php

namespace App\Http\Requests;

use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\WorkScheduleTemplate;
use App\Services\ScheduleAssignmentAccessService;
use App\Support\EmployeeBranchDirectoryFilter;
use App\Support\TeamAttendanceSegmentsTemplateValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeamAttendanceDayRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
            'work_date' => ['required', 'date'],
            'work_schedule_template_id' => [
                'required',
                'integer',
                Rule::exists('work_schedule_templates', 'id')->where(function ($query) use ($orgId): void {
                    $query->where('organization_id', $orgId)->whereNull('deleted_at');
                }),
            ],
            'segments' => ['required', 'array', 'min:1', 'max:8'],
            'segments.*.label' => ['required', 'string', 'max:80'],
            'segments.*.scheduled_in' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'segments.*.scheduled_out' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'segments.*.actual_in' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'segments.*.actual_out' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'variance_label' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $organization = $this->defaultOrganization();
            if ($organization === null) {
                return;
            }

            $employee = Employee::query()
                ->whereKey((int) $this->input('employee_id'))
                ->first();
            if ($employee === null) {
                return;
            }

            $attendanceProfileId = $employee->attendance_id;
            if (! is_string($attendanceProfileId) || trim($attendanceProfileId) === '') {
                $v->errors()->add('employee_id', 'This employee needs an attendance ID on Employee Schedules before recording a day.');
            }

            $templateId = (int) $this->input('work_schedule_template_id');
            if ($employee->work_schedule_template_id === null || (int) $employee->work_schedule_template_id !== $templateId) {
                $v->errors()->add('work_schedule_template_id', 'The selected work schedule must match the employee’s assigned template.');
            }

            $template = WorkScheduleTemplate::query()
                ->whereKey($templateId)
                ->where('organization_id', $organization->id)
                ->whereNull('deleted_at')
                ->first();
            if ($template === null) {
                return;
            }

            $segments = $this->input('segments');
            if (! is_array($segments)) {
                return;
            }

            if ($template->clock_pattern === WorkScheduleClockPattern::SinglePair) {
                if (count($segments) !== 1) {
                    $v->errors()->add('segments', 'Simple attendance uses exactly one segment.');
                }
            } elseif ($template->clock_pattern === WorkScheduleClockPattern::SplitSessions) {
                if (count($segments) !== 2) {
                    $v->errors()->add('segments', 'Split-session attendance uses exactly two segments.');
                }
            }

            if ($v->errors()->has('segments')) {
                return;
            }

            TeamAttendanceSegmentsTemplateValidator::validate(
                $segments,
                $template,
                function (string $message) use ($v): void {
                    $v->errors()->add('segments', $message);
                }
            );

            $workDate = (string) $this->input('work_date');
            $exists = EmployeeAttendanceDay::query()
                ->where('employee_id', (int) $this->input('employee_id'))
                ->whereDate('work_date', $workDate)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                $v->errors()->add('work_date', 'An attendance day already exists for this employee on this date.');
            }
        });
    }
}
