<?php

namespace App\Http\Requests;

use App\Models\EmployeeEmployment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeEmploymentHistoryRequest extends FormRequest
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
            'employment_status' => ['nullable', Rule::in(EmployeeEmployment::STATUSES)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'hire_from' => ['nullable', 'date'],
            'hire_to' => ['nullable', 'date', 'after_or_equal:hire_from'],
            'separation_from' => ['nullable', 'date'],
            'separation_to' => ['nullable', 'date', 'after_or_equal:separation_from'],
            'sort' => ['required', Rule::in(['hire_date', 'separation_date', 'last_name', 'id_number', 'employment_status', 'tenure'])],
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

        if ($this->has('employment_status')
            && trim((string) $this->input('employment_status')) === '') {
            $this->merge(['employment_status' => null]);
        }

        $allowedSorts = ['hire_date', 'separation_date', 'last_name', 'id_number', 'employment_status', 'tenure'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'hire_date']);
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

        if ($this->filled('hire_from') xor $this->filled('hire_to')) {
            if ($this->filled('hire_from') && ! $this->filled('hire_to')) {
                $this->merge(['hire_to' => $this->input('hire_from')]);
            }
            if ($this->filled('hire_to') && ! $this->filled('hire_from')) {
                $this->merge(['hire_from' => $this->input('hire_to')]);
            }
        }

        if ($this->filled('separation_from') xor $this->filled('separation_to')) {
            if ($this->filled('separation_from') && ! $this->filled('separation_to')) {
                $this->merge(['separation_to' => $this->input('separation_from')]);
            }
            if ($this->filled('separation_to') && ! $this->filled('separation_from')) {
                $this->merge(['separation_from' => $this->input('separation_to')]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.in' => 'The selected sort column is invalid.',
            'direction.in' => 'The sort direction must be asc or desc.',
            'per_page.max' => 'The page size may not be greater than 50.',
        ];
    }
}
