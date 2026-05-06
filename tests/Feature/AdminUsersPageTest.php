<?php

use App\Models\BranchManager;
use App\Models\Employee;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('admin users route redirects guests to login', function () {
    $this->get(route('admin.users'))
        ->assertRedirect(route('login'));
});

test('employee role cannot access admin users page', function () {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('hr head can access admin users page and receives role rows', function () {
    $role = Role::query()->where('code', Role::CODE_HR_MANAGER)->firstOrFail();
    $assigned = User::factory()->create([
        'name' => 'Role Assigned',
        'email' => 'role-assigned@example.com',
    ]);
    $assigned->roles()->attach($role->id);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->has('roles.data')
            ->has('users.data')
            ->has('roleFilterOptions')
            ->where('filters.view', 'users')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 10)
            ->where('filters.role_id', null)
            ->where('filters.org_scope', null)
            ->where('filters.unit_id', null)
            ->where('filters.account_status', 'all'));
});

test('super admin can access admin users page', function () {
    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users'));
});

test('hr manager cannot access admin users page without managed workspace branch', function () {
    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertRedirect(route('dashboard'));
});

test('hr manager can access admin users page when assigned to selected branch', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-USR-HRM']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-USR-HRM',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'HRM-ROOT',
        'name' => 'HRM Branch Root',
        'is_active' => true,
    ]);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::factory()->create([
        'user_id' => $viewer->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => null,
    ]);

    $this->actingAs($viewer)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $root->code,
                'name' => (string) $root->name,
            ],
        ])
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('viewerCanManageRoles', false)
            ->where('viewerCanUseEmploymentStateFilter', false));
});

test('hr head can access admin users page', function () {
    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users'));
});

test('admin users page supports search and users_count sorting', function () {
    $targetRole = Role::query()->where('code', Role::CODE_HR_MANAGER)->firstOrFail();
    $userA = User::factory()->create(['name' => 'Sort A', 'email' => 'sort-a@example.com']);
    $userB = User::factory()->create(['name' => 'Sort B', 'email' => 'sort-b@example.com']);
    $targetRole->users()->attach([$userA->id, $userB->id]);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'search' => 'hr manager',
            'sort' => 'users_count',
            'direction' => 'desc',
            'per_page' => 5,
            'page' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'users')
            ->where('filters.search', 'hr manager')
            ->where('filters.sort', 'users_count')
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 5)
            ->where('filters.role_id', null)
            ->where('filters.org_scope', null)
            ->where('filters.unit_id', null)
            ->where('filters.account_status', 'all'));
});

test('admin users page supports users tab state via view query', function () {
    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'search' => 'example.com',
            'sort' => 'code',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->has('users.data')
            ->where('filters.view', 'users')
            ->where('filters.search', 'example.com')
            ->where('filters.sort', 'code')
            ->where('filters.role_id', null)
            ->where('filters.org_scope', null)
            ->where('filters.unit_id', null)
            ->where('filters.account_status', 'all'));
});

test('users tab supports role filter by role id', function () {
    $hrManagerRole = Role::query()->where('code', Role::CODE_HR_MANAGER)->firstOrFail();
    $employeeRole = Role::query()->where('code', Role::CODE_EMPLOYEE)->firstOrFail();

    $hrManagerUser = User::factory()->create(['email' => 'hrm-filter@example.com']);
    $employeeUser = User::factory()->create(['email' => 'emp-filter@example.com']);
    $hrManagerUser->roles()->attach($hrManagerRole->id);
    $employeeUser->roles()->attach($employeeRole->id);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'role_id' => $hrManagerRole->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'users')
            ->where('filters.role_id', (int) $hrManagerRole->id)
            ->where('filters.org_scope', null)
            ->where('filters.unit_id', null)
            ->where('filters.account_status', 'all'));
});

