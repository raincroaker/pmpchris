<?php

namespace App\Http\Requests;

use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeSchedulesRequest extends FormRequest
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
            'sort' => ['required', Rule::in(['last_name', 'first_name', 'id_number', 'id'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:10', 'max:50'],
            'page' => ['required', 'integer', 'min:1'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'org_scope' => ['nullable', Rule::in(['org_wide', 'branch_scoped'])],
            'attendance_id_filter' => ['nullable', Rule::in(['has', 'missing'])],
            'work_schedule_filter' => ['nullable', Rule::in(['assigned', 'unassigned'])],
            'unit_filter' => ['nullable', 'string', 'max:64'],
            'placement_unit_filter' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validatorInstance): void {
            $raw = $this->input('unit_filter');

            if ($raw === null || $raw === '') {
                return;
            }

            if (! is_string($raw)) {
                $validatorInstance->errors()->add('unit_filter', __('The selected unit filter is invalid.'));

                return;
            }

            if (strcasecmp(trim($raw), 'all') === 0) {
                return;
            }

            $organization = app(BranchContextService::class)->defaultOrganization();

            if ($organization === null) {
                $validatorInstance->errors()->add('unit_filter', __('A default organization is required to filter by unit.'));

                return;
            }

            $orgId = (int) $organization->id;

            if ($raw === 'organization') {
                $user = $this->user();
                if ($user === null || ! $user->hasAnyRole([Role::CODE_SUPER_ADMIN, Role::CODE_HR_HEAD])) {
                    $validatorInstance->errors()->add(
                        'unit_filter',
                        __('You cannot filter by organization-level unit.'),
                    );
                }

                return;
            }

            if (! ctype_digit($raw)) {
                $validatorInstance->errors()->add('unit_filter', __('The selected unit filter is invalid.'));

                return;
            }

            $unitId = (int) $raw;

            $exists = OrganizationalUnit::query()
                ->whereKey($unitId)
                ->where('organization_id', $orgId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                $validatorInstance->errors()->add('unit_filter', __('The selected organizational unit is invalid.'));

                return;
            }

            $accessService = app(ScheduleAssignmentAccessService::class);
            $branchRootId = $accessService->resolveBranchRootForEmployeeDirectory($this, $organization);

            if ($branchRootId !== null) {
                $allowed = $accessService->collectSubtreeUnitIds($orgId, $branchRootId);
                if (! in_array($unitId, $allowed, true)) {
                    $this->merge(['unit_filter' => null]);
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search')),
            ]);
        }

        $allowedSorts = ['last_name', 'first_name', 'id_number', 'id'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'last_name']);
        }

        if (! $this->has('direction') || ! in_array((string) $this->input('direction'), ['asc', 'desc'], true)) {
            $this->merge(['direction' => 'asc']);
        }

        $perPage = $this->has('per_page')
            ? max(10, (int) $this->input('per_page'))
            : 15;
        $this->merge(['per_page' => min($perPage, 50)]);

        if (! $this->has('page')) {
            $this->merge(['page' => 1]);
        }

        $page = max(1, (int) $this->input('page', 1));
        $this->merge(['page' => $page]);

        if ($this->has('position_id')) {
            $positionId = $this->input('position_id');
            if ($positionId === '' || $positionId === null) {
                $this->merge(['position_id' => null]);
            } else {
                $numericPositionId = (int) $positionId;
                $this->merge(['position_id' => $numericPositionId > 0 ? $numericPositionId : null]);
            }
        }

        if ($this->has('org_scope')) {
            $scope = (string) $this->input('org_scope');
            if (! in_array($scope, ['org_wide', 'branch_scoped'], true)) {
                $this->merge(['org_scope' => null]);
            }
        }

        if ($this->has('attendance_id_filter')) {
            $v = (string) $this->input('attendance_id_filter');
            if (! in_array($v, ['has', 'missing'], true)) {
                $this->merge(['attendance_id_filter' => null]);
            }
        }

        if ($this->has('work_schedule_filter')) {
            $v = (string) $this->input('work_schedule_filter');
            if (! in_array($v, ['assigned', 'unassigned'], true)) {
                $this->merge(['work_schedule_filter' => null]);
            }
        }

        if ($this->missing('unit_filter') && $this->has('placement_unit_filter')) {
            $this->merge(['unit_filter' => $this->input('placement_unit_filter')]);
        }

        if ($this->has('unit_filter')) {
            $p = $this->input('unit_filter');
            if ($p === '' || $p === null) {
                $this->merge(['unit_filter' => null]);
            } elseif (is_string($p) && strcasecmp(trim($p), 'all') === 0) {
                $this->merge(['unit_filter' => null]);
            }
        }
    }
}
