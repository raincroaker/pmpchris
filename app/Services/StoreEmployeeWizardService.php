<?php

namespace App\Services;

use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeContact;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreEmployeeWizardService
{
    public function store(StoreEmployeeRequest $request): Employee
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $createUserAccount = $request->boolean('create_user_account');

        /** @var array<string, mixed> $personalInfo */
        $personalInfo = $validated['personal_info'];
        /** @var array<string, mixed> $employmentData */
        $employmentData = $validated['employment'];
        /** @var list<array<string, mixed>> $addresses */
        $addresses = $validated['addresses'];
        /** @var list<array<string, mixed>> $contacts */
        $contacts = $validated['contacts'];
        /** @var list<array<string, mixed>> $affiliations */
        $affiliations = $employmentData['affiliations'];
        /** @var list<array<string, mixed>> $positions */
        $positions = is_array($employmentData['positions'] ?? null) ? $employmentData['positions'] : [];

        return DB::transaction(function () use (
            $request,
            $createUserAccount,
            $personalInfo,
            $employmentData,
            $addresses,
            $contacts,
            $validated,
            $affiliations,
            $positions,
        ): Employee {
            $employee = Employee::query()->create([
                'id_number' => (string) $employmentData['id_number'],
                'attendance_id' => $employmentData['attendance_id'] ?? null,
                'first_name' => (string) $personalInfo['first_name'],
                'middle_name' => $personalInfo['middle_name'] ?? null,
                'last_name' => (string) $personalInfo['last_name'],
                'suffix' => $personalInfo['suffix'] ?? null,
                'birthdate' => (string) $personalInfo['birthdate'],
                'sex' => (string) $personalInfo['sex'],
                'civil_status' => isset($personalInfo['civil_status']) && is_string($personalInfo['civil_status']) && trim($personalInfo['civil_status']) !== ''
                    ? trim($personalInfo['civil_status'])
                    : null,
                'nationality' => isset($personalInfo['nationality']) && is_string($personalInfo['nationality']) && trim($personalInfo['nationality']) !== ''
                    ? trim($personalInfo['nationality'])
                    : null,
                'religion' => $personalInfo['religion'] ?? null,
            ]);

            $hireDate = (string) $employmentData['hire_date'];
            $separationDate = $employmentData['separation_date'] ?? null;
            $employmentStatus = (string) $employmentData['employment_status'];
            $isCurrent = $separationDate === null || $separationDate === '';

            $employment = EmployeeEmployment::query()->create([
                'employee_id' => $employee->id,
                'hire_date' => $hireDate,
                'separation_date' => $isCurrent ? null : (string) $separationDate,
                'separation_reason' => $employmentData['separation_reason'] ?? null,
                'employment_status' => $employmentStatus,
                'is_current' => $isCurrent,
                'notes' => $employmentData['notes'] ?? null,
            ]);

            if ($addresses !== []) {
                foreach ($addresses as $address) {
                    EmployeeAddress::query()->create([
                        'employee_id' => $employee->id,
                        'type' => (string) $address['type'],
                        'address_line_1' => (string) $address['address_line_1'],
                        'address_line_2' => $address['address_line_2'] ?? null,
                        'barangay' => (string) $address['barangay'],
                        'barangay_code' => $address['barangay_code'] ?? null,
                        'city' => (string) $address['city'],
                        'city_code' => $address['city_code'] ?? null,
                        'province' => (string) $address['province'],
                        'province_code' => $address['province_code'] ?? null,
                        'zip_code' => (string) $address['zip_code'],
                        'country' => (string) $address['country'],
                        'is_primary' => (bool) ($address['is_primary'] ?? false),
                    ]);
                }
            }

            foreach ($contacts as $contact) {
                EmployeeContact::query()->create([
                    'employee_id' => $employee->id,
                    'category' => (string) $contact['category'],
                    'type' => $contact['type'] ?? null,
                    'contact_person' => $contact['contact_person'] ?? null,
                    'relationship' => $contact['relationship'] ?? null,
                    'contact_number' => (string) $contact['contact_number'],
                    'email' => $contact['email'] ?? null,
                    'is_primary' => (bool) ($contact['is_primary'] ?? false),
                ]);
            }

            foreach ($affiliations as $affiliation) {
                EmployeeAffiliation::query()->create([
                    'employee_id' => $employee->id,
                    'employee_employment_id' => $employment->id,
                    'organization_id' => app(BranchContextService::class)->defaultOrganization()?->id,
                    'root_unit_id' => $affiliation['root_unit_id'] !== '' ? $affiliation['root_unit_id'] : null,
                    'is_primary' => (bool) ($affiliation['is_primary'] ?? false),
                    'start_date' => (string) $affiliation['start_date'],
                    'end_date' => $affiliation['end_date'] ?? null,
                ]);
            }

            foreach ($positions as $position) {
                EmployeePosition::query()->create([
                    'employee_id' => $employee->id,
                    'employee_employment_id' => $employment->id,
                    'position_id' => (int) $position['position_id'],
                    'is_primary' => (bool) ($position['is_primary'] ?? false),
                    'start_date' => (string) $position['start_date'],
                    'end_date' => $position['end_date'] ?? null,
                ]);
            }

            if ($createUserAccount) {
                /** @var array<string, mixed> $account */
                $account = $validated['user_account'];

                $name = trim(implode(' ', array_filter([
                    (string) $personalInfo['first_name'],
                    (string) ($personalInfo['middle_name'] ?? ''),
                    (string) $personalInfo['last_name'],
                ])));

                $user = User::query()->create([
                    'employee_id' => $employee->id,
                    'name' => $name,
                    'email' => (string) $account['email'],
                    'password' => (string) $account['password'],
                ]);

                $user->assignRole(Role::CODE_EMPLOYEE);

                $avatar = $request->avatarFile();
                if ($avatar !== null) {
                    $user->avatar_path = $avatar->store(
                        'avatars/users/'.$user->id,
                        'public',
                    );
                    $user->save();
                }
            }

            return $employee;
        });
    }
}
