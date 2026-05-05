<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadTeamAttendanceDtrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', Rule::in(['sample', 'individual', 'team'])],
            'employee_id' => ['nullable', 'integer', 'min:1', 'required_if:mode,individual'],
            'unit_id' => ['nullable', 'integer', 'min:1', 'required_if:mode,team'],
            'date_from' => ['nullable', 'date', 'required_with:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_with:date_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->filled('mode')) {
            $merge['mode'] = 'sample';
        }

        if (! $this->has('date_from') || ! $this->has('date_to')) {
            $merge['date_from'] = now()->startOfMonth()->toDateString();
            $merge['date_to'] = now()->endOfMonth()->toDateString();
        }

        $this->merge($merge);
    }
}

