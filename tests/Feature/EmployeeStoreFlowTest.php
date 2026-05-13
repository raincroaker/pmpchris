<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
});

function createRootForOrg(Organization $organization): OrganizationalUnit
{
    $unitType = UnitType::factory()->create(['can_be_root' => true, 'is_active' => true]);

    return OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $unitType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
}

function employeePayload(?int $rootUnitId): array
{
    return [
        'personal_info' => [
            'first_name' => 'Aira',
            'last_name' => 'Reyes',
            'middle_name' => null,
            'suffix' => null,
            'birthdate' => '1999-01-01',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'nationality' => 'Filipino',
            'religion' => 'Catholic',
        ],
        'employment' => [
            'id_number' => 'EMP-'.fake()->numerify('#####'),
            'attendance_id' => null,
            'hire_date' => '2026-01-01',
            'separation_date' => null,
            'employment_status' => 'active',
            'affiliations' => [
                [
                    'root_unit_id' => $rootUnitId,
                    'start_date' => '2026-01-01',
                    'end_date' => null,
                    'is_primary' => true,
                ],
            ],
            'positions' => [],
        ],
        'addresses' => [
            [
                'type' => 'permanent',
                'address_line_1' => '123 Main',
                'address_line_2' => null,
                'barangay' => 'Barangay 1',
                'barangay_code' => '0123',
                'city' => 'Makati',
                'city_code' => '0456',
                'province' => 'Metro Manila',
                'province_code' => '0789',
                'zip_code' => '1200',
                'country' => 'Philippines',
                'is_primary' => true,
            ],
        ],
        'contacts' => [
            [
                'category' => 'personal',
                'type' => 'mobile',
                'contact_person' => null,
                'relationship' => null,
                'contact_number' => '09171234567',
                'email' => null,
                'is_primary' => true,
            ],
        ],
        'create_user_account' => true,
        'user_account' => [
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ],
    ];
}

test('employee role cannot store employee', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-ROLE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-ROLE']);
    $root = createRootForOrg($organization);
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), employeePayload(null))
        ->assertForbidden();
});

test('hr manager cannot create org wide affiliation', function () {
    $organization = Organization::factory()->create(['code' => 'T-HRM-ORGWIDE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HRM-ORGWIDE']);

    $root = createRootForOrg($organization);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), employeePayload(null))
        ->assertSessionHasErrors(['employment.affiliations.0.root_unit_id']);
});

test('hr manager cannot create affiliation on unmanaged root', function () {
    $organization = Organization::factory()->create(['code' => 'T-HRM-ROOT', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HRM-ROOT']);

    $root = createRootForOrg($organization);
    $sessionRoot = createRootForOrg($organization);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $sessionRoot->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $sessionRoot->id])
        ->post(route('employees.store'), employeePayload($root->id))
        ->assertSessionHasErrors(['employment.affiliations.0.root_unit_id']);
});

test('hr manager cannot create employee on affiliated but unmanaged root', function () {
    $organization = Organization::factory()->create(['code' => 'T-HRM-AFFIL-ONLY', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HRM-AFFIL-ONLY']);

    $affiliatedRoot = createRootForOrg($organization);
    $sessionRoot = createRootForOrg($organization);

    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
    ]);
    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create([
        'employee_id' => $employee->id,
    ]);
    BranchManager::query()->create([
        'user_id' => $manager->id,
        'root_unit_id' => $sessionRoot->id,
        'is_active' => true,
    ]);
    EmployeeAffiliation::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $affiliatedRoot->id,
        'is_primary' => true,
        'start_date' => now()->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $affiliatedRoot->id])
        ->post(route('employees.store'), employeePayload($affiliatedRoot->id))
        ->assertSessionHasErrors(['employment.affiliations.0.root_unit_id']);
});

test('hr manager can create employee only on managed root', function () {
    $organization = Organization::factory()->create(['code' => 'T-HRM-ALLOW', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HRM-ALLOW']);

    $root = createRootForOrg($organization);

    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $manager->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), employeePayload($root->id))
        ->assertRedirect(route('employees'))
        ->assertSessionHas('success', 'Employee created successfully.');

    $employee = Employee::query()->firstOrFail();
    expect($employee->currentEmployment?->employment_status)->toBe('active');
});