test('picker user sees only current branch and org-wide users in users tab', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-USR-BR']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-USR-BR',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'BRA-ADMIN',
        'name' => 'Branch A Admin',
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'BRB-ADMIN',
        'name' => 'Branch B Admin',
    ]);

    $branchEmployee = Employee::factory()->create();
    $branchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($branchEmployee)->create([
        'employee_employment_id' => $branchEmployment->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    $branchUser = User::factory()->create([
        'name' => 'Branch User',
        'email' => 'branch-user@example.com',
        'employee_id' => $branchEmployee->id,
    ]);
    $branchUser->assignRole(Role::CODE_EMPLOYEE);

    $orgWideEmployee = Employee::factory()->create();
    $orgWideEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $orgWideEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($orgWideEmployee)->create([
        'employee_employment_id' => $orgWideEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);
    $orgWideUser = User::factory()->create([
        'name' => 'Orgwide User',
        'email' => 'orgwide-user@example.com',
        'employee_id' => $orgWideEmployee->id,
    ]);
    $orgWideUser->assignRole(Role::CODE_EMPLOYEE);

    $otherBranchEmployee = Employee::factory()->create();
    $otherBranchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $otherBranchEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($otherBranchEmployee)->create([
        'employee_employment_id' => $otherBranchEmployment->id,
        'root_unit_id' => $rootB->id,
        'end_date' => null,
    ]);
    $otherBranchUser = User::factory()->create([
        'name' => 'Other Branch User',
        'email' => 'other-branch-user@example.com',
        'employee_id' => $otherBranchEmployee->id,
    ]);
    $otherBranchUser->assignRole(Role::CODE_EMPLOYEE);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('admin.users', [
            'view' => 'users',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'users')
            ->where('filters.account_status', 'all')
            ->has('users.data', 2)
            ->where('users.data.0.email', 'branch-user@example.com')
            ->where('users.data.1.email', 'orgwide-user@example.com'));
});

test('users tab supports org scope filter for org-wide users', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-USR-SCOPE',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-ADMIN-USR-SCOPE']);

    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
    ]);

    $orgWideEmployee = Employee::factory()->create();
    $orgWideEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $orgWideEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($orgWideEmployee)->create([
        'employee_employment_id' => $orgWideEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);
    $orgWideUser = User::factory()->create([
        'name' => 'Org Wide Filter User',
        'email' => 'org-scope@example.com',
        'employee_id' => $orgWideEmployee->id,
    ]);
    $orgWideUser->assignRole(Role::CODE_EMPLOYEE);

    $branchEmployee = Employee::factory()->create();
    $branchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($branchEmployee)->create([
        'employee_employment_id' => $branchEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    $branchUser = User::factory()->create([
        'name' => 'Branch Scope Filter User',
        'email' => 'branch-scope@example.com',
        'employee_id' => $branchEmployee->id,
    ]);
    $branchUser->assignRole(Role::CODE_EMPLOYEE);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'org_scope' => 'org_wide',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'users')
            ->where('filters.org_scope', 'org_wide')
            ->where('filters.unit_id', null)
            ->where('filters.account_status', 'all')
            ->has('users.data', 1)
            ->where('users.data.0.email', 'org-scope@example.com'));
});

test('users tab supports account status filter', function () {
    config(['hris.branch_picker_enabled' => false]);

    $linkedEmployee = Employee::factory()->create(['id_number' => 'EMP-LINK-01']);
    $linkedUser = User::factory()->create([
        'email' => 'linked-acc-filter@example.com',
        'employee_id' => $linkedEmployee->id,
    ]);
    $linkedUser->assignRole(Role::CODE_EMPLOYEE);
    EmployeeEmployment::factory()->create([
        'employee_id' => $linkedEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    $unlinkedUser = User::factory()->create([
        'email' => 'no-emp-link@example.com',
        'employee_id' => null,
    ]);
    $unlinkedUser->assignRole(Role::CODE_HR_HEAD);

    $employeeWithoutAccount = Employee::factory()->create([
        'id_number' => 'EMP-NO-ACCOUNT-01',
        'first_name' => 'No',
        'last_name' => 'Account',
    ]);
    EmployeeEmployment::factory()->create([
        'employee_id' => $employeeWithoutAccount->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'account_status' => 'has_account',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.account_status', 'has_account')
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('email')->contains('linked-acc-filter@example.com')
                && ! collect($rows)->pluck('email')->contains('no-emp-link@example.com')));

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'account_status' => 'no_account',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.account_status', 'no_account')
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('employee_number')->contains('EMP-NO-ACCOUNT-01')
                && ! collect($rows)->pluck('email')->contains('linked-acc-filter@example.com')
                && ! collect($rows)->pluck('email')->contains('no-emp-link@example.com')));
});

