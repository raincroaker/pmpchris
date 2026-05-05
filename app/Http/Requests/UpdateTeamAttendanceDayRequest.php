<?php

namespace App\Http\Requests;

use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Models\EmployeeAttendanceDay;
use App\Support\TeamAttendanceSegmentsTemplateValidator;
use App\Models\WorkScheduleTemplate;
use App\Services\ScheduleAssignmentAccessService;
use App\Services\TeamAttendanceDayMutationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamAttendanceDayRequest extends FormRequest
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

        $day = $this->employeeAttendanceDay();
        if (! $day instanceof EmployeeAttendanceDay) {
            return false;
        }

        if ((int) $day->organization_id !== (int) $organization->id) {
            return false;
        }

        return app(TeamAttendanceDayMutationService::class)->isAttendanceDayMutableInWorkspace($day, $this);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->defaultOrganization();
        $orgId = (int) $organization?->id;
        $branchRootId = (int) $this->workspaceBranchRootId();
        $day = $this->employeeAttendanceDay();
        abort_if($day === null, 404);

        $selectableUnitIds = app(ScheduleAssignmentAccessService::class)->teamHrFormSelectableUnitIds($orgId, $branchRootId);

        return [
            'employee_id' => ['required', 'integer', Rule::in([(int) $day->employee_id])],
            'organizational_unit_id' => ['nullable', 'integer', Rule::in($selectableUnitIds)],
            'work_date' => [
                'required',
                'date',
                Rule::unique('employee_attendance_days')
                    ->where(static fn ($query) => $query
                        ->where('employee_id', (int) $day->employee_id)
                        ->whereNull('deleted_at'))
                    ->ignore($day->id),
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

            $day = $this->employeeAttendanceDay();
            $template = $day->workScheduleTemplate ?? WorkScheduleTemplate::query()
                ->whereKey((int) $day->work_schedule_template_id)
                ->withTrashed()
                ->first();
            if ($template === null) {
                $v->errors()->add('segments', 'The stored work schedule template is missing.');

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
        });
    }

    private function employeeAttendanceDay(): ?EmployeeAttendanceDay
    {
        $value = $this->route('employeeAttendanceDay');

        return $value instanceof EmployeeAttendanceDay ? $value : null;
    }
}
