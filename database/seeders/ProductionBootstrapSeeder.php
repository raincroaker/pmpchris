<?php

namespace Database\Seeders;

use App\Enums\EmployeeBirthdayVisibility;
use App\Models\Area;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal PMPC bootstrap for a fresh database: Panabo-root org structure only, two seeded accounts,
 * baseline positions (including HR Admin), Mon–Sat work schedules, policies, holidays, calendar categories —
 * without demo calendar events, attendance rows, assignments, leaves, or overtimes.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public const POSITION_CODE_HR_ADMIN = 'HR-ADM';

    public function run(): void
    {
        $this->assertConfiguration();

        $this->call(OrganizationalStructureSeeder::class);
        $this->call(RoleSeeder::class);

        $organization = $this->ensureOrganization();

        $panaboRoot = $this->ensurePanaboRootBranch($organization);

        $this->seedPositionCatalog($organization);

        [$superEmployee] = $this->ensureSuperAdminAccount();

        [$hrEmployee] = $this->ensureHrHeadAccount();

        $superEmployment = $this->ensureBootstrapEmployment(
            $superEmployee,
            '2026-02-23',
            'Bootstrap: Super Administrator (Krysta Magallanes); current tenure.',
        );

        $hrEmployment = $this->ensureBootstrapEmployment(
            $hrEmployee,
            '2016-06-24',
            'Bootstrap: HR Head (Kenneth Martinez); current tenure.',
        );

        foreach (
            [
                [$superEmployee, $superEmployment],
                [$hrEmployee, $hrEmployment],
            ] as [$employee, $employment]
        ) {
            $this->ensureHrAdminEmployeePosition((int) $organization->id, $employee, $employment);
            $this->ensurePanaboAffiliationOnly((int) $organization->id, $panaboRoot, $employee, $employment);
        }

        $this->call(WorkScheduleTemplatesSeeder::class);

        $this->assignMonSatTemplates($organization, $superEmployee, $hrEmployee);

        $this->call(LeaveAndOvertimePoliciesSeeder::class);
        $this->call(HolidayTypesSeeder::class);
        $this->call(OrganizationHolidaysSeeder::class);
        $this->call(CalendarEventCategoriesSeeder::class);
    }

    private function assertConfiguration(): void
    {
        if (blank(config('production_bootstrap.super_admin_email'))) {
            throw new InvalidArgumentException('Set PROD_BOOTSTRAP_SUPER_ADMIN_EMAIL before running ProductionBootstrapSeeder.');
        }

        if (blank(config('production_bootstrap.hr_head_email'))) {
            throw new InvalidArgumentException('Set PROD_BOOTSTRAP_HR_HEAD_EMAIL before running ProductionBootstrapSeeder.');
        }

        if ((string) config('production_bootstrap.organization_code') !== 'PMPC') {
            throw new InvalidArgumentException(
                'ProductionBootstrapSeeder currently requires PMPC (`PROD_BOOTSTRAP_ORGANIZATION_CODE` or `DEFAULT_ORGANIZATION_CODE`).',
            );
        }

        if (! app()->environment('production')) {
            return;
        }

        if (blank(config('production_bootstrap.super_admin_password'))) {
            throw new InvalidArgumentException('Set PROD_BOOTSTRAP_SUPER_ADMIN_PASSWORD in production before running ProductionBootstrapSeeder.');
        }

        if (blank(config('production_bootstrap.hr_head_password'))) {
            throw new InvalidArgumentException('Set PROD_BOOTSTRAP_HR_HEAD_PASSWORD in production before running ProductionBootstrapSeeder.');
        }
    }

    private function ensureOrganization(): Organization
    {
        $code = (string) config('production_bootstrap.organization_code');

        return Organization::query()->firstOrCreate(
            ['code' => $code],
            ['name' => 'Panabo Multipurpose Cooperative', 'is_active' => true],
        );
    }

    private function ensurePanaboRootBranch(Organization $organization): OrganizationalUnit
    {
        $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

        Area::query()->firstOrCreate(
            ['code' => 'AREA-DAVNOR'],
            [
                'name' => 'Davao del Norte',
                'organization_id' => $organization->id,
                'is_active' => true,
            ],
        );

        $davNor = Area::query()->where('code', 'AREA-DAVNOR')->first();

        /** @var OrganizationalUnit */
        return OrganizationalUnit::query()->updateOrCreate(
            ['code' => 'PAN', 'organization_id' => $organization->id],
            [
                'name' => 'Panabo Branch',
                'unit_type_id' => $branchType->id,
                'parent_id' => null,
                'area_id' => $davNor?->id,
                'is_active' => true,
            ],
        );
    }

    private function seedPositionCatalog(Organization $organization): void
    {
        $positionCatalog = [
            ['code' => self::POSITION_CODE_HR_ADMIN, 'title' => 'HR Admin', 'description' => 'Human resources administration, records, policy execution, and staff support coordination.'],
            ['code' => 'HR-MGR', 'title' => 'HR Manager', 'description' => 'Leads HR operations, policy implementation, and workforce planning.'],
            ['code' => 'HR-SPEC', 'title' => 'HR Specialist', 'description' => 'Handles recruitment, onboarding, and employee records.'],
            ['code' => 'PAYROLL-OFF', 'title' => 'Payroll Officer', 'description' => 'Processes payroll cycles and statutory contributions.'],
            ['code' => 'LOAN-OFC', 'title' => 'Loan Officer', 'description' => 'Manages member loan applications and client interviews.'],
            ['code' => 'CREDIT-ANL', 'title' => 'Credit Analyst', 'description' => 'Evaluates repayment capacity and credit risk.'],
            ['code' => 'MEMBER-SVC', 'title' => 'Member Services Representative', 'description' => 'Supports member concerns and account inquiries.'],
            ['code' => 'BR-OPS-SUP', 'title' => 'Branch Operations Supervisor', 'description' => 'Oversees daily branch operations and service delivery.'],
            ['code' => 'TELLER', 'title' => 'Teller', 'description' => 'Handles over-the-counter transactions and cash balancing.'],
            ['code' => 'IT-SUP', 'title' => 'IT Support Specialist', 'description' => 'Provides user support and endpoint maintenance.'],
            ['code' => 'COMPLIANCE-OFF', 'title' => 'Compliance Officer', 'description' => 'Monitors policy adherence and regulatory compliance.'],
        ];

        foreach ($positionCatalog as $row) {
            Position::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $row['code'],
                ],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array{0: Employee, 1: User}
     */
    private function ensureSuperAdminAccount(): array
    {
        $idNumber = (string) config('production_bootstrap.super_admin_id_number');
        $email = (string) config('production_bootstrap.super_admin_email');

        $employee = Employee::query()->updateOrCreate(
            ['id_number' => $idNumber],
            [
                'first_name' => 'Krysta',
                'middle_name' => null,
                'last_name' => 'Magallanes',
                'suffix' => null,
                'birthdate' => '1990-01-15',
                'birthday_visibility' => EmployeeBirthdayVisibility::Private,
                'sex' => 'female',
                'civil_status' => 'single',
                'nationality' => 'Filipino',
                'religion' => null,
            ],
        );

        $user = $this->persistBootstrapUserForEmployee(
            $email,
            'Krysta Magallanes',
            $employee,
            'super_admin_password',
            [Role::CODE_SUPER_ADMIN],
        );

        return [$employee, $user];
    }

    /**
     * @return array{0: Employee, 1: User}
     */
    private function ensureHrHeadAccount(): array
    {
        $idNumber = (string) config('production_bootstrap.hr_head_id_number');
        $email = (string) config('production_bootstrap.hr_head_email');

        $employee = Employee::query()->updateOrCreate(
            ['id_number' => $idNumber],
            [
                'first_name' => 'Kenneth',
                'middle_name' => null,
                'last_name' => 'Martinez',
                'suffix' => null,
                'birthdate' => '1985-06-10',
                'birthday_visibility' => EmployeeBirthdayVisibility::Branch,
                'sex' => 'male',
                'civil_status' => 'married',
                'nationality' => 'Filipino',
                'religion' => null,
            ],
        );

        $user = $this->persistBootstrapUserForEmployee(
            $email,
            'Kenneth Martinez',
            $employee,
            'hr_head_password',
            [Role::CODE_HR_HEAD],
        );

        return [$employee, $user];
    }

    /**
     * @param  list<string>  $roleCodes
     */
    private function persistBootstrapUserForEmployee(
        string $email,
        string $fullName,
        Employee $employee,
        string $passwordConfigKey,
        array $roleCodes,
    ): User {
        $plainPassword = config('production_bootstrap.'.$passwordConfigKey);

        /** @var User $user */
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $fullName;
        $user->employee_id = $employee->id;
        $user->email_verified_at ??= now();

        $alreadyExists = $user->exists;

        if (! $alreadyExists || filled($plainPassword)) {
            $user->password = filled($plainPassword)
                ? Hash::make((string) $plainPassword)
                : Hash::make('password');
        }

        $user->save();

        if (count($roleCodes) !== 1) {
            throw new InvalidArgumentException('Each bootstrap login must sync exactly one application role.');
        }

        $user->syncRolesByCode($roleCodes);

        return $user->fresh() ?? $user;
    }

    private function ensureBootstrapEmployment(Employee $employee, string $hireDateIso, string $notes): EmployeeEmployment
    {
        /** @var EmployeeEmployment|null $employment */
        $employment = EmployeeEmployment::query()
            ->where('employee_id', (int) $employee->id)
            ->where('is_current', true)
            ->orderByDesc('hire_date')
            ->first();

        $payload = [
            'hire_date' => $hireDateIso,
            'separation_date' => null,
            'separation_reason' => null,
            'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
            'is_current' => true,
            'notes' => $notes,
        ];

        if ($employment !== null) {
            $employment->update($payload);

            return $employment->fresh() ?? $employment;
        }

        return EmployeeEmployment::query()->create(array_merge([
            'employee_id' => (int) $employee->id,
        ], $payload));
    }

    private function ensureHrAdminEmployeePosition(
        int $organizationId,
        Employee $employee,
        EmployeeEmployment $employment,
    ): void {
        $positionKey = Position::query()
            ->where('organization_id', $organizationId)
            ->where('code', self::POSITION_CODE_HR_ADMIN)
            ->where('is_active', true)
            ->value('id');

        if ($positionKey === null) {
            throw new RuntimeException(self::POSITION_CODE_HR_ADMIN.' position missing.');
        }

        EmployeePosition::query()->updateOrCreate(
            [
                'employee_id' => (int) $employee->id,
                'employee_employment_id' => (int) $employment->id,
                'position_id' => (int) $positionKey,
                'is_primary' => true,
            ],
            [
                'start_date' => CarbonImmutable::parse((string) $employment->hire_date)->toDateString(),
                'end_date' => null,
                'notes' => 'Bootstrap primary job title.',
            ],
        );
    }

    private function ensurePanaboAffiliationOnly(
        int $organizationId,
        OrganizationalUnit $panaboRoot,
        Employee $employee,
        EmployeeEmployment $employment,
    ): void {
        EmployeeAffiliation::query()
            ->where('organization_id', $organizationId)
            ->where('employee_id', (int) $employee->id)
            ->forceDelete();

        $startDate = CarbonImmutable::parse((string) $employment->hire_date)->toDateString();

        EmployeeAffiliation::query()->create([
            'employee_id' => (int) $employee->id,
            'employee_employment_id' => (int) $employment->id,
            'organization_id' => $organizationId,
            'root_unit_id' => (int) $panaboRoot->id,
            'is_primary' => true,
            'start_date' => $startDate,
            'end_date' => null,
        ]);
    }

    private function assignMonSatTemplates(
        Organization $organization,
        Employee $superEmployee,
        Employee $hrEmployee,
    ): void {
        /** @var WorkScheduleTemplate|null $template */
        $template = WorkScheduleTemplate::query()
            ->where('organization_id', (int) $organization->id)
            ->where('name', WorkScheduleTemplatesSeeder::TEMPLATE_NAME_MON_SAT_SPLIT_OT)
            ->first();

        if ($template === null) {
            throw new RuntimeException('Mon–Sat work schedule template not found.');
        }

        $templateId = (int) $template->id;

        foreach ([$superEmployee, $hrEmployee] as $who) {
            $who->forceFill(['work_schedule_template_id' => $templateId])->save();
        }
    }
}
