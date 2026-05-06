<?php

namespace App\Http\Requests;

use App\Models\EmployeeEmployment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPositionsJobHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'history_type' => ['required', Rule::in(['positions', 'unit_assignments'])],
            'employment_status' => ['nullable', Rule::in(EmployeeEmployment::STATUSES)],
            'start_from' => ['nullable', 'date'],
            'start_to' => ['nullable', 'date', 'after_or_equal:start_from'],
            'end_from' => ['nullable', 'date'],
            'end_to' => ['nullable', 'date', 'after_or_equal:end_from'],
            'sort' => ['required', Rule::in(['start_date', 'end_date', 'last_name', 'id_number', 'employment_status', 'total_days'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search')),
            ]);
        }

        if (! $this->has('history_type') || ! in_array((string) $this->input('history_type'), ['positions', 'unit_assignments'], true)) {
            $this->merge(['history_type' => 'positions']);
        }

        if ($this->has('employment_status')
            && trim((string) $this->input('employment_status')) === '') {
            $this->merge(['employment_status' => null]);
        }

        $allowedSorts = ['start_date', 'end_date', 'last_name', 'id_number', 'employment_status', 'total_days'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'start_date']);
        }

        if (! $this->has('direction') || ! in_array((string) $this->input('direction'), ['asc', 'desc'], true)) {
            $this->merge(['direction' => 'desc']);
        }

        $perPage = $this->has('per_page')
            ? max(1, (int) $this->input('per_page'))
            : 10;
        $this->merge(['per_page' => min($perPage, 50)]);

        if (! $this->has('page')) {
            $this->merge(['page' => 1]);
        }

        $this->merge(['page' => max(1, (int) $this->input('page', 1))]);

        if ($this->filled('start_from') xor $this->filled('start_to')) {
            if ($this->filled('start_from') && ! $this->filled('start_to')) {
                $this->merge(['start_to' => $this->input('start_from')]);
            }
            if ($this->filled('start_to') && ! $this->filled('start_from')) {
                $this->merge(['start_from' => $this->input('start_to')]);
            }
        }

        if ($this->filled('end_from') xor $this->filled('end_to')) {
            if ($this->filled('end_from') && ! $this->filled('end_to')) {
                $this->merge(['end_to' => $this->input('end_from')]);
            }
            if ($this->filled('end_to') && ! $this->filled('end_from')) {
                $this->merge(['end_from' => $this->input('end_to')]);
            }
        }
    }
}
