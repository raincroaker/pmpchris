<?php

namespace App\Http\Requests;

use App\Models\EmployeeEmployment;
use App\Models\OrganizationalUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'view' => ['required', Rule::in(['users', 'roles'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['required', Rule::in(['name', 'code', 'users_count', 'id'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'page' => ['required', 'integer', 'min:1'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'org_scope' => ['nullable', Rule::in(['org_wide', 'not_org_wide'])],
            'unit_id' => ['nullable'],
            'account_status' => ['required', Rule::in(['all', 'has_account', 'no_account'])],
            'employment_state' => ['required', Rule::in(['active', 'inactive'])],
            'employment_status' => ['nullable', Rule::in(EmployeeEmployment::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('view') || ! in_array((string) $this->input('view'), ['users', 'roles'], true)) {
            $this->merge(['view' => 'users']);
        }

        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search')),
            ]);
        }

        $allowedSorts = ['name', 'code', 'users_count', 'id'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'name']);
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

        if ($this->has('role_id')) {
            $roleId = (int) $this->input('role_id');
            $this->merge([
                'role_id' => $roleId > 0 ? $roleId : null,
            ]);
        }

        if ($this->has('org_scope')) {
            $scope = (string) $this->input('org_scope');
            $this->merge([
                'org_scope' => in_array($scope, ['org_wide', 'not_org_wide'], true) ? $scope : null,
            ]);
        }

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

        $accountStatus = (string) $this->input('account_status', 'all');
        if (! in_array($accountStatus, ['all', 'has_account', 'no_account'], true)) {
            $accountStatus = 'all';
        }
        $this->merge(['account_status' => $accountStatus]);

        $employmentState = (string) $this->input('employment_state', 'active');
        if (! in_array($employmentState, ['active', 'inactive'], true)) {
            $employmentState = 'active';
        }
        $this->merge(['employment_state' => $employmentState]);

        if ($this->has('employment_status') && trim((string) $this->input('employment_status')) === '') {
            $this->merge(['employment_status' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'view.in' => 'The selected table view is invalid.',
            'account_status.in' => 'The selected account filter is invalid.',
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

            if (! OrganizationalUnit::query()->whereKey($unitId)->exists()) {
                $validator->errors()->add('unit_id', 'The selected unit filter is invalid.');
            }
        });
    }
}
