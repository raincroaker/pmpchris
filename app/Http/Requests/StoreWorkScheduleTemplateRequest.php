<?php

namespace App\Http\Requests;

use App\Enums\WorkScheduleClockPattern;
use App\Http\Requests\Concerns\AuthorizesWorkScheduleTemplateMutation;
use App\Models\Organization;
use App\Services\BranchContextService;
use App\Support\WorkScheduleDefinitionValidator;
use App\Support\WorkScheduleTemplateRulesValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkScheduleTemplateRequest extends FormRequest
{
    use AuthorizesWorkScheduleTemplateMutation;

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = app(BranchContextService::class)->defaultOrganization();

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('work_schedule_templates', 'name')->where(
                    fn ($query) => $query->where('organization_id', $organization instanceof Organization ? $organization->id : 0),
                ),
            ],
            'clock_pattern' => ['required', Rule::enum(WorkScheduleClockPattern::class)],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'string', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'segments' => ['nullable'],
            'time_in' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'time_out' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'is_overnight' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'unpaid_break_minutes' => ['required', 'integer', 'min:0'],
            'grace_late_arrival_minutes' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attendance_rules' => ['sometimes', 'nullable', 'array'],
            'overtime_rules' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'is_overnight' => filter_var($this->input('is_overnight'), FILTER_VALIDATE_BOOLEAN),
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)
                : true,
        ]);

        $pattern = $this->input('clock_pattern');
        if ($pattern === WorkScheduleClockPattern::SinglePair->value) {
            $this->merge(['segments' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();

            WorkScheduleDefinitionValidator::validate(
                $data,
                fn (string $message) => $validator->errors()->add('definition', $message)
            );

            WorkScheduleTemplateRulesValidator::validateAttendanceRules(
                isset($data['attendance_rules']) && is_array($data['attendance_rules'])
                    ? $data['attendance_rules']
                    : null,
                fn (string $message) => $validator->errors()->add('attendance_rules', $message)
            );
            WorkScheduleTemplateRulesValidator::validateOvertimeRules(
                isset($data['overtime_rules']) && is_array($data['overtime_rules'])
                    ? $data['overtime_rules']
                    : null,
                fn (string $message) => $validator->errors()->add('overtime_rules', $message)
            );
        });
    }
}