test('super admin can create org wide employee', function () {
    $organization = Organization::factory()->create(['code' => 'T-SA-ORGWIDE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SA-ORGWIDE']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), employeePayload(null))
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    expect($employee->affiliations()->first()?->root_unit_id)->toBeNull();
});

test('hr head plus hr manager can create org wide employee', function () {
    $organization = Organization::factory()->create(['code' => 'T-HRH-OVERRIDE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HRH-OVERRIDE']);

    $root = createRootForOrg($organization);
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD, Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), employeePayload(null))
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    expect($employee->affiliations()->first()?->root_unit_id)->toBeNull();
});

test('store flow is transactional when downstream write fails', function () {
    $organization = Organization::factory()->create(['code' => 'T-TX-ROLLBACK', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-TX-ROLLBACK']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();
    Role::query()->where('code', Role::CODE_EMPLOYEE)->delete();

    $this->withoutExceptionHandling();
    expect(function () use ($admin, $root): void {
        $this->actingAs($admin)
            ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
            ->post(route('employees.store'), employeePayload(null));
    })->toThrow(ModelNotFoundException::class);

    expect(Employee::query()->count())->toBe(0);
});

test('store employee rejects active status when separation date is set', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-SEP', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-SEP']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2020-01-01';
    $payload['employment']['separation_date'] = '2024-12-31';
    $payload['employment']['employment_status'] = 'active';
    $payload['employment']['affiliations'][0]['start_date'] = '2020-01-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.employment_status']);
});

test('store employee can create separated employment with resigned status', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-RESIGN', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-RESIGN']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-03-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    $employment = $employee->employments()->first();
    expect($employment)->not->toBeNull()
        ->and($employment->employment_status)->toBe('resigned')
        ->and($employment->is_current)->toBeFalse()
        ->and($employment->separation_date?->toDateString())->toBe('2025-03-01')
        ->and($employee->currentEmployment)->toBeNull()
        ->and($employee->affiliations()->first()?->end_date?->toDateString())->toBe('2025-03-01');
});

test('store employee can create separated employment with contract ended status', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-C-END', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-C-END']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2021-06-01';
    $payload['employment']['separation_date'] = '2025-04-01';
    $payload['employment']['employment_status'] = 'contract_ended';
    $payload['employment']['separation_reason'] = 'Contract period completed';
    $payload['employment']['notes'] = 'Potential employee for rehiring next quarter.';
    $payload['employment']['affiliations'][0]['start_date'] = '2021-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-04-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    $employment = $employee->employments()->first();
    expect($employment)->not->toBeNull()
        ->and($employment->employment_status)->toBe('contract_ended')
        ->and($employment->is_current)->toBeFalse()
        ->and($employment->separation_date?->toDateString())->toBe('2025-04-01')
        ->and($employment->separation_reason)->toBe('Contract period completed')
        ->and($employment->notes)->toBe('Potential employee for rehiring next quarter.')
        ->and($employee->currentEmployment)->toBeNull()
        ->and($employee->affiliations()->first()?->end_date?->toDateString())->toBe('2025-04-01');
});

test('store employee rejects missing affiliation end date when separation is set', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-AFF', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-AFF']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations.0.end_date']);
});

test('store employee rejects affiliation end date after separation date', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-AFF-END', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-AFF-END']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-04-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations.0.end_date']);
});

test('store employee rejects affiliation start date after separation date', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-AFF-START', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-AFF-START']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2025-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-06-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations.0.start_date']);
});

test('store employee rejects missing position end date when separation is set', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-POS', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-POS']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-03-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-01-01',
        'end_date' => null,
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions.0.end_date']);
});

test('store employee rejects position end date after separation date', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-POS-DT', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-POS-DT']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-03-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-01-01',
        'end_date' => '2025-06-01',
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions.0.end_date']);
});

