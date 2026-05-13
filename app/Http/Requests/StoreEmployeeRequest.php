<?php

namespace App\Http\Requests;

use App\Models\EmployeeEmployment;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use App\Support\EmployeeDemographicsFormOptions;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $addresses = $this->input('addresses');
        $personalInfo = $this->input('personal_info', []);
        if (! is_array($personalInfo)) {
            $personalInfo = [];
        }

        foreach (['civil_status', 'nationality', 'religion'] as $key) {
            $raw = $personalInfo[$key] ?? null;
            if ($raw === '' || (is_string($raw) && trim($raw) === '')) {
                $personalInfo[$key] = null;
            }
        }

        $this->merge([
            'create_user_account' => $this->boolean('create_user_account'),
            'addresses' => is_array($addresses) ? $addresses : [],
            'personal_info' => $personalInfo,
        ]);
    }

    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
            Role::CODE_HR_MANAGER,
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'personal_info' => ['required', 'array'],
            'personal_info.first_name' => ['required', 'string', 'max:100'],
            'personal_info.last_name' => ['required', 'string', 'max:100'],
            'personal_info.middle_name' => ['nullable', 'string', 'max:100'],
            'personal_info.suffix' => ['nullable', 'string', 'max:20'],
            'personal_info.birthdate' => ['required', 'date'],
            'personal_info.sex' => ['required', 'string', Rule::in(EmployeeDemographicsFormOptions::SEX_OPTIONS)],
            'personal_info.civil_status' => ['nullable', 'string', Rule::in(EmployeeDemographicsFormOptions::CIVIL_STATUS_OPTIONS)],
            'personal_info.nationality' => ['nullable', 'string', Rule::in(EmployeeDemographicsFormOptions::NATIONALITY_OPTIONS)],
            'personal_info.religion' => ['nullable', 'string', 'max:100'],

            'employment' => ['required', 'array'],
            'employment.id_number' => ['required', 'string', 'max:50', 'unique:employees,id_number'],
            'employment.attendance_id' => ['nullable', 'string', 'max:50', 'unique:employees,attendance_id'],
            'employment.hire_date' => ['required', 'date'],
            'employment.separation_date' => ['nullable', 'date', 'after_or_equal:employment.hire_date'],
            'employment.separation_reason' => ['nullable', 'string', 'max:100'],
            'employment.employment_status' => ['required', 'string', Rule::in(EmployeeEmployment::STATUSES)],
            'employment.notes' => ['nullable', 'string'],
            'employment.affiliations' => ['required', 'array', 'min:1'],
            'employment.affiliations.*.root_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'employment.affiliations.*.start_date' => ['required', 'date', 'after_or_equal:employment.hire_date'],
            'employment.affiliations.*.end_date' => ['nullable', 'date', 'after_or_equal:employment.hire_date', 'after_or_equal:employment.affiliations.*.start_date'],
            'employment.affiliations.*.is_primary' => ['required', 'boolean'],
            'employment.positions' => ['nullable', 'array'],
            'employment.positions.*.position_id' => ['required', 'integer', 'exists:positions,id'],
            'employment.positions.*.start_date' => ['required', 'date', 'after_or_equal:employment.hire_date'],
            'employment.positions.*.end_date' => ['nullable', 'date', 'after_or_equal:employment.hire_date', 'after_or_equal:employment.positions.*.start_date'],
            'employment.positions.*.is_primary' => ['required', 'boolean'],

            'addresses' => ['array'],
            'addresses.*.type' => ['required', Rule::in(['current', 'permanent'])],
            'addresses.*.address_line_1' => ['required', 'string', 'max:255'],
            'addresses.*.address_line_2' => ['nullable', 'string', 'max:255'],
            'addresses.*.barangay' => ['required', 'string', 'max:120'],
            'addresses.*.barangay_code' => ['nullable', 'string', 'max:10'],
            'addresses.*.city' => ['required', 'string', 'max:120'],
            'addresses.*.city_code' => ['nullable', 'string', 'max:10'],
            'addresses.*.province' => ['required', 'string', 'max:120'],
            'addresses.*.province_code' => ['nullable', 'string', 'max:10'],
            'addresses.*.zip_code' => ['required', 'string', 'max:20'],
            'addresses.*.country' => ['required', 'string', 'max:100'],
            'addresses.*.is_primary' => ['required', 'boolean'],

            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.category' => ['required', Rule::in(['personal', 'emergency'])],
            'contacts.*.type' => ['nullable', Rule::in(['mobile', 'home', 'work'])],
            'contacts.*.contact_person' => ['nullable', 'string', 'max:150'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'contacts.*.contact_number' => ['required', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.is_primary' => ['required', 'boolean'],

            'create_user_account' => ['boolean'],

            'user_account' => [
                Rule::excludeIf(fn () => ! $this->boolean('create_user_account')),
                'required',
                'array',
            ],
            'user_account.email' => [
                Rule::excludeIf(fn () => ! $this->boolean('create_user_account')),
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'user_account.password' => [
                Rule::excludeIf(fn () => ! $this->boolean('create_user_account')),
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'user_account.password_confirmation' => [
                Rule::excludeIf(fn () => ! $this->boolean('create_user_account')),
                'required',
                'string',
            ],
            'avatar' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(3 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employment.affiliations.required' => 'At least one affiliation is required.',
            'employment.affiliations.min' => 'At least one affiliation is required.',
            'employment.affiliations.*.start_date.required' => 'Each affiliation needs a start date.',
            'employment.affiliations.*.root_unit_id.exists' => 'Selected branch does not exist anymore.',
            'employment.hire_date.required' => 'Hire date is required.',
            'employment.separation_date.after_or_equal' => 'Separation date must be on or after the hire date.',
            'employment.employment_status.in' => 'Employment status must be one of: active, resigned, terminated, retired, contract ended.',
            'employment.affiliations.*.start_date.after_or_equal' => 'Affiliation start date must be on or after the hire date.',
            'employment.affiliations.*.end_date.after_or_equal' => 'Affiliation end date must be on or after the hire date and on or after the affiliation start.',
            'employment.positions.*.start_date.after_or_equal' => 'Position start date must be on or after the hire date.',
            'employment.positions.*.end_date.after_or_equal' => 'Position end date must be on or after the hire date and on or after the position start.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $separationDate = $this->input('employment.separation_date');
            $employmentStatus = $this->input('employment.employment_status');

            if (is_string($employmentStatus) && $employmentStatus !== '') {
                $hasSeparation = $separationDate !== null && $separationDate !== '';

                if (! $hasSeparation && $employmentStatus !== EmployeeEmployment::STATUS_ACTIVE) {
                    $validator->errors()->add(
                        'employment.employment_status',
                        'When no separation date is provided, employment status must be active.',
                    );
                }

                if ($hasSeparation && $employmentStatus === EmployeeEmployment::STATUS_ACTIVE) {
                    $validator->errors()->add(
                        'employment.employment_status',
                        'When a separation date is provided, choose resigned, terminated, retired, or contract ended.',
                    );
                }
            }
        });

        $validator->after(function (Validator $validator): void {
            $separationRaw = $this->input('employment.separation_date');
            $hasSeparation = is_string($separationRaw) && $separationRaw !== '';

            $affiliations = $this->input('employment.affiliations', []);
            if (is_array($affiliations) && $affiliations !== []) {
                $hasPrimaryAffiliation = false;
                $hasActiveAffiliation = false;

                foreach ($affiliations as $affiliation) {
                    if (! is_array($affiliation)) {
                        continue;
                    }

                    if ((bool) ($affiliation['is_primary'] ?? false)) {
                        $hasPrimaryAffiliation = true;
                    }

                    $startRaw = $affiliation['start_date'] ?? null;
                    $endRaw = $affiliation['end_date'] ?? null;
                    if (is_string($startRaw) && $startRaw !== '' && ($endRaw === null || $endRaw === '')) {
                        $hasActiveAffiliation = true;
                    }
                }

                if (! $hasPrimaryAffiliation) {
                    $validator->errors()->add(
                        'employment.affiliations',
                        'At least one affiliation must be marked as primary.',
                    );
                }

                if (! $hasSeparation && ! $hasActiveAffiliation) {
                    $validator->errors()->add(
                        'employment.affiliations',
                        'At least one active affiliation is required when no separation date is provided.',
                    );
                }
            }

            $positions = $this->input('employment.positions', []);
            if (! is_array($positions) || $positions === []) {
                return;
            }

            $hasPrimaryPosition = false;
            $hasActivePosition = false;
            foreach ($positions as $position) {
                if (! is_array($position)) {
                    continue;
                }

                if ((bool) ($position['is_primary'] ?? false)) {
                    $hasPrimaryPosition = true;
                }

                $positionId = $position['position_id'] ?? null;
                $startRaw = $position['start_date'] ?? null;
                $endRaw = $position['end_date'] ?? null;
                if (
                    $positionId !== null &&
                    is_string($startRaw) &&
                    $startRaw !== '' &&
                    ($endRaw === null || $endRaw === '')
                ) {
                    $hasActivePosition = true;
                }
            }

            if (! $hasPrimaryPosition) {
                $validator->errors()->add(
                    'employment.positions',
                    'At least one position assignment must be marked as primary.',
                );
            }

            if (! $hasSeparation && ! $hasActivePosition) {
                $validator->errors()->add(
                    'employment.positions',
                    'At least one active position assignment is required when no separation date is provided.',
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            $separationRaw = $this->input('employment.separation_date');
            if (! is_string($separationRaw) || $separationRaw === '') {
                return;
            }

            try {
                $separationCarbon = Carbon::parse($separationRaw)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $affiliations = $this->input('employment.affiliations');
            if (is_array($affiliations)) {
                foreach ($affiliations as $index => $affiliation) {
                    if (! is_array($affiliation)) {
                        continue;
                    }

                    $endRaw = $affiliation['end_date'] ?? null;
                    if ($endRaw === null || $endRaw === '') {
                        $validator->errors()->add(
                            "employment.affiliations.$index.end_date",
                            'When a separation date is provided, each affiliation must have an end date.',
                        );

                        continue;
                    }

                    if (! is_string($endRaw)) {
                        continue;
                    }

                    $startRaw = $affiliation['start_date'] ?? null;
                    if (is_string($startRaw) && $startRaw !== '') {
                        try {
                            $startCarbon = Carbon::parse($startRaw)->startOfDay();
                            if ($startCarbon->gt($separationCarbon)) {
                                $validator->errors()->add(
                                    "employment.affiliations.$index.start_date",
                                    'Affiliation start date must be on or before the employment separation date.',
                                );
                            }
                        } catch (\Throwable) {
                            // Other rules validate date shape.
                        }
                    }

                    try {
                        $endCarbon = Carbon::parse($endRaw)->startOfDay();
                        if ($endCarbon->gt($separationCarbon)) {
                            $validator->errors()->add(
                                "employment.affiliations.$index.end_date",
                                'Affiliation end date must be on or before the employment separation date.',
                            );
                        }
                    } catch (\Throwable) {
                        // Other rules validate date shape.
                    }
                }
            }

            $positions = $this->input('employment.positions');
            if (! is_array($positions) || $positions === []) {
                return;
            }

            foreach ($positions as $index => $position) {
                if (! is_array($position)) {
                    continue;
                }

                $endRaw = $position['end_date'] ?? null;
                if ($endRaw === null || $endRaw === '') {
                    $validator->errors()->add(
                        "employment.positions.$index.end_date",
                        'When a separation date is provided, each position assignment must have an end date.',
                    );

                    continue;
                }

                if (! is_string($endRaw)) {
                    continue;
                }

                $startRaw = $position['start_date'] ?? null;
                if (is_string($startRaw) && $startRaw !== '') {
                    try {
                        $startCarbon = Carbon::parse($startRaw)->startOfDay();
                        if ($startCarbon->gt($separationCarbon)) {
                            $validator->errors()->add(
                                "employment.positions.$index.start_date",
                                'Position start date must be on or before the employment separation date.',
                            );
                        }
                    } catch (\Throwable) {
                        // Other rules validate date shape.
                    }
                }

                try {
                    $endCarbon = Carbon::parse($endRaw)->startOfDay();
                    if ($endCarbon->gt($separationCarbon)) {
                        $validator->errors()->add(
                            "employment.positions.$index.end_date",
                            'Position end date must be on or before the employment separation date.',
                        );
                    }
                } catch (\Throwable) {
                    // Other rules validate date shape.
                }
            }
        });

        $validator->after(function (Validator $validator): void {
            /** @var User|null $user */
            $user = $this->user();
            if ($user === null) {
                return;
            }

            $hasFullAccess = $user->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
            ]);
            $isManagerOnly = $user->hasRole(Role::CODE_HR_MANAGER) && ! $hasFullAccess;
            if (! $isManagerOnly) {
                return;
            }

            /** @var BranchContextService $branchContextService */
            $branchContextService = app(BranchContextService::class);
            $managedRootIds = $branchContextService->managedBranchRootIdsFor($user);
            $affiliations = $this->input('employment.affiliations', []);
            if (! is_array($affiliations) || $affiliations === []) {
                return;
            }

            foreach ($affiliations as $index => $affiliation) {
                $rootUnitId = is_array($affiliation) ? ($affiliation['root_unit_id'] ?? null) : null;
                if ($rootUnitId === null || $rootUnitId === '') {
                    $validator->errors()->add(
                        "employment.affiliations.$index.root_unit_id",
                        'HR Manager cannot assign org-wide affiliation.',
                    );

                    continue;
                }

                if (! in_array((int) $rootUnitId, $managedRootIds, true)) {
                    $validator->errors()->add(
                        "employment.affiliations.$index.root_unit_id",
                        'You can only assign employees to branches you manage.',
                    );
                }
            }
        });
    }

    public function avatarFile(): ?UploadedFile
    {
        $file = $this->file('avatar');
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file;
    }
}
