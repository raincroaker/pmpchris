<?php

namespace App\Http\Requests;

use App\Models\OrganizationalUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeesRequest extends FormRequest
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
            'hire_from' => ['nullable', 'date'],
            'hire_to' => ['nullable', 'date', 'after_or_equal:hire_from'],
            'sort' => ['required', Rule::in(['last_name', 'first_name', 'id_number', 'id', 'hire_date'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'page' => ['required', 'integer', 'min:1'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'unit_id' => ['nullable'],
            'org_scope' => ['nullable', Rule::in(['org_wide', 'branch_scoped'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search')),
            ]);
        }

        $allowedSorts = ['last_name', 'first_name', 'id_number', 'id', 'hire_date'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'last_name']);
        }

        if ($this->filled('hire_from') xor $this->filled('hire_to')) {
            if ($this->filled('hire_from') && ! $this->filled('hire_to')) {
                $this->merge(['hire_to' => $this->input('hire_from')]);
            }
            if ($this->filled('hire_to') && ! $this->filled('hire_from')) {
                $this->merge(['hire_from' => $this->input('hire_to')]);
            }
        }

        if (! $this->has('direction') || ! in_array((string) $this->input('direction'), ['asc', 'desc'], true)) {
            $this->merge(['direction' => 'asc']);
        }

        $perPage = $this->has('per_page')
            ? max(1, (int) $this->input('per_page'))
            : 10;
        $this->merge(['per_page' => min($perPage, 50)]);

        if (! $this->has('page')) {
            $this->merge(['page' => 1]);
        }

        $page = max(1, (int) $this->input('page', 1));
        $this->merge(['page' => $page]);

        if ($this->has('unit_id')) {
            $unitId = $this->input('unit_id');
            if ($unitId === '' || $unitId === null) {
                $this->merge(['unit_id' => null]);
            } elseif ((string) $unitId === 'unassigned') {
                $this->merge(['unit_id' => 'unassigned']);
            } else {
                $numericUnitId = (int) $unitId;
                if ($numericUnitId > 0) {
                    $this->merge(['unit_id' => $numericUnitId]);
                } else {
                    $this->merge(['unit_id' => null]);
                }
            }
        }

        if ($this->has('org_scope')) {
            $scope = (string) $this->input('org_scope');
            if (in_array($scope, ['org_wide', 'branch_scoped'], true)) {
                $this->merge(['org_scope' => $scope]);
            } else {
                $this->merge(['org_scope' => null]);
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
            'unit_id.exists' => 'The selected unit filter is invalid.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $unitId = $this->input('unit_id');
            if ($unitId === null || $unitId === 'unassigned') {
                return;
            }

            if (! is_int($unitId)) {
                $validator->errors()->add('unit_id', 'The selected unit filter is invalid.');

                return;
            }

            $exists = OrganizationalUnit::query()
                ->whereKey($unitId)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('unit_id', 'The selected unit filter is invalid.');
            }
        });
    }
}
