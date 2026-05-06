<?php

namespace Database\Seeders;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Enums\OvertimePolicyContext;
use App\Enums\WorkScheduleClockPattern;
use App\Models\EmployeeEmployment;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PhaseOneInitialSystemSeeder extends Seeder
{
    public function run(): void
    {
        $seededAt = CarbonImmutable::create(2026, 2, 15, 19, 32, 2);

        DB::transaction(function () use ($seededAt): void {
            $organizationId = $this->seedOrganization($seededAt);
            $areaId = $this->seedArea($organizationId, $seededAt);
            $unitTypeIds = $this->seedUnitTypes($seededAt);
            $this->seedUnitTypeParents($unitTypeIds, $seededAt);
            $panaboRootId = $this->seedPanaboRoot($organizationId, $areaId, $unitTypeIds['Branch'], $seededAt);

            $roleIds = $this->seedRoles($seededAt);
            $positionIds = $this->seedPositions($organizationId, $seededAt);
            $this->seedPhaseTwoDepartmentsAndInternPosition($organizationId, $panaboRootId, $unitTypeIds['Department']);
            $this->seedPhaseThreeInternEmployees($organizationId, $panaboRootId, $roleIds, $positionIds);
            $this->seedPhaseFourDepartmentAssignments($organizationId);
            $this->seedWorkScheduleTemplate($organizationId, $seededAt);
            $this->seedPhaseFiveBiometricEnrollment($organizationId);
            $this->seedPhaseSixInternAttendanceDeviceIngest($organizationId);
            $holidayTypeIds = $this->seedHolidayTypes($organizationId, $seededAt);
            $this->seedCalendarEventCategories($organizationId, $seededAt);
            $this->seedOrganizationHolidays($organizationId, $holidayTypeIds, $seededAt);
            $this->seedLeavePolicies($organizationId, $seededAt);
            $this->seedOvertimePolicies($organizationId, $seededAt);

            $this->seedBootstrapAccount(
                organizationId: $organizationId,
                rootUnitId: $panaboRootId,
                roleId: $roleIds[Role::CODE_HR_HEAD],
                positionId: $positionIds['HR-ADM'],
                idNumber: '20170032',
                firstName: 'Kenneth',
                lastName: 'Martinez',
                birthdate: '1986-04-17',
                sex: 'male',
                civilStatus: 'married',
                nationality: 'Filipino',
                religion: null,
                hireDate: '2017-06-26',
                email: 'martinez.kenneth@hrnexus.com',
                seededAt: $seededAt,
                passwordPlain: 'KenHR#Mtz!2610',
                userUpdatedAt: CarbonImmutable::create(2026, 2, 16, 10, 11, 27),
            );

            $this->seedBootstrapAccount(
                organizationId: $organizationId,
                rootUnitId: $panaboRootId,
                roleId: $roleIds[Role::CODE_SUPER_ADMIN],
                positionId: $positionIds['HR-MGR'],
                idNumber: '20200024',
                firstName: 'Krysta',
                lastName: 'Magallanes',
                birthdate: '1989-09-08',
                sex: 'female',
                civilStatus: 'married',
                nationality: 'Filipino',
                religion: null,
                hireDate: '2020-11-11',
                email: 'magallanes.krysta@hrnexus.com',
                seededAt: $seededAt,
                passwordPlain: 'password',
                userUpdatedAt: CarbonImmutable::create(2026, 2, 16, 10, 18, 44),
            );
        });
    }

    private function seedOrganization(CarbonImmutable $seededAt): int
    {
        DB::table('organizations')->updateOrInsert(
            ['code' => 'PMPC'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Panabo Multipurpose Cooperative',
                'is_active' => true,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]
        );

        return (int) DB::table('organizations')->where('code', 'PMPC')->value('id');
    }

    private function seedArea(int $organizationId, CarbonImmutable $seededAt): int
    {
        DB::table('areas')->updateOrInsert(
            ['organization_id' => $organizationId, 'code' => 'AREA-DAVNOR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Davao del Norte',
                'is_active' => true,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]
        );

        return (int) DB::table('areas')
            ->where('organization_id', $organizationId)
            ->where('code', 'AREA-DAVNOR')
            ->value('id');
    }

    /**
     * @return array<string, int>
     */
    private function seedUnitTypes(CarbonImmutable $seededAt): array
    {
        $rows = [
            ['name' => 'Branch', 'color' => '#6366f1', 'can_be_root' => true, 'description' => 'Regional branch', 'is_active' => true],
            ['name' => 'Department', 'color' => '#10b981', 'can_be_root' => false, 'description' => 'Functional department', 'is_active' => true],
            ['name' => 'Section', 'color' => '#f59e0b', 'can_be_root' => false, 'description' => 'Sub-unit within a department', 'is_active' => true],
        ];

        foreach ($rows as $row) {
            DB::table('unit_types')->updateOrInsert(
                ['name' => $row['name']],
                [
                    'color' => $row['color'],
                    'can_be_root' => $row['can_be_root'],
                    'description' => $row['description'],
                    'is_active' => $row['is_active'],
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]
            );
        }

        return DB::table('unit_types')
            ->whereIn('name', ['Branch', 'Department', 'Section'])
            ->pluck('id', 'name')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, int>  $unitTypeIds
     */
    private function seedUnitTypeParents(array $unitTypeIds, CarbonImmutable $seededAt): void
    {
        $rows = [
            ['parent_unit_type_id' => $unitTypeIds['Branch'], 'child_unit_type_id' => $unitTypeIds['Department']],
            ['parent_unit_type_id' => $unitTypeIds['Department'], 'child_unit_type_id' => $unitTypeIds['Section']],
            ['parent_unit_type_id' => $unitTypeIds['Branch'], 'child_unit_type_id' => $unitTypeIds['Section']],
        ];

        foreach ($rows as $row) {
            DB::table('unit_type_parents')->updateOrInsert(
                [
                    'parent_unit_type_id' => $row['parent_unit_type_id'],
                    'child_unit_type_id' => $row['child_unit_type_id'],
                ],
                [
                    'is_active' => true,
                    'created_at' => $seededAt,
                ]
            );
        }
    }

    private function seedPanaboRoot(
        int $organizationId,
        int $areaId,
        int $branchUnitTypeId,
        CarbonImmutable $seededAt
    ): int {
        DB::table('organizational_units')->updateOrInsert(
            ['organization_id' => $organizationId, 'code' => 'PAN'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Panabo Branch',
                'unit_type_id' => $branchUnitTypeId,
                'parent_id' => null,
                'area_id' => $areaId,
                'address' => null,
                'is_active' => true,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]
        );

        return (int) DB::table('organizational_units')
            ->where('organization_id', $organizationId)
            ->where('code', 'PAN')
            ->value('id');
    }

    /**
     * @return array<string, int>
     */
    private function seedRoles(CarbonImmutable $seededAt): array
    {
        $rows = [
            [
                'code' => Role::CODE_EMPLOYEE,
                'name' => 'Employee',
                'description' => 'Baseline staff access.',
            ],
            [
                'code' => Role::CODE_HR_HEAD,
                'name' => 'HR Head',
                'description' => 'HR leadership.',
            ],
            [
                'code' => Role::CODE_SUPER_ADMIN,
                'name' => 'Super Administrator',
                'description' => 'Full application access.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('roles')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]
            );
        }

        return DB::table('roles')
            ->whereIn('code', [Role::CODE_EMPLOYEE, Role::CODE_HR_HEAD, Role::CODE_SUPER_ADMIN])
            ->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function seedPositions(int $organizationId, CarbonImmutable $seededAt): array
    {
        $rows = [
            ['code' => 'HR-ADM', 'title' => 'HR Admin', 'description' => 'Human resources administration and records.'],
            ['code' => 'HR-MGR', 'title' => 'HR Manager', 'description' => 'Leads HR operations and policy implementation.'],
        ];

        foreach ($rows as $row) {
            DB::table('positions')->updateOrInsert(
                ['organization_id' => $organizationId, 'code' => $row['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'is_active' => true,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]
            );
        }

        return DB::table('positions')
            ->where('organization_id', $organizationId)
            ->whereIn('code', ['HR-ADM', 'HR-MGR'])
            ->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function seedPhaseTwoDepartmentsAndInternPosition(
        int $organizationId,
        int $panaboRootId,
        int $departmentUnitTypeId
    ): void {
        $departmentRows = [
            [
                'code' => 'MM',
                'name' => 'Marketing Management',
                'created_at' => CarbonImmutable::create(2026, 2, 16, 10, 8, 12),
            ],
            [
                'code' => 'FM',
                'name' => 'Finance Management',
                'created_at' => CarbonImmutable::create(2026, 2, 16, 10, 9, 45),
            ],
            [
                'code' => 'HR',
                'name' => 'Human Resources',
                'created_at' => CarbonImmutable::create(2026, 2, 16, 10, 10, 3),
            ],
        ];

        foreach ($departmentRows as $row) {
            DB::table('organizational_units')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'code' => $row['code'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $row['name'],
                    'unit_type_id' => $departmentUnitTypeId,
                    'parent_id' => $panaboRootId,
                    'area_id' => null,
                    'address' => null,
                    'is_active' => true,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['created_at'],
                ]
            );
        }

        $internPositionTimestamp = CarbonImmutable::create(2026, 2, 16, 10, 13, 22);

        DB::table('positions')->updateOrInsert(
            [
                'organization_id' => $organizationId,
                'code' => 'INT',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'Intern',
                'description' => 'Entry-level internship position.',
                'is_active' => true,
                'created_at' => $internPositionTimestamp,
                'updated_at' => $internPositionTimestamp,
            ]
        );
    }

    /**
     * @param  array<string, int>  $roleIds
     * @param  array<string, int>  $positionIds
     */
    private function seedPhaseThreeInternEmployees(
        int $organizationId,
        int $panaboRootId,
        array $roleIds,
        array $positionIds
    ): void {
        $internPositionId = (int) (
            DB::table('positions')
                ->where('organization_id', $organizationId)
                ->where('code', 'INT')
                ->value('id')
            ?? $positionIds['INT']
            ?? 0
        );

        if ($internPositionId <= 0 || ! isset($roleIds[Role::CODE_EMPLOYEE])) {
            return;
        }

        $employeeRoleId = (int) $roleIds[Role::CODE_EMPLOYEE];

        $rows = [
            [
                'id_number' => '2023-00452',
                'first_name' => 'Jannah',
                'last_name' => 'Cartagena',
                'sex' => 'female',
                'birthdate' => '2004-03-15',
                'contact_number' => '09184726503',
                'email' => 'jannah.cartagena@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. San Francisco, Panabo City, Davao del Norte',
                'barangay' => 'San Francisco',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 11, 2, 12),
                'password_plain' => 'Jc#5204!Nrm',
            ],
            [
                'id_number' => '2023-01987',
                'first_name' => 'Rose',
                'last_name' => 'Magno',
                'sex' => 'female',
                'birthdate' => '2003-07-22',
                'contact_number' => '09276148395',
                'email' => 'rose.magno@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Nanyo, Panabo City, Davao del Norte',
                'barangay' => 'Nanyo',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 11, 5, 4),
                'password_plain' => 'Rm!1987@Pcs',
            ],
            [
                'id_number' => '2023-07314',
                'first_name' => 'April',
                'last_name' => 'Alcordo',
                'sex' => 'female',
                'birthdate' => '2004-04-10',
                'contact_number' => '09452837610',
                'email' => 'april.alcordo@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Ising, Carmen, Davao del Norte',
                'barangay' => 'Ising',
                'city' => 'Carmen',
                'province' => 'Davao del Norte',
                'zip_code' => '8101',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 11, 8, 31),
                'password_plain' => 'Ap$7314_Crm',
            ],
            [
                'id_number' => '2023-11829',
                'first_name' => 'Carmel',
                'last_name' => 'Galon',
                'sex' => 'female',
                'birthdate' => '2003-12-05',
                'contact_number' => '09367052184',
                'email' => 'carmel.galon@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Tibungol, Panabo City, Davao del Norte',
                'barangay' => 'Tibungol',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 11, 11, 9),
                'password_plain' => 'Cg*1829Pan!',
            ],
            [
                'id_number' => '2023-20576',
                'first_name' => 'Archie',
                'last_name' => 'Josol',
                'sex' => 'male',
                'birthdate' => '2003-01-18',
                'contact_number' => '09095614728',
                'email' => 'archie.josol@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Tagpore, Panabo City, Davao del Norte',
                'barangay' => 'Tagpore',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 3, 15),
                'password_plain' => 'Aj-20576#Ojt',
            ],
            [
                'id_number' => '2023-26741',
                'first_name' => 'Ivy',
                'last_name' => 'Moya',
                'sex' => 'female',
                'birthdate' => '2004-05-09',
                'contact_number' => '09528341907',
                'email' => 'ivy.moya@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Alejal, Carmen, Davao del Norte',
                'barangay' => 'Alejal',
                'city' => 'Carmen',
                'province' => 'Davao del Norte',
                'zip_code' => '8101',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 6, 2),
                'password_plain' => 'Im@26741!Cmn',
            ],
            [
                'id_number' => '2023-33490',
                'first_name' => 'Philip',
                'last_name' => 'Sagais',
                'sex' => 'male',
                'birthdate' => '2003-08-14',
                'contact_number' => '09216473059',
                'email' => 'philip.sagais@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Guadalupe, Carmen, Davao del Norte',
                'barangay' => 'Guadalupe',
                'city' => 'Carmen',
                'province' => 'Davao del Norte',
                'zip_code' => '8101',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 9, 44),
                'password_plain' => 'Ps#33490_Ojt',
            ],
            [
                'id_number' => '2023-40128',
                'first_name' => 'Odessa',
                'last_name' => 'Ybañez',
                'sex' => 'female',
                'birthdate' => '2004-11-30',
                'contact_number' => '09392185647',
                'email' => 'odessa.ybanez@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Datu Abdul Dadia, Panabo City, Davao del Norte',
                'barangay' => 'Datu Abdul Dadia',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 12, 21),
                'password_plain' => 'Oy!40128-pan',
            ],
            [
                'id_number' => '2023-58937',
                'first_name' => 'Lealyn',
                'last_name' => 'Gentica',
                'sex' => 'female',
                'birthdate' => '2004-02-02',
                'contact_number' => '09476053821',
                'email' => 'lealyn.gentica@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Salvacion, Panabo City, Davao del Norte',
                'barangay' => 'Salvacion',
                'city' => 'Panabo City',
                'province' => 'Davao del Norte',
                'zip_code' => '8105',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 15, 58),
                'password_plain' => 'Lg#58937_hrs',
            ],
            [
                'id_number' => '2023-74206',
                'first_name' => 'Jona Mae',
                'last_name' => 'Cabanog',
                'sex' => 'female',
                'birthdate' => '2003-06-25',
                'contact_number' => '09267431589',
                'email' => 'jonamae.cabanog@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Tuganay, Carmen, Davao del Norte',
                'barangay' => 'Tuganay',
                'city' => 'Carmen',
                'province' => 'Davao del Norte',
                'zip_code' => '8101',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 18, 33),
                'password_plain' => 'Jm$74206-mm',
            ],
            [
                'id_number' => '2023-91355',
                'first_name' => 'Ruby Rose',
                'last_name' => 'Arellano',
                'sex' => 'female',
                'birthdate' => '2004-09-12',
                'contact_number' => '09345802671',
                'email' => 'rubyrose.arellano@gmail.com',
                'religion' => 'Catholic',
                'hire_date' => '2026-02-09',
                'address_line_1' => 'Brgy. Anibongan, Carmen, Davao del Norte',
                'barangay' => 'Anibongan',
                'city' => 'Carmen',
                'province' => 'Davao del Norte',
                'zip_code' => '8101',
                'created_at' => CarbonImmutable::create(2026, 2, 17, 16, 21, 41),
                'password_plain' => 'Rr@91355-hr',
            ],
        ];

        foreach ($rows as $row) {
            $timestamp = $row['created_at'];

            DB::table('employees')->updateOrInsert(
                ['id_number' => $row['id_number']],
                [
                    'attendance_id' => null,
                    'work_schedule_template_id' => null,
                    'first_name' => $row['first_name'],
                    'middle_name' => null,
                    'last_name' => $row['last_name'],
                    'suffix' => null,
                    'birthdate' => $row['birthdate'],
                    'birthday_visibility' => 'team',
                    'sex' => $row['sex'],
                    'civil_status' => 'single',
                    'nationality' => 'Filipino',
                    'religion' => $row['religion'],
                    'religion_other' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            $employeeId = (int) DB::table('employees')->where('id_number', $row['id_number'])->value('id');

            DB::table('users')->updateOrInsert(
                ['email' => $row['email']],
                [
                    'employee_id' => $employeeId,
                    'name' => trim($row['first_name'].' '.$row['last_name']),
                    'password' => Hash::make((string) ($row['password_plain'] ?? 'password')),
                    'email_verified_at' => $timestamp,
                    'remember_token' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );

            $userId = (int) DB::table('users')->where('email', $row['email'])->value('id');

            DB::table('role_user')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'role_id' => $employeeRoleId,
                ],
                [
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );

            DB::table('employee_employments')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'hire_date' => $row['hire_date'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'separation_date' => null,
                    'separation_reason' => null,
                    'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                    'is_current' => true,
                    'notes' => 'Phase 3 intern onboarding.',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            $employmentId = (int) DB::table('employee_employments')
                ->where('employee_id', $employeeId)
                ->where('hire_date', $row['hire_date'])
                ->value('id');

            DB::table('employee_affiliations')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'employee_employment_id' => $employmentId,
                    'organization_id' => $organizationId,
                    'root_unit_id' => $panaboRootId,
                    'start_date' => $row['hire_date'],
                ],
                [
                    'is_primary' => true,
                    'end_date' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            DB::table('employee_positions')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'employee_employment_id' => $employmentId,
                    'position_id' => $internPositionId,
                    'start_date' => $row['hire_date'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'is_primary' => true,
                    'end_date' => null,
                    'notes' => 'Phase 3 intern position.',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            DB::table('employee_contacts')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'category' => 'personal',
                    'type' => 'mobile',
                ],
                [
                    'contact_person' => null,
                    'relationship' => null,
                    'contact_number' => $row['contact_number'],
                    'email' => $row['email'],
                    'is_primary' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            DB::table('employee_addresses')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'type' => 'current',
                ],
                [
                    'address_line_1' => $row['address_line_1'],
                    'address_line_2' => null,
                    'barangay' => $row['barangay'],
                    'barangay_code' => null,
                    'city' => $row['city'],
                    'city_code' => null,
                    'province' => $row['province'],
                    'province_code' => null,
                    'zip_code' => $row['zip_code'],
                    'country' => 'Philippines',
                    'is_primary' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedPhaseFourDepartmentAssignments(int $organizationId): void
    {
        $unitIdsByCode = DB::table('organizational_units')
            ->where('organization_id', $organizationId)
            ->whereIn('code', ['MM', 'FM', 'HR'])
            ->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $rows = [
            ['id_number' => '2023-74206', 'unit_code' => 'HR', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 0, 18)],
            ['id_number' => '2023-91355', 'unit_code' => 'HR', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 0, 52)],
            ['id_number' => '2023-26741', 'unit_code' => 'MM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 2, 31)],
            ['id_number' => '2023-33490', 'unit_code' => 'MM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 3, 6)],
            ['id_number' => '2023-40128', 'unit_code' => 'MM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 3, 44)],
            ['id_number' => '2023-58937', 'unit_code' => 'MM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 4, 21)],
            ['id_number' => '2023-00452', 'unit_code' => 'FM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 6, 6)],
            ['id_number' => '2023-01987', 'unit_code' => 'FM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 6, 42)],
            ['id_number' => '2023-07314', 'unit_code' => 'FM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 7, 19)],
            ['id_number' => '2023-11829', 'unit_code' => 'FM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 7, 55)],
            ['id_number' => '2023-20576', 'unit_code' => 'FM', 'created_at' => CarbonImmutable::create(2026, 2, 18, 10, 8, 34)],
        ];

        foreach ($rows as $row) {
            $unitId = (int) ($unitIdsByCode[$row['unit_code']] ?? 0);
            if ($unitId <= 0) {
                continue;
            }

            $employeeId = (int) (DB::table('employees')->where('id_number', $row['id_number'])->value('id') ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            $employmentId = (int) (
                DB::table('employee_employments')
                    ->where('employee_id', $employeeId)
                    ->where('is_current', true)
                    ->value('id')
                ?? 0
            );
            if ($employmentId <= 0) {
                continue;
            }

            $employeePositionId = (int) (
                DB::table('employee_positions')
                    ->where('employee_id', $employeeId)
                    ->where('employee_employment_id', $employmentId)
                    ->where('is_primary', true)
                    ->value('id')
                ?? 0
            );
            if ($employeePositionId <= 0) {
                continue;
            }

            $timestamp = $row['created_at'];

            DB::table('employee_assignments')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'employee_employment_id' => $employmentId,
                    'organizational_unit_id' => $unitId,
                    'start_date' => '2026-02-18',
                ],
                [
                    'organization_id' => null,
                    'is_primary' => true,
                    'is_head' => false,
                    'end_date' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );

            $assignmentId = (int) (
                DB::table('employee_assignments')
                    ->where('employee_id', $employeeId)
                    ->where('employee_employment_id', $employmentId)
                    ->where('organizational_unit_id', $unitId)
                    ->where('start_date', '2026-02-18')
                    ->value('id')
                ?? 0
            );
            if ($assignmentId <= 0) {
                continue;
            }

            DB::table('assignment_positions')->updateOrInsert(
                [
                    'employee_assignment_id' => $assignmentId,
                    'employee_position_id' => $employeePositionId,
                    'start_date' => '2026-02-18',
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'is_primary_for_assignment' => true,
                    'end_date' => '2099-12-31',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedPhaseFiveBiometricEnrollment(int $organizationId): void
    {
        $templateId = (int) (
            DB::table('work_schedule_templates')
                ->where('organization_id', $organizationId)
                ->where('name', 'Weekday 8-5 split (Mon-Fri)')
                ->value('id')
            ?? 0
        );

        if ($templateId <= 0) {
            return;
        }

        $rows = [
            [
                'id_number' => '2023-00452',
                'attendance_id' => '647',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 30, 12),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 30, 41),
            ],
            [
                'id_number' => '2023-01987',
                'attendance_id' => '648',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 31, 1),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 31, 29),
            ],
            [
                'id_number' => '2023-07314',
                'attendance_id' => '649',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 31, 48),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 32, 14),
            ],
            [
                'id_number' => '2023-11829',
                'attendance_id' => '650',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 32, 37),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 33, 3),
            ],
            [
                'id_number' => '2023-20576',
                'attendance_id' => '651',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 33, 24),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 33, 52),
            ],
            [
                'id_number' => '2023-26741',
                'attendance_id' => '652',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 34, 13),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 34, 40),
            ],
            [
                'id_number' => '2023-33490',
                'attendance_id' => '653',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 35, 2),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 35, 31),
            ],
            [
                'id_number' => '2023-40128',
                'attendance_id' => '654',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 35, 50),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 36, 18),
            ],
            [
                'id_number' => '2023-58937',
                'attendance_id' => '655',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 36, 39),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 37, 6),
            ],
            [
                'id_number' => '2023-74206',
                'attendance_id' => '656',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 37, 26),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 37, 54),
            ],
            [
                'id_number' => '2023-91355',
                'attendance_id' => '657',
                'attendance_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 38, 15),
                'schedule_updated_at' => CarbonImmutable::create(2026, 2, 18, 16, 38, 43),
            ],
        ];

        foreach ($rows as $row) {
            DB::table('employees')
                ->where('id_number', $row['id_number'])
                ->update([
                    'attendance_id' => $row['attendance_id'],
                    'updated_at' => $row['attendance_updated_at'],
                ]);

            DB::table('employees')
                ->where('id_number', $row['id_number'])
                ->update([
                    'work_schedule_template_id' => $templateId,
                    'updated_at' => $row['schedule_updated_at'],
                ]);
        }
    }

    private function seedPhaseSixInternAttendanceDeviceIngest(int $organizationId): void
    {
        $template = DB::table('work_schedule_templates')
            ->where('organization_id', $organizationId)
            ->where('name', 'Weekday 8-5 split (Mon-Fri)')
            ->first();

        if ($template === null) {
            return;
        }

        $unitIdsByCode = DB::table('organizational_units')
            ->where('organization_id', $organizationId)
            ->whereIn('code', ['HR', 'MM', 'FM'])
            ->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $employeeRows = DB::table('employees')
            ->whereIn('id_number', [
                '2023-00452',
                '2023-01987',
                '2023-07314',
                '2023-11829',
                '2023-20576',
                '2023-26741',
                '2023-33490',
                '2023-40128',
                '2023-58937',
                '2023-74206',
                '2023-91355',
            ])
            ->get(['id', 'id_number', 'attendance_id']);

        $employeesByIdNumber = [];
        foreach ($employeeRows as $row) {
            if (! is_string($row->id_number) || ! is_string($row->attendance_id)) {
                continue;
            }

            $employeesByIdNumber[$row->id_number] = [
                'id' => (int) $row->id,
                'attendance_id' => $row->attendance_id,
            ];
        }

        $assignmentUnitCodeByEmployee = [
            '2023-74206' => 'HR',
            '2023-91355' => 'HR',
            '2023-26741' => 'MM',
            '2023-33490' => 'MM',
            '2023-40128' => 'MM',
            '2023-58937' => 'MM',
            '2023-00452' => 'FM',
            '2023-01987' => 'FM',
            '2023-07314' => 'FM',
            '2023-11829' => 'FM',
            '2023-20576' => 'FM',
        ];

        $absentByEmployeeAndDate = [
            '2023-20576|2026-03-03' => true,
            '2023-40128|2026-03-14' => true,
            '2023-91355|2026-03-24' => true,
        ];

        $anomalyDates = [
            CarbonImmutable::create(2026, 2, 18),
        ];

        $workDates = [];
        for ($day = CarbonImmutable::create(2026, 2, 19); $day->lte(CarbonImmutable::create(2026, 3, 27)); $day = $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            // Keep EDSA (Feb 25) as a working day; skip Eid'l Fitr only.
            if ($day->toDateString() === '2026-03-20') {
                continue;
            }

            $workDates[] = $day;
        }

        $employeePlan = [];
        foreach ($assignmentUnitCodeByEmployee as $idNumber => $unitCode) {
            $employee = $employeesByIdNumber[$idNumber] ?? null;
            $unitId = (int) ($unitIdsByCode[$unitCode] ?? 0);

            if (! is_array($employee) || $unitId <= 0) {
                continue;
            }

            $employeePlan[] = [
                'id_number' => $idNumber,
                'employee_id' => (int) $employee['id'],
                'attendance_id' => (string) $employee['attendance_id'],
                'unit_id' => $unitId,
            ];
        }

        foreach ($anomalyDates as $anomalyDate) {
            $orderedPlans = $this->orderEmployeePlansForDate($employeePlan, $anomalyDate->toDateString(), 'anomaly');
            foreach ($orderedPlans as $index => $plan) {
                $anomalyDateKey = $anomalyDate->toDateString();
                $anomalySegments = $this->buildPhaseSixAnomalySegments((string) $plan['id_number'], $anomalyDateKey);

                $firstIn = $this->minutesFromHm((string) $anomalySegments[0]['actual_in']);
                $lastTouchIn = $this->minutesFromHm((string) $anomalySegments[3]['actual_in']);

                $createdAt = $anomalyDate->setTime(
                    intdiv($firstIn, 60),
                    $firstIn % 60,
                    7 + (($index * 5) % 53),
                );

                $updatedAt = $anomalyDate->setTime(
                    intdiv($lastTouchIn, 60),
                    $lastTouchIn % 60,
                    17 + (($index * 7) % 41),
                );

                DB::table('employee_attendance_days')->updateOrInsert(
                    [
                        'employee_id' => (int) $plan['employee_id'],
                        'work_date' => $anomalyDateKey,
                    ],
                    [
                        'organization_id' => $organizationId,
                        'organizational_unit_id' => (int) $plan['unit_id'],
                        'work_schedule_template_id' => (int) $template->id,
                        'clock_pattern' => WorkScheduleClockPattern::SplitSessions->value,
                        'is_overnight_schedule' => false,
                        'ingest_key' => null,
                        'original_entry_source' => 'device',
                        'last_modified_source' => 'device',
                        'status' => 'incomplete',
                        'punctuality' => null,
                        'net_hours' => null,
                        'variance_label' => null,
                        'created_by_user_id' => null,
                        'updated_by_user_id' => null,
                        'deleted_by_user_id' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ]
                );

                $dayId = (int) (
                    DB::table('employee_attendance_days')
                        ->where('employee_id', (int) $plan['employee_id'])
                        ->where('work_date', $anomalyDateKey)
                        ->value('id')
                    ?? 0
                );

                if ($dayId <= 0) {
                    continue;
                }

                foreach ($anomalySegments as $segmentOffset => $segment) {
                    $segmentIndex = $segmentOffset + 1;
                    DB::table('employee_attendance_segments')->updateOrInsert(
                        [
                            'employee_attendance_day_id' => $dayId,
                            'segment_index' => $segmentIndex,
                        ],
                        [
                            'label' => $segment['label'],
                            'scheduled_in' => $segment['scheduled_in'],
                            'scheduled_out' => $segment['scheduled_out'],
                            'actual_in' => $segment['actual_in'],
                            'actual_out' => $segment['actual_out'],
                            'created_at' => $createdAt,
                            'updated_at' => $updatedAt,
                        ]
                    );
                }

                DB::table('employee_attendance_segments')
                    ->where('employee_attendance_day_id', $dayId)
                    ->whereNotIn('segment_index', [1, 2, 3, 4])
                    ->delete();
            }
        }

        // Remove legacy anomaly rows from the old Feb 17 experiment if present.
        $employeeIds = array_map(
            fn (array $plan): int => (int) $plan['employee_id'],
            $employeePlan
        );
        DB::table('employee_attendance_days')
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', '2026-02-17')
            ->where('ingest_key', null)
            ->where('status', 'incomplete')
            ->delete();

        foreach ($workDates as $workDate) {
            $orderedPlans = $this->orderEmployeePlansForDate($employeePlan, $workDate->toDateString(), 'normal');
            foreach ($orderedPlans as $index => $plan) {
                $dateKey = $workDate->toDateString();
                $compositeKey = ((string) $plan['id_number']).'|'.$dateKey;
                $isAbsent = isset($absentByEmployeeAndDate[$compositeKey]);
                if ($isAbsent) {
                    DB::table('employee_attendance_days')
                        ->where('employee_id', (int) $plan['employee_id'])
                        ->where('work_date', $dateKey)
                        ->delete();

                    continue;
                }

                $segments = $this->buildPhaseSixSegments((string) $plan['id_number'], $dateKey);
                $status = 'complete';
                $punctuality = 'on_time';
                $netHours = 8;

                $morningInMinutes = $this->minutesFromHm((string) $segments[0]['actual_in']);
                $afternoonOutMinutes = $this->minutesFromHm((string) $segments[1]['actual_out']);

                $createdAt = $workDate->setTime(
                    intdiv($morningInMinutes, 60),
                    $morningInMinutes % 60,
                    10 + (($index * 3) % 43),
                );

                $updatedAt = $workDate->setTime(
                    intdiv($afternoonOutMinutes, 60),
                    $afternoonOutMinutes % 60,
                    5 + (($index * 7) % 49),
                );

                $ingestKey = sprintf(
                    'DVC%s%s',
                    $workDate->format('Ymd'),
                    str_pad((string) $plan['attendance_id'], 4, '0', STR_PAD_LEFT)
                );

                DB::table('employee_attendance_days')->updateOrInsert(
                    [
                        'employee_id' => (int) $plan['employee_id'],
                        'work_date' => $dateKey,
                    ],
                    [
                        'organization_id' => $organizationId,
                        'organizational_unit_id' => (int) $plan['unit_id'],
                        'work_schedule_template_id' => (int) $template->id,
                        'clock_pattern' => WorkScheduleClockPattern::SplitSessions->value,
                        'is_overnight_schedule' => false,
                        'ingest_key' => $ingestKey,
                        'original_entry_source' => 'device',
                        'last_modified_source' => 'device',
                        'status' => $status,
                        'punctuality' => $punctuality,
                        'net_hours' => $netHours,
                        'variance_label' => null,
                        'created_by_user_id' => null,
                        'updated_by_user_id' => null,
                        'deleted_by_user_id' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => null,
                    ]
                );

                $dayId = (int) (
                    DB::table('employee_attendance_days')
                        ->where('employee_id', (int) $plan['employee_id'])
                        ->where('work_date', $dateKey)
                        ->value('id')
                    ?? 0
                );

                if ($dayId <= 0) {
                    continue;
                }

                foreach ($segments as $segmentOffset => $segment) {
                    $segmentIndex = $segmentOffset + 1;
                    DB::table('employee_attendance_segments')->updateOrInsert(
                        [
                            'employee_attendance_day_id' => $dayId,
                            'segment_index' => $segmentIndex,
                        ],
                        [
                            'label' => $segment['label'],
                            'scheduled_in' => $segment['scheduled_in'],
                            'scheduled_out' => $segment['scheduled_out'],
                            'actual_in' => $segment['actual_in'],
                            'actual_out' => $segment['actual_out'],
                            'created_at' => $createdAt,
                            'updated_at' => $updatedAt,
                        ]
                    );
                }
            }
        }
    }

    /**
     * @return list<array{
     *     label: string,
     *     scheduled_in: string,
     *     scheduled_out: string,
     *     actual_in: ?string,
     *     actual_out: ?string
     * }>
     */
    private function buildPhaseSixSegments(string $idNumber, string $workDate): array
    {
        $morningIn = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'morning_in', 7 * 60 + 20, 8 * 60 + 5));
        $morningOut = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'morning_out', 12 * 60 + 3, 12 * 60 + 15));
        $afternoonIn = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'afternoon_in', 12 * 60 + 42, 12 * 60 + 55));
        $afternoonOut = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'afternoon_out', 17 * 60 + 2, 17 * 60 + 12));

        return [
            [
                'label' => 'Session 1',
                'scheduled_in' => '08:00',
                'scheduled_out' => '12:00',
                'actual_in' => $morningIn,
                'actual_out' => $morningOut,
            ],
            [
                'label' => 'Session 2',
                'scheduled_in' => '13:00',
                'scheduled_out' => '17:00',
                'actual_in' => $afternoonIn,
                'actual_out' => $afternoonOut,
            ],
        ];
    }

    /**
     * @param  list<array{
     *     id_number: string,
     *     employee_id: int,
     *     attendance_id: string,
     *     unit_id: int
     * }>  $employeePlan
     * @return list<array{
     *     id_number: string,
     *     employee_id: int,
     *     attendance_id: string,
     *     unit_id: int
     * }>
     */
    private function orderEmployeePlansForDate(array $employeePlan, string $dateKey, string $mode): array
    {
        usort(
            $employeePlan,
            function (array $left, array $right) use ($dateKey, $mode): int {
                $leftKey = abs(crc32($dateKey.'|'.$mode.'|'.((string) $left['id_number'])));
                $rightKey = abs(crc32($dateKey.'|'.$mode.'|'.((string) $right['id_number'])));

                if ($leftKey === $rightKey) {
                    return strcmp((string) $left['id_number'], (string) $right['id_number']);
                }

                return $leftKey <=> $rightKey;
            }
        );

        return array_values($employeePlan);
    }

    /**
     * @return list<array{
     *     label: string,
     *     scheduled_in: string,
     *     scheduled_out: string,
     *     actual_in: ?string,
     *     actual_out: ?string
     * }>
     */
    private function buildPhaseSixAnomalySegments(string $idNumber, string $workDate): array
    {
        $firstIn = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'anomaly_first_in', 7 * 60 + 20, 8 * 60 + 5));
        $firstOut = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'anomaly_first_out', 11 * 60 + 31, 12 * 60 + 16));
        $thirdIn = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'anomaly_third_in', 12 * 60 + 41, 13 * 60 + 24));
        $thirdOut = $this->formatMinutesToHm($this->deterministicMinuteInRange($idNumber, $workDate, 'anomaly_third_out', 14 * 60 + 47, 16 * 60 + 4));

        return [
            [
                'label' => 'Session 1',
                'scheduled_in' => '08:00',
                'scheduled_out' => '12:00',
                'actual_in' => $firstIn,
                'actual_out' => $firstOut,
            ],
            [
                'label' => 'Session 1',
                'scheduled_in' => '08:00',
                'scheduled_out' => '12:00',
                'actual_in' => $firstOut,
                'actual_out' => null,
            ],
            [
                'label' => 'Session 1',
                'scheduled_in' => '13:00',
                'scheduled_out' => '17:00',
                'actual_in' => $thirdIn,
                'actual_out' => $thirdOut,
            ],
            [
                'label' => 'Session 1',
                'scheduled_in' => '13:00',
                'scheduled_out' => '17:00',
                'actual_in' => $thirdOut,
                'actual_out' => null,
            ],
        ];
    }

    private function deterministicMinuteInRange(
        string $idNumber,
        string $workDate,
        string $slot,
        int $minMinute,
        int $maxMinute
    ): int {
        $range = ($maxMinute - $minMinute) + 1;
        $hash = abs(crc32($idNumber.'|'.$workDate.'|'.$slot));

        return $minMinute + ($hash % $range);
    }

    private function formatMinutesToHm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function minutesFromHm(string $hm): int
    {
        [$h, $m] = array_pad(explode(':', $hm), 2, '00');

        return (((int) $h) * 60) + ((int) $m);
    }

    private function seedWorkScheduleTemplate(int $organizationId, CarbonImmutable $seededAt): void
    {
        $segments = [
            ['label' => 'Session 1', 'time_in' => '08:00', 'time_out' => '12:00', 'is_overnight' => false],
            ['label' => 'Session 2', 'time_in' => '13:00', 'time_out' => '17:00', 'is_overnight' => false],
        ];

        DB::table('work_schedule_templates')->updateOrInsert(
            ['organization_id' => $organizationId, 'name' => 'Weekday 8-5 split (Mon-Fri)'],
            [
                'clock_pattern' => WorkScheduleClockPattern::SplitSessions->value,
                'days' => json_encode(['mon', 'tue', 'wed', 'thu', 'fri'], JSON_THROW_ON_ERROR),
                'segments' => json_encode($segments, JSON_THROW_ON_ERROR),
                'time_in' => '08:00',
                'time_out' => '17:00',
                'is_overnight' => false,
                'is_active' => true,
                'unpaid_break_minutes' => 60,
                'grace_late_arrival_minutes' => 10,
                'notes' => 'Phase 1 baseline weekday schedule with two sessions only.',
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    private function seedHolidayTypes(int $organizationId, CarbonImmutable $seededAt): array
    {
        $rows = [
            [
                'slug' => 'builtin-regular',
                'name' => 'Regular Holiday',
                'kind' => 'builtin',
                'color_key' => 'lime',
                'pay_policy' => 'Double Pay',
                'custom_multiplier' => null,
                'premium_note' => 'PH-oriented baseline regular holiday type.',
            ],
            [
                'slug' => 'builtin-special-non-working',
                'name' => 'Special Non-Working Holiday',
                'kind' => 'builtin',
                'color_key' => 'amber',
                'pay_policy' => 'Custom Multiplier',
                'custom_multiplier' => '1.30x',
                'premium_note' => 'PH-oriented baseline special non-working type.',
            ],
            [
                'slug' => 'builtin-special-working',
                'name' => 'Special Working Holiday',
                'kind' => 'builtin',
                'color_key' => 'sky',
                'pay_policy' => 'No Premium',
                'custom_multiplier' => null,
                'premium_note' => 'PH-oriented baseline special working type.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('holiday_types')->updateOrInsert(
                ['organization_id' => $organizationId, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'color_key' => $row['color_key'],
                    'pay_policy' => $row['pay_policy'],
                    'custom_multiplier' => $row['custom_multiplier'],
                    'premium_note' => $row['premium_note'],
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]
            );
        }

        return DB::table('holiday_types')
            ->where('organization_id', $organizationId)
            ->whereIn('slug', ['builtin-regular', 'builtin-special-non-working', 'builtin-special-working'])
            ->pluck('id', 'slug')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function seedCalendarEventCategories(int $organizationId, CarbonImmutable $seededAt): void
    {
        $rows = [
            ['name' => 'Planning', 'slug' => 'planning', 'color_key' => 'blue'],
            ['name' => 'Meeting', 'slug' => 'meeting', 'color_key' => 'indigo'],
            ['name' => 'Review', 'slug' => 'review', 'color_key' => 'violet'],
            ['name' => 'Training', 'slug' => 'training', 'color_key' => 'fuchsia'],
            ['name' => 'Deadline', 'slug' => 'deadline', 'color_key' => 'rose'],
            ['name' => 'Announcement', 'slug' => 'announcement', 'color_key' => 'teal'],
            ['name' => 'Operations', 'slug' => 'operations', 'color_key' => 'emerald'],
            ['name' => 'Compliance', 'slug' => 'compliance', 'color_key' => 'slate'],
            ['name' => 'Incident', 'slug' => 'incident', 'color_key' => 'orange'],
            ['name' => 'Leave', 'slug' => 'leave', 'color_key' => 'emerald'],
            ['name' => 'Team Building', 'slug' => 'team-building', 'color_key' => 'teal'],
            ['name' => 'Audit', 'slug' => 'audit', 'color_key' => 'slate'],
        ];

        foreach ($rows as $row) {
            DB::table('calendar_event_categories')->updateOrInsert(
                ['organization_id' => $organizationId, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'color_key' => $row['color_key'],
                    'is_active' => true,
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                ]
            );
        }
    }

    /**
     * @param  array<string, int>  $holidayTypeIds
     */
    private function seedOrganizationHolidays(int $organizationId, array $holidayTypeIds, CarbonImmutable $seededAt): void
    {
        $yearlyNever = [
            'frequency' => 'yearly',
            'interval' => 1,
            'ends' => ['type' => 'never'],
        ];

        $rows = [
            [
                'name' => "New Year's Day",
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-01',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => 'Fixed date; yearly repetition in calendar.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Christmas Day',
                'start_date' => '2026-12-25',
                'end_date' => '2026-12-25',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => 'Fixed date; widely observed.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Araw ng Kagitingan (Day of Valor)',
                'start_date' => '2026-04-09',
                'end_date' => '2026-04-09',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => 'Republic Act No. 3022 / subsequent laws; verify classification yearly.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Labor Day',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-01',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => 'International Workers’ Day; PH legal holiday.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Independence Day',
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-12',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => null,
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Bonifacio Day',
                'start_date' => '2026-11-30',
                'end_date' => '2026-11-30',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => null,
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Rizal Day',
                'start_date' => '2026-12-30',
                'end_date' => '2026-12-30',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => null,
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Ninoy Aquino Day',
                'start_date' => '2026-08-21',
                'end_date' => '2026-08-21',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => 'Often observed as special non-working; confirm annual proclamation.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => "All Saints' Day",
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => 'Undas; commonly special non-working when proclaimed.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => "All Souls' Day",
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-02',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => 'Common observance in PH; holiday status is proclamation-dependent.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Feast of the Immaculate Conception of Mary',
                'start_date' => '2026-12-08',
                'end_date' => '2026-12-08',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => null,
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Christmas Eve',
                'start_date' => '2026-12-24',
                'end_date' => '2026-12-24',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => 'Frequently declared special non-working by proclamation.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'EDSA People Power Anniversary',
                'start_date' => '2026-02-25',
                'end_date' => '2026-02-25',
                'holiday_type_id' => $holidayTypeIds['builtin-special-working'],
                'notes' => 'Commonly classified as a special working holiday.',
                'recurrence' => $yearlyNever,
            ],
            [
                'name' => 'Maundy Thursday',
                'start_date' => '2026-04-02',
                'end_date' => '2026-04-02',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => '2026 only (Holy Week). Dates move with Easter; replace yearly per proclamation.',
                'recurrence' => null,
            ],
            [
                'name' => 'Good Friday',
                'start_date' => '2026-04-03',
                'end_date' => '2026-04-03',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => '2026 only (Holy Week).',
                'recurrence' => null,
            ],
            [
                'name' => 'National Heroes Day',
                'start_date' => '2026-08-31',
                'end_date' => '2026-08-31',
                'holiday_type_id' => $holidayTypeIds['builtin-regular'],
                'notes' => '2026: last Monday of August. Rule-based date; review yearly.',
                'recurrence' => null,
            ],
            [
                'name' => 'Chinese New Year',
                'start_date' => '2026-02-17',
                'end_date' => '2026-02-17',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => '2026 lunisolar date; verify yearly.',
                'recurrence' => null,
            ],
            [
                'name' => 'Black Saturday',
                'start_date' => '2026-04-04',
                'end_date' => '2026-04-04',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => '2026 Holy Week observance; proclamation-dependent.',
                'recurrence' => null,
            ],
            [
                'name' => 'Last Day of the Year (special non-working — when proclaimed)',
                'start_date' => '2026-12-31',
                'end_date' => '2026-12-31',
                'holiday_type_id' => $holidayTypeIds['builtin-special-non-working'],
                'notes' => 'Observance varies by proclamation; sample 2026 row for demo.',
                'recurrence' => null,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('organization_holidays')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'name' => $row['name'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                ],
                [
                    'holiday_type_id' => $row['holiday_type_id'],
                    'notes' => $row['notes'],
                    'recurrence' => $row['recurrence'] !== null
                        ? json_encode($row['recurrence'], JSON_THROW_ON_ERROR)
                        : null,
                    'set_by_user_id' => null,
                    'last_edited_by_user_id' => null,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedLeavePolicies(int $organizationId, CarbonImmutable $seededAt): void
    {
        $rows = [
            [
                'code' => 'VL',
                'name' => 'Vacation leave',
                'unit' => LeavePolicyUnit::Days->value,
                'annual_entitlement' => 15,
                'use_accrual' => true,
                'accrual_cadence' => LeavePolicyAccrualCadence::Monthly->value,
                'accrual_per_period' => 1.25,
                'max_balance' => 45,
                'carryover_allowed' => true,
                'carryover_cap' => 30,
                'paid' => true,
                'requires_approval' => true,
                'applies_after_months' => null,
                'notes' => 'Phase 1 active leave policy.',
            ],
            [
                'code' => 'SL',
                'name' => 'Sick leave',
                'unit' => LeavePolicyUnit::Days->value,
                'annual_entitlement' => 12,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => 90,
                'carryover_allowed' => true,
                'carryover_cap' => null,
                'paid' => true,
                'requires_approval' => false,
                'applies_after_months' => null,
                'notes' => 'Phase 1 active leave policy.',
            ],
            [
                'code' => 'PL',
                'name' => 'Parental leave',
                'unit' => LeavePolicyUnit::Days->value,
                'annual_entitlement' => 105,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => null,
                'carryover_allowed' => false,
                'carryover_cap' => null,
                'paid' => true,
                'requires_approval' => true,
                'applies_after_months' => null,
                'notes' => 'Phase 1 active leave policy.',
            ],
            [
                'code' => 'LWOP',
                'name' => 'Leave without pay',
                'unit' => LeavePolicyUnit::Days->value,
                'annual_entitlement' => 0,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => null,
                'carryover_allowed' => false,
                'carryover_cap' => null,
                'paid' => false,
                'requires_approval' => true,
                'applies_after_months' => 6,
                'notes' => 'Phase 1 active leave policy.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('leave_policies')->updateOrInsert(
                ['organization_id' => $organizationId, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'annual_entitlement' => $row['annual_entitlement'],
                    'use_accrual' => $row['use_accrual'],
                    'accrual_cadence' => $row['accrual_cadence'],
                    'accrual_per_period' => $row['accrual_per_period'],
                    'max_balance' => $row['max_balance'],
                    'carryover_allowed' => $row['carryover_allowed'],
                    'carryover_cap' => $row['carryover_cap'],
                    'paid' => $row['paid'],
                    'requires_approval' => $row['requires_approval'],
                    'applies_after_months' => $row['applies_after_months'],
                    'is_active' => true,
                    'notes' => $row['notes'],
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'deleted_by_user_id' => null,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedOvertimePolicies(int $organizationId, CarbonImmutable $seededAt): void
    {
        $rows = [
            [
                'code' => 'OT-WD',
                'name' => 'Weekday overtime',
                'context' => OvertimePolicyContext::OrdinaryWeekday->value,
                'rate_multiplier' => 1.25,
                'daily_threshold_hours' => 8,
                'daily_cap_hours' => 4,
                'weekly_cap_hours' => 24,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 24,
                'notes' => 'Phase 1 active overtime policy.',
            ],
            [
                'code' => 'OT-RD',
                'name' => 'Rest day overtime',
                'context' => OvertimePolicyContext::RestDay->value,
                'rate_multiplier' => 1.30,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 48,
                'notes' => 'Phase 1 active overtime policy.',
            ],
            [
                'code' => 'OT-RH',
                'name' => 'Regular holiday work',
                'context' => OvertimePolicyContext::RegularHoliday->value,
                'rate_multiplier' => 2.00,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => null,
                'notes' => 'Phase 1 active overtime policy.',
            ],
            [
                'code' => 'OT-SH',
                'name' => 'Special holiday work',
                'context' => OvertimePolicyContext::SpecialHoliday->value,
                'rate_multiplier' => 1.30,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 24,
                'notes' => 'Phase 1 active overtime policy.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('overtime_policies')->updateOrInsert(
                ['organization_id' => $organizationId, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'context' => $row['context'],
                    'rate_multiplier' => $row['rate_multiplier'],
                    'daily_threshold_hours' => $row['daily_threshold_hours'],
                    'daily_cap_hours' => $row['daily_cap_hours'],
                    'weekly_cap_hours' => $row['weekly_cap_hours'],
                    'requires_approval' => $row['requires_approval'],
                    'minimum_lead_time_hours' => $row['minimum_lead_time_hours'],
                    'is_active' => true,
                    'notes' => $row['notes'],
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'deleted_by_user_id' => null,
                    'created_at' => $seededAt,
                    'updated_at' => $seededAt,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedBootstrapAccount(
        int $organizationId,
        int $rootUnitId,
        int $roleId,
        int $positionId,
        string $idNumber,
        string $firstName,
        string $lastName,
        string $birthdate,
        string $sex,
        string $civilStatus,
        string $nationality,
        ?string $religion,
        string $hireDate,
        string $email,
        CarbonImmutable $seededAt,
        string $passwordPlain = 'password',
        ?CarbonImmutable $userUpdatedAt = null,
    ): void {
        DB::table('employees')->updateOrInsert(
            ['id_number' => $idNumber],
            [
                'attendance_id' => null,
                'work_schedule_template_id' => null,
                'first_name' => $firstName,
                'middle_name' => null,
                'last_name' => $lastName,
                'suffix' => null,
                'birthdate' => $birthdate,
                'birthday_visibility' => 'team',
                'sex' => $sex,
                'civil_status' => $civilStatus,
                'nationality' => $nationality,
                'religion' => $religion,
                'religion_other' => null,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
                'deleted_at' => null,
            ]
        );

        $employeeId = (int) DB::table('employees')->where('id_number', $idNumber)->value('id');

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'employee_id' => $employeeId,
                'name' => trim($firstName.' '.$lastName),
                'password' => Hash::make($passwordPlain),
                'email_verified_at' => $seededAt,
                'remember_token' => null,
                'created_at' => $seededAt,
                'updated_at' => $userUpdatedAt ?? $seededAt,
            ]
        );

        $userId = (int) DB::table('users')->where('email', $email)->value('id');

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $roleId],
            [
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
            ]
        );

        DB::table('employee_employments')->updateOrInsert(
            ['employee_id' => $employeeId, 'hire_date' => $hireDate],
            [
                'uuid' => (string) Str::uuid(),
                'separation_date' => null,
                'separation_reason' => null,
                'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
                'is_current' => true,
                'notes' => 'Phase 1 bootstrap employment.',
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
                'deleted_at' => null,
            ]
        );

        $employmentId = (int) DB::table('employee_employments')
            ->where('employee_id', $employeeId)
            ->where('hire_date', $hireDate)
            ->value('id');

        DB::table('employee_affiliations')->updateOrInsert(
            [
                'employee_id' => $employeeId,
                'employee_employment_id' => $employmentId,
                'organization_id' => $organizationId,
                'root_unit_id' => $rootUnitId,
                'start_date' => $hireDate,
            ],
            [
                'is_primary' => true,
                'end_date' => null,
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
                'deleted_at' => null,
            ]
        );

        DB::table('employee_positions')->updateOrInsert(
            [
                'employee_id' => $employeeId,
                'employee_employment_id' => $employmentId,
                'position_id' => $positionId,
                'start_date' => $hireDate,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'is_primary' => true,
                'end_date' => null,
                'notes' => 'Phase 1 bootstrap position.',
                'created_at' => $seededAt,
                'updated_at' => $seededAt,
                'deleted_at' => null,
            ]
        );
    }
}
