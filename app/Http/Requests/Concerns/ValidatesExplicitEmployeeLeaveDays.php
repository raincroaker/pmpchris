<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesExplicitEmployeeLeaveDays
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function explicitEmployeeLeaveDaysRules(): array
    {
        return [
            'leave_days' => ['sometimes', 'array', 'min:1'],
            'leave_days.*.date' => ['required_with:leave_days', 'date_format:Y-m-d'],
            'leave_days.*.is_half_day' => ['sometimes', 'boolean'],
        ];
    }

    public function validateExplicitEmployeeLeaveDaysPayload(Validator $validator): void
    {
        if (! $this->has('leave_days')) {
            return;
        }

        $leaveDays = $this->input('leave_days');

        if (! is_array($leaveDays)) {
            return;
        }

        $dates = [];

        foreach ($leaveDays as $index => $_) {
            $dateRaw = data_get($leaveDays, $index.'.date');
            if ($dateRaw === null || $dateRaw === '') {
                continue;
            }

            $dates[] = substr((string) $dateRaw, 0, 10);
        }

        if (count($dates) !== count(array_unique($dates))) {
            $validator->errors()->add('leave_days', 'Each calendar date must appear once.');

            return;
        }

        $startRaw = $this->input('start_date');
        $endRaw = $this->input('end_date');

        $startBound = $startRaw !== null && $startRaw !== '' ? substr((string) $startRaw, 0, 10) : null;
        $endBound = $endRaw !== null && $endRaw !== '' ? substr((string) $endRaw, 0, 10) : null;

        if ($startBound !== null && $endBound !== null && $startBound !== '' && $endBound !== '') {
            foreach ($dates as $d) {
                if ($d < $startBound || $d > $endBound) {
                    $validator->errors()->add('leave_days', 'Each leave date must fall between the submitted start date and end date.');

                    return;
                }
            }
        }
    }
}