test('users tab supports inactive state and employment status filter', function () {
    config(['hris.branch_picker_enabled' => false]);

    $inactiveEmployeeWithAccount = Employee::factory()->create(['id_number' => 'EMP-INACTIVE-ACC']);
    EmployeeEmployment::factory()->create([
        'employee_id' => $inactiveEmployeeWithAccount->id,
        'employment_status' => EmployeeEmployment::STATUS_TERMINATED,
        'is_current' => false,
        'separation_date' => now()->subDay()->toDateString(),
    ]);
    $inactiveUser = User::factory()->create([
        'email' => 'inactive-has-account@example.com',
        'employee_id' => $inactiveEmployeeWithAccount->id,
    ]);
    $inactiveUser->assignRole(Role::CODE_EMPLOYEE);

    $inactiveEmployeeNoAccount = Employee::factory()->create(['id_number' => 'EMP-INACTIVE-NOACC']);
    EmployeeEmployment::factory()->create([
        'employee_id' => $inactiveEmployeeNoAccount->id,
        'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
        'is_current' => false,
        'separation_date' => now()->subDay()->toDateString(),
    ]);

    $activeEmployee = Employee::factory()->create(['id_number' => 'EMP-ACTIVE-ACC']);
    EmployeeEmployment::factory()->create([
        'employee_id' => $activeEmployee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);
    $activeUser = User::factory()->create([
        'email' => 'active-has-account@example.com',
        'employee_id' => $activeEmployee->id,
    ]);
    $activeUser->assignRole(Role::CODE_EMPLOYEE);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'employment_state' => 'inactive',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.employment_state', 'inactive')
            ->where('filters.unit_id', null)
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('email')->contains('inactive-has-account@example.com')
                && collect($rows)->pluck('employee_number')->contains('EMP-INACTIVE-NOACC')
                && ! collect($rows)->pluck('email')->contains('active-has-account@example.com')));

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'employment_state' => 'inactive',
            'employment_status' => EmployeeEmployment::STATUS_CONTRACT_ENDED,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.employment_state', 'inactive')
            ->where('filters.employment_status', EmployeeEmployment::STATUS_CONTRACT_ENDED)
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('employee_number')->contains('EMP-INACTIVE-NOACC')
                && ! collect($rows)->pluck('email')->contains('inactive-has-account@example.com')));
});

test('admin users toolbar unit filter narrows users tab rows', function () {
    config(['hris.branch_picker_enabled' => false]);
    config(['hris.default_organization_code' => 'T-ADMIN-U-FLT']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-U-FLT',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $unitA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'UA-FLT',
        'name' => 'Unit A Toolbar',
    ]);
    $unitB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'UB-FLT',
        'name' => 'Unit B Toolbar',
    ]);

    $employmentA = EmployeeEmployment::factory()->create();
    $empA = $employmentA->employee;
    EmployeeAssignment::factory()->create([
        'employee_id' => $empA->id,
        'employee_employment_id' => $employmentA->id,
        'organizational_unit_id' => $unitA->id,
        'organization_id' => null,
        'end_date' => null,
        'deleted_at' => null,
    ]);
    $userA = User::factory()->create([
        'email' => 'flt-a-toolbar@example.com',
        'employee_id' => $empA->id,
    ]);
    $userA->assignRole(Role::CODE_EMPLOYEE);

    $employmentB = EmployeeEmployment::factory()->create();
    $empB = $employmentB->employee;
    EmployeeAssignment::factory()->create([
        'employee_id' => $empB->id,
        'employee_employment_id' => $employmentB->id,
        'organizational_unit_id' => $unitB->id,
        'organization_id' => null,
        'end_date' => null,
        'deleted_at' => null,
    ]);
    $userB = User::factory()->create([
        'email' => 'flt-b-toolbar@example.com',
        'employee_id' => $empB->id,
    ]);
    $userB->assignRole(Role::CODE_EMPLOYEE);

    $employmentBare = EmployeeEmployment::factory()->create();
    $empBare = $employmentBare->employee;
    $userBare = User::factory()->create([
        'email' => 'flt-none-toolbar@example.com',
        'employee_id' => $empBare->id,
    ]);
    $userBare->assignRole(Role::CODE_EMPLOYEE);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'unit_id' => $unitA->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.unit_id', (int) $unitA->id)
            ->where('filters.account_status', 'all')
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('email')->contains('flt-a-toolbar@example.com')
                && ! collect($rows)->pluck('email')->contains('flt-b-toolbar@example.com')));

    $this->actingAs($viewer)
        ->get(route('admin.users', [
            'view' => 'users',
            'unit_id' => 'unassigned',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.unit_id', 'unassigned')
            ->where('filters.account_status', 'all')
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('email')->contains('flt-none-toolbar@example.com')
                && ! collect($rows)->pluck('email')->contains('flt-a-toolbar@example.com')));
});

test('employee role cannot access administration pages', function () {
    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('admin.audit-logs'))
        ->assertForbidden();
});

test('administration pages are restricted to top admins and branch-assigned hr managers', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();
    /** @var User $hrHead */
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.audit-logs'))
        ->assertSuccessful();

    $this->actingAs($hrHead)
        ->get(route('admin.audit-logs'))
        ->assertSuccessful();

    $this->actingAs($viewer)
        ->get(route('admin.audit-logs'))
        ->assertRedirect(route('dashboard'));
});

test('non-super-admin admin users payloads hide super administrator role and holders', function () {
    $backupUser = User::factory()->create(['email' => 'backup-super@example.com']);
    $backupUser->assignRole(Role::CODE_SUPER_ADMIN);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('viewerIsSuperAdmin', false)
            ->where('roleFilterOptions', fn ($options): bool => ! collect($options)->contains(
                fn (array $role): bool => $role['code'] === Role::CODE_SUPER_ADMIN
            ))
            ->where('roles.data', fn ($rows): bool => ! collect($rows)->contains(
                fn (array $role): bool => $role['code'] === Role::CODE_SUPER_ADMIN
            ))
            ->where('users.data', fn ($rows): bool => ! collect($rows)->pluck('email')->contains('backup-super@example.com'))
        );
});

