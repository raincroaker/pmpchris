<?php

namespace App\Http\Requests;

use App\Models\EmployeeEmployment;
use App\Models\Role;
use App\Services\BranchContextService;
use App\Services\EmploymentWorkMutationAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncEmployeeEmploymentPositionsAffiliationsRequest extends FormRequest
{
    public function authorize(EmploymentWorkMutationAccess $access): bool
    {
        $employment = $this->route('employment');
        if (! $employment instanceof EmployeeEmployment) {
            return false;
        }

        if (! $access->allows($this, $employment)) {
            return false;
        }

        $employment->refresh();

        if (! $employment->is_current || $employment->employment_status !== EmployeeEmployment::STATUS_ACTIVE) {
            return false;
        }

        return $employment->separation_date === null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var EmployeeEmployment $employment */
        $employment = $this->route('employment');
        $hireDate = Carbon::parse($employment->hire_date)->toDateString();

        $organization = app(BranchContextService::class)->defaultOrganization();

        $positionExistsRule = Rule::exists('positions', 'id')->where(static function (Builder $query) use ($organization): void {
            if ($organization === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('organization_id', $organization->id)->where('is_active', true);
        });

        /** @phpstan-ignore-next-line */
        $employmentId = $employment->getKey();

        return [
            'current_password' => ['required', 'string', 'current_password'],
            'current_password_confirmation' => ['required', 'same:current_password'],
            'positions' => ['required', 'array', 'min:1'],
            'positions.*.id' => [
                'nullable',
                'integer',
                Rule::exists('employee_positions', 'id')->where(static function (Builder $query) use ($employmentId): void {
                    $query->where('employee_employment_id', $employmentId);
                }),
            ],
            'positions.*.position_id' => ['required', 'integer', $positionExistsRule],
            'positions.*.start_date' => ['required', 'date', 'after_or_equal:'.$hireDate],
            'positions.*.end_date' => ['nullable', 'date'],
            'positions.*.is_primary' => ['required', 'boolean'],
            'affiliations' => ['required', 'array', 'min:1'],
            'affiliations.*.id' => [
                'nullable',
                'integer',
                Rule::exists('employee_affiliations', 'id')->where(static function (Builder $query) use ($employmentId): void {
                    $query->where('employee_employment_id', $employmentId);
                }),
            ],
            'affiliations.*.root_unit_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')],
            'affiliations.*.start_date' => ['required', 'date', 'after_or_equal:'.$hireDate],
            'affiliations.*.end_date' => ['nullable', 'date'],
            'affiliations.*.is_primary' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The password does not match your account.',
            'current_password_confirmation.same' => 'Password confirmation must match.',
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var list<array<string, mixed>>|null $rows */
        $rows = $this->input('affiliations');

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['root_unit_id'] ?? null) === '') {
                $rows[$idx]['root_unit_id'] = null;
            }
        }

        $this->merge([
            'affiliations' => $rows,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var EmployeeEmployment $employment */
            $employment = $this->route('employment');
            $hireCarbon = Carbon::parse($employment->hire_date)->startOfDay();
            $today = Carbon::now()->startOfDay();

            /** @var BranchContextService $branchContext */
            $branchContext = app(BranchContextService::class);

            $allowOrgWide = $this->user()?->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
            ]) ?? false;

            $positions = $this->input('positions', []);
            if (! is_array($positions)) {
                $positions = [];
            }

            $hasPrimaryPosition = false;

            foreach ($positions as $index => $position) {
                if (! is_array($position)) {
                    continue;
                }

                if ((bool) ($position['is_primary'] ?? false)) {
                    $hasPrimaryPosition = true;
                }

                $startRaw = $position['start_date'] ?? null;
                $endRaw = $position['end_date'] ?? null;

                if (
                    is_string($startRaw) && $startRaw !== ''
                    && ($endRaw === null || $endRaw === '')) {

                    try {
                        $startC = Carbon::parse($startRaw)->startOfDay();
                        if ($startC->gt($today)) {
                            $validator->errors()->add(
                                'positions.'.$index.'.start_date',
                                'Open-ended positions cannot start in the future.',
                            );
                        }
                    } catch (\Throwable) {
                        // Covered by earlier rules.
                    }
                }

                if (is_string($startRaw) && $startRaw !== '' && (is_string($endRaw) && $endRaw !== '')) {
                    try {
                        $startC = Carbon::parse($startRaw)->startOfDay();
                        $endC = Carbon::parse($endRaw)->startOfDay();
                        if ($endC->lt($startC)) {
                            $validator->errors()->add(
                                'positions.'.$index.'.end_date',
                                'Position end date must be on or after start date.',
                            );
                        }
                        if ($endC->lt($hireCarbon)) {
                            $validator->errors()->add(
                                'positions.'.$index.'.end_date',
                                'Position end date must be on or after hire date.',
                            );
                        }
                    } catch (\Throwable) {
                        // Covered by earlier rules.
                    }
                }
            }

            if (! $hasPrimaryPosition) {
                $validator->errors()->add(
                    'positions',
                    'At least one position assignment must be marked as primary.',
                );
            }

            $affiliations = $this->input('affiliations', []);
            if (! is_array($affiliations)) {
                return;
            }

            $hasPrimaryAffiliation = false;

            foreach ($affiliations as $index => $affiliation) {
                if (! is_array($affiliation)) {
                    continue;
                }

                $rootUnitId = $affiliation['root_unit_id'] ?? null;

                if ($rootUnitId === null && ! $allowOrgWide) {
                    $validator->errors()->add(
                        'affiliations.'.$index.'.root_unit_id',
                        'Each affiliation requires a branch. Ask HR for an organization-wide exception when needed.',
                    );
                }

                if ($rootUnitId !== null) {
                    $root = $branchContext->findSelectableBranchRoot((int) $rootUnitId, user: $this->user());
                    if ($root === null) {
                        $validator->errors()->add(
                            'affiliations.'.$index.'.root_unit_id',
                            'That branch root is not available for your workspace or account.',
                        );
                    }
                }

                if ((bool) ($affiliation['is_primary'] ?? false)) {
                    $hasPrimaryAffiliation = true;
                }

                $startRaw = $affiliation['start_date'] ?? null;
                $endRaw = $affiliation['end_date'] ?? null;

                if (
                    is_string($startRaw) && $startRaw !== ''
                    && ($endRaw === null || $endRaw === '')) {

                    try {
                        $startC = Carbon::parse($startRaw)->startOfDay();
                        if ($startC->gt($today)) {
                            $validator->errors()->add(
                                'affiliations.'.$index.'.start_date',
                                'Open-ended affiliations cannot start in the future.',
                            );
                        }
                    } catch (\Throwable) {
                        // Covered by earlier rules.
                    }
                }

                if (is_string($startRaw) && $startRaw !== '' && (is_string($endRaw) && $endRaw !== '')) {
                    try {
                        $startC = Carbon::parse($startRaw)->startOfDay();
                        $endC = Carbon::parse($endRaw)->startOfDay();
                        if ($endC->lt($startC)) {
                            $validator->errors()->add(
                                'affiliations.'.$index.'.end_date',
                                'Affiliation end date must be on or after start date.',
                            );
                        }
                        if ($endC->lt($hireCarbon)) {
                            $validator->errors()->add(
                                'affiliations.'.$index.'.end_date',
                                'Affiliation end date must be on or after hire date.',
                            );
                        }
                    } catch (\Throwable) {
                        // Covered by earlier rules.
                    }
                }
            }

            if (! $hasPrimaryAffiliation) {
                $validator->errors()->add(
                    'affiliations',
                    'At least one affiliation must be marked as primary.',
                );
            }
        });
    }
}
