<?php

namespace App\Http\Requests;

use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Services\EmploymentWorkMutationAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeEmploymentDatesRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const SEPARATION_STATUSES = [
        EmployeeEmployment::STATUS_RESIGNED,
        EmployeeEmployment::STATUS_TERMINATED,
        EmployeeEmployment::STATUS_RETIRED,
        EmployeeEmployment::STATUS_CONTRACT_ENDED,
    ];

    public function authorize(EmploymentWorkMutationAccess $access): bool
    {
        $employment = $this->route('employment');
        if (! $employment instanceof EmployeeEmployment) {
            return false;
        }

        return $access->allows($this, $employment);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hire_date' => ['required', 'date'],
            'separation_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', Rule::in(EmployeeEmployment::STATUSES)],
            'separation_reason' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'current_password' => ['required', 'string', 'current_password'],
            'current_password_confirmation' => ['required', 'same:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hire_date.required' => 'Hire date is required.',
            'separation_date.after_or_equal' => 'Separation date must be on or after the hire date.',
            'employment_status.in' => 'Employment status is not valid.',
            'current_password.current_password' => 'The password does not match your account.',
            'current_password_confirmation.same' => 'Password confirmation must match.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employment = $this->route('employment');
            if (! $employment instanceof EmployeeEmployment) {
                return;
            }

            $employment->refresh();

            /** @var EmploymentWorkMutationAccess $employmentAccess */
            $employmentAccess = app(EmploymentWorkMutationAccess::class);

            if ($employment->employment_status === EmployeeEmployment::STATUS_ACTIVE
                && $employment->separation_date === null
                && ! $employmentAccess->mayRecordEmploymentSeparation($this->user())
                && $this->requestAttemptsSeparationRecording()) {
                $validator->errors()->add(
                    'separation_date',
                    'Recording separation requires HR Head or Super Admin access.',
                );

                return;
            }

            if ($employment->employment_status !== EmployeeEmployment::STATUS_ACTIVE
                || $employment->separation_date !== null) {
                $validator->errors()->add(
                    'employment',
                    'This employment record can no longer be edited. Separated employment is final.',
                );

                return;
            }

            $hireRaw = $this->input('hire_date');
            if (! is_string($hireRaw) || $hireRaw === '') {
                return;
            }

            try {
                $hireCarbon = Carbon::parse($hireRaw)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $separationRaw = $this->input('separation_date');
            $hasSeparationInput = is_string($separationRaw) && $separationRaw !== '';

            $statusRaw = $this->input('employment_status');
            $hasStatusInput = is_string($statusRaw) && $statusRaw !== '';

            $this->validateActiveBranch(
                $validator,
                $employment,
                $hireCarbon,
                $hasSeparationInput,
                $separationRaw,
                $hasStatusInput,
                $statusRaw,
            );
        });
    }

    /**
     * Whether the request body attempts to record a separation (branch HR managers are not allowed to).
     */
    private function requestAttemptsSeparationRecording(): bool
    {
        $separationRaw = $this->input('separation_date');
        if (is_string($separationRaw) && $separationRaw !== '') {
            return true;
        }

        $statusRaw = $this->input('employment_status');

        return is_string($statusRaw)
            && in_array($statusRaw, self::SEPARATION_STATUSES, true);
    }

    private function validateActiveBranch(
        Validator $validator,
        EmployeeEmployment $employment,
        Carbon $hireCarbon,
        bool $hasSeparationInput,
        mixed $separationRaw,
        bool $hasStatusInput,
        mixed $statusRaw,
    ): void {
        if ($hasSeparationInput) {
            if (! $hasStatusInput) {
                $validator->errors()->add(
                    'employment_status',
                    'Select an employment status when recording separation.',
                );

                return;
            }

            if (! is_string($statusRaw) || ! in_array($statusRaw, self::SEPARATION_STATUSES, true)) {
                $validator->errors()->add(
                    'employment_status',
                    'Choose resigned, terminated, retired, or contract ended when separating.',
                );

                return;
            }

            try {
                $separationCarbon = Carbon::parse((string) $separationRaw)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($separationCarbon->lt($hireCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after the hire date.',
                );

                return;
            }

            $this->assertAssignmentsAffiliationsPositionsAllowSeparationDates(
                $validator,
                $employment,
                $hireCarbon,
                $separationCarbon,
            );

            return;
        }

        // Hire-only adjustments (current employment remains active).
        if ($hasStatusInput && is_string($statusRaw)
            && $statusRaw !== EmployeeEmployment::STATUS_ACTIVE) {
            $validator->errors()->add(
                'employment_status',
                'Changing status requires recording a separation date. Use Record separation.',
            );

            return;
        }

        if ($this->hasNonEmptyString('separation_reason')) {
            $validator->errors()->add(
                'separation_reason',
                'Separation details are only saved when recording separation.',
            );

            return;
        }

        if ($this->hasNonEmptyString('notes')) {
            $validator->errors()->add(
                'notes',
                'Notes can only be saved when recording separation.',
            );

            return;
        }

        $this->assertHireVsAssignmentStarts($validator, $employment, $hireCarbon);
    }

    private function hasNonEmptyString(string $key): bool
    {
        $v = $this->input($key);

        return is_string($v) && trim($v) !== '';
    }

    private function assertHireVsAssignmentStarts(
        Validator $validator,
        EmployeeEmployment $employment,
        Carbon $hireCarbon,
    ): void {
        $affiliations = EmployeeAffiliation::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date']);

        foreach ($affiliations as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($hireCarbon->gt($start)) {
                $validator->errors()->add(
                    'hire_date',
                    'Hire date must be on or before each affiliation start date.',
                );

                return;
            }
        }

        $positions = EmployeePosition::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date']);

        foreach ($positions as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($hireCarbon->gt($start)) {
                $validator->errors()->add(
                    'hire_date',
                    'Hire date must be on or before each position start date.',
                );

                return;
            }
        }

        $assignments = EmployeeAssignment::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date']);

        foreach ($assignments as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($hireCarbon->gt($start)) {
                $validator->errors()->add(
                    'hire_date',
                    'Hire date must be on or before each org chart assignment start date.',
                );

                return;
            }
        }
    }

    private function assertAssignmentsAffiliationsPositionsAllowSeparationDates(
        Validator $validator,
        EmployeeEmployment $employment,
        Carbon $hireCarbon,
        Carbon $separationCarbon,
    ): void {
        $this->assertHireVsAssignmentStarts($validator, $employment, $hireCarbon);
        if ($validator->errors()->has('hire_date')) {
            return;
        }

        $affiliations = EmployeeAffiliation::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date', 'end_date']);

        foreach ($affiliations as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($start->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each affiliation start date.',
                );

                return;
            }

            if ($row->end_date === null) {
                $validator->errors()->add(
                    'separation_date',
                    'This employment has an open-ended affiliation. Close or end affiliations before adjusting separation.',
                );

                return;
            }

            $end = Carbon::parse($row->end_date)->startOfDay();
            if ($end->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each affiliation end date.',
                );

                return;
            }
        }

        $positions = EmployeePosition::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date', 'end_date']);

        foreach ($positions as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($start->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each position start date.',
                );

                return;
            }

            if ($row->end_date === null) {
                $validator->errors()->add(
                    'separation_date',
                    'This employment has an open-ended position. End position assignments before adjusting separation.',
                );

                return;
            }

            $end = Carbon::parse($row->end_date)->startOfDay();
            if ($end->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each position end date.',
                );

                return;
            }
        }

        $assignments = EmployeeAssignment::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->get(['start_date', 'end_date']);

        foreach ($assignments as $row) {
            $start = Carbon::parse($row->start_date)->startOfDay();
            if ($start->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each org chart assignment start date.',
                );

                return;
            }

            if ($row->end_date === null) {
                $validator->errors()->add(
                    'separation_date',
                    'This employment has an open-ended org chart assignment. End assignments on Organization Chart before recording separation.',
                );

                return;
            }

            $end = Carbon::parse($row->end_date)->startOfDay();
            if ($end->gt($separationCarbon)) {
                $validator->errors()->add(
                    'separation_date',
                    'Separation date must be on or after each org chart assignment end date.',
                );

                return;
            }
        }
    }
}