test('store employee rejects affiliation start date before hire date', function () {
    $organization = Organization::factory()->create(['code' => 'T-HIRE-AFF-START', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HIRE-AFF-START']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2020-06-01';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-01-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations.0.start_date']);
});

test('store employee rejects affiliation end date before hire date', function () {
    $organization = Organization::factory()->create(['code' => 'T-HIRE-AFF-END', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HIRE-AFF-END']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2020-06-01';
    $payload['employment']['affiliations'][0]['start_date'] = '2020-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2020-05-31';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations.0.end_date']);
});

test('store employee rejects position start date before hire date', function () {
    $organization = Organization::factory()->create(['code' => 'T-HIRE-POS-START', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HIRE-POS-START']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2020-06-01';
    $payload['employment']['affiliations'][0]['start_date'] = '2020-06-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2019-01-01',
        'end_date' => null,
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions.0.start_date']);
});

test('store employee rejects position end date before hire date', function () {
    $organization = Organization::factory()->create(['code' => 'T-HIRE-POS-END', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-HIRE-POS-END']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2020-06-01';
    $payload['employment']['affiliations'][0]['start_date'] = '2020-06-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-06-01',
        'end_date' => '2020-05-31',
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions.0.end_date']);
});

test('store employee rejects position start date after separation date', function () {
    $organization = Organization::factory()->create(['code' => 'T-SEP-POS-START', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-SEP-POS-START']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-03-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2025-06-01',
        'end_date' => '2025-06-30',
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions.0.start_date']);
});

test('store employee rejects affiliations with no primary selection', function () {
    $organization = Organization::factory()->create(['code' => 'T-AFF-NO-PRIMARY', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-AFF-NO-PRIMARY']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['affiliations'][0]['is_primary'] = false;

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations']);
});

test('store employee rejects positions with no primary selection', function () {
    $organization = Organization::factory()->create(['code' => 'T-POS-NO-PRIMARY', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-POS-NO-PRIMARY']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-01-01',
        'end_date' => null,
        'is_primary' => false,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions']);
});

test('store employee rejects ended-only affiliations when no separation date is provided', function () {
    $organization = Organization::factory()->create(['code' => 'T-AFF-NO-ACTIVE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-AFF-NO-ACTIVE']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['affiliations'][0]['end_date'] = '2026-02-01';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.affiliations']);
});

test('store employee rejects ended-only positions when no separation date is provided', function () {
    $organization = Organization::factory()->create(['code' => 'T-POS-NO-ACTIVE', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-POS-NO-ACTIVE']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-01-01',
        'end_date' => '2020-12-31',
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['employment.positions']);
});

test('store employee allows ended primary position when separation date exists', function () {
    $organization = Organization::factory()->create(['code' => 'T-POS-END-PRIMARY-SEP', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-POS-END-PRIMARY-SEP']);

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['employment']['hire_date'] = '2019-06-01';
    $payload['employment']['separation_date'] = '2025-03-01';
    $payload['employment']['employment_status'] = 'resigned';
    $payload['employment']['affiliations'][0]['start_date'] = '2019-06-01';
    $payload['employment']['affiliations'][0]['end_date'] = '2025-03-01';
    $payload['employment']['positions'] = [[
        'position_id' => $position->id,
        'start_date' => '2020-01-01',
        'end_date' => '2025-03-01',
        'is_primary' => true,
    ]];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));
});

test('store employee without user account creates employee only', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-NO-USER', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-NO-USER']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $beforeUserCount = User::query()->count();

    $payload = employeePayload(null);
    unset($payload['user_account']);
    $payload['create_user_account'] = false;

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));

    expect(User::query()->count())->toBe($beforeUserCount);

    $employee = Employee::query()->firstOrFail();
    expect($employee->user()->exists())->toBeFalse();
});

test('store employee with empty addresses array creates no employee addresses', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-0-ADDR', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-0-ADDR']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['addresses'] = [];

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    expect(EmployeeAddress::query()->where('employee_id', $employee->id)->count())->toBe(0);
});

test('store employee allows null civil status and nationality', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-NULL-DEMO', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-NULL-DEMO']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['personal_info']['civil_status'] = null;
    $payload['personal_info']['nationality'] = null;

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertRedirect(route('employees'));

    $employee = Employee::query()->firstOrFail();
    expect($employee->civil_status)->toBeNull()
        ->and($employee->nationality)->toBeNull();
});

test('store employee rejects invalid civil status when provided', function () {
    $organization = Organization::factory()->create(['code' => 'T-EMP-BAD-CIV', 'is_active' => true]);
    config(['hris.default_organization_code' => 'T-EMP-BAD-CIV']);

    $root = createRootForOrg($organization);
    $admin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $payload = employeePayload(null);
    $payload['personal_info']['civil_status'] = 'Not a real status';

    $this->actingAs($admin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $root->id])
        ->post(route('employees.store'), $payload)
        ->assertSessionHasErrors(['personal_info.civil_status']);
});