test('picker user sees only current branch and org-wide users in roles tab and unit filter applies', function () {
    config(['hris.branch_picker_enabled' => true]);
    config(['hris.default_organization_code' => 'T-ADMIN-USR-BR-ROLE']);

    $organization = Organization::factory()->create([
        'code' => 'T-ADMIN-USR-BR-ROLE',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $rootA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'BRA-ROLE',
        'name' => 'Branch A Role',
    ]);
    $rootB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'BRB-ROLE',
        'name' => 'Branch B Role',
    ]);
    $unitA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => $rootA->id,
        'code' => 'UNIT-A-ROLE',
        'name' => 'Unit A Role',
    ]);

    $employeeRole = Role::query()->where('code', Role::CODE_EMPLOYEE)->firstOrFail();

    $branchEmployee = Employee::factory()->create();
    $branchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($branchEmployee)->create([
        'employee_employment_id' => $branchEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $rootA->id,
        'end_date' => null,
    ]);
    EmployeeAssignment::factory()->create([
        'employee_id' => $branchEmployee->id,
        'employee_employment_id' => $branchEmployment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unitA->id,
        'end_date' => null,
    ]);
    $branchUser = User::factory()->create([
        'name' => 'Branch Role User',
        'email' => 'branch-role-user@example.com',
        'avatar_path' => 'avatars/branch-role-user.png',
        'employee_id' => $branchEmployee->id,
    ]);
    $branchUser->roles()->attach($employeeRole->id);

    $orgWideEmployee = Employee::factory()->create();
    $orgWideEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $orgWideEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($orgWideEmployee)->create([
        'employee_employment_id' => $orgWideEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'end_date' => null,
    ]);
    $orgWideUser = User::factory()->create([
        'name' => 'Orgwide Role User',
        'email' => 'orgwide-role-user@example.com',
        'employee_id' => $orgWideEmployee->id,
    ]);
    $orgWideUser->roles()->attach($employeeRole->id);

    $otherBranchEmployee = Employee::factory()->create();
    $otherBranchEmployment = EmployeeEmployment::factory()->create([
        'employee_id' => $otherBranchEmployee->id,
        'is_current' => true,
    ]);
    EmployeeAffiliation::factory()->for($otherBranchEmployee)->create([
        'employee_employment_id' => $otherBranchEmployment->id,
        'organization_id' => $organization->id,
        'root_unit_id' => $rootB->id,
        'end_date' => null,
    ]);
    $otherBranchUser = User::factory()->create([
        'name' => 'Other Branch Role User',
        'email' => 'other-branch-role-user@example.com',
        'employee_id' => $otherBranchEmployee->id,
    ]);
    $otherBranchUser->roles()->attach($employeeRole->id);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($viewer)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('admin.users', [
            'view' => 'roles',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'roles')
            ->where('roles.data', fn ($rows): bool => collect($rows)->contains(
                fn (array $role): bool => $role['code'] === Role::CODE_EMPLOYEE
                    && $role['users_count'] === 2
                    && collect($role['users'])->contains(
                        fn (array $roleUser): bool => $roleUser['email'] === 'branch-role-user@example.com'
                            && is_string($roleUser['avatar_url'])
                            && str_contains($roleUser['avatar_url'], '/storage/avatars/branch-role-user.png')
                    )
            )));

    $this->actingAs($viewer)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $rootA->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => (string) $rootA->code,
                'name' => (string) $rootA->name,
            ],
        ])
        ->get(route('admin.users', [
            'view' => 'roles',
            'unit_id' => $unitA->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('filters.view', 'roles')
            ->where('filters.unit_id', (int) $unitA->id)
            ->where('roles.data', fn ($rows): bool => collect($rows)->contains(
                fn (array $role): bool => $role['code'] === Role::CODE_EMPLOYEE
                    && $role['users_count'] === 1
            )));
});

test('super administrator sees super administrator role and holders on admin users index', function () {
    $backupUser = User::factory()->create(['email' => 'backup-super-visible@example.com']);
    $backupUser->assignRole(Role::CODE_SUPER_ADMIN);

    /** @var User $viewer */
    $viewer = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($viewer)
        ->get(route('admin.users'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users')
            ->where('viewerIsSuperAdmin', true)
            ->where('roleFilterOptions', fn ($options): bool => collect($options)->contains(
                fn (array $role): bool => $role['code'] === Role::CODE_SUPER_ADMIN
            ))
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('email')->contains('backup-super-visible@example.com'))
        );
});
