<?php

use App\Models\Organization;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('guests are redirected to the login page from positions index', function () {
    $response = $this->get(route('positions'));

    $response->assertRedirect(route('login'));
});

test('employee role cannot visit the positions index page', function () {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();
    $this->actingAs($user)
        ->get(route('positions'))
        ->assertForbidden();
});

test('authenticated privileged users can visit the positions index page', function () {
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user);

    $response = $this->get(route('positions'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Positions/Index')
            ->has('positions')
            ->has('positions.data')
            ->has('positions.current_page')
            ->has('filters')
            ->where('filters.status', 'active')
            ->where('filters.sort', 'code')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 10));
});

test('positions index defaults to active only for default organization', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-IDX',
        'name' => 'Pos Index Org',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-IDX']);

    $active = Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ACT-01',
        'title' => 'Active Role',
        'is_active' => true,
    ]);

    $inactive = Position::factory()->inactive()->create([
        'organization_id' => $organization->id,
        'code' => 'INA-01',
        'title' => 'Inactive Role',
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Positions/Index')
            ->has('positions.data', 1)
            ->where('positions.data.0.id', $active->id)
            ->missing('positions.data.1')
            ->where('filters.status', 'active'));

    expect(Position::query()->whereKey($inactive->id)->exists())->toBeTrue();
});

test('positions index status all includes inactive rows', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-ALL',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-ALL']);

    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'A1',
        'is_active' => true,
    ]);
    Position::factory()->inactive()->create([
        'organization_id' => $organization->id,
        'code' => 'I1',
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions', ['status' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('positions.data', 2)
            ->where('filters.status', 'all'));
});

test('positions index status inactive excludes active rows', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-IN',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-IN']);

    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ONLY-ACT',
        'is_active' => true,
    ]);
    $inactive = Position::factory()->inactive()->create([
        'organization_id' => $organization->id,
        'code' => 'ONLY-INA',
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions', ['status' => 'inactive']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('positions.data', 1)
            ->where('positions.data.0.id', $inactive->id));
});

test('positions index search matches code title or description', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-SR',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-SR']);

    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ZZSRCH99',
        'title' => 'Other',
        'description' => null,
        'is_active' => true,
    ]);
    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'OTHER',
        'title' => 'No match',
        'is_active' => true,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions', [
            'status' => 'all',
            'search' => 'ZZSRCH99',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('positions.data', 1)
            ->where('positions.data.0.code', 'ZZSRCH99')
            ->where('filters.search', 'ZZSRCH99'));
});

test('positions index sorts by code ascending when requested', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-SORT',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-SORT']);

    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ZEBRA',
        'is_active' => true,
    ]);
    Position::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'ALPHA',
        'is_active' => true,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions', [
            'status' => 'all',
            'sort' => 'code',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('positions.data.0.code', 'ALPHA')
            ->where('positions.data.1.code', 'ZEBRA'));
});

test('positions index coerces oversized per page to fifty', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-PP',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-PP']);

    Position::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'is_active' => true,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions', [
            'status' => 'all',
            'per_page' => 999,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.per_page', 50)
            ->where('positions.per_page', 50));
});

test('positions index validation rejects invalid status', function () {
    $organization = Organization::factory()->create([
        'code' => 'T-POS-422',
        'is_active' => true,
    ]);
    config(['hris.default_organization_code' => 'T-POS-422']);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $response = $this->actingAs($user)
        ->from(route('dashboard'))
        ->get(route('positions', ['status' => 'nope']));

    $response->assertInvalid(['status']);
});

test('positions index returns empty paginator when no default organization', function () {
    config(['hris.default_organization_code' => '']);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    $this->actingAs($user)
        ->get(route('positions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Positions/Index')
            ->where('organization', null)
            ->has('positions.data', 0)
            ->where('positions.total', 0));
});

test('employee users do not receive administration nav visibility shared prop', function () {
    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('showHrAdminNav', false));
});

test('non-employee users receive administration nav visibility shared prop', function () {
    $manager = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('showHrAdminNav', true));
});
