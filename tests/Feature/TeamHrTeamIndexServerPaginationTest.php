<?php

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function teamHrIndexSeedOrgWithSelectableBranchRoot(): OrganizationalUnit
{
    (new OrganizationalStructureSeeder)->run();

    $org = Organization::factory()->create([
        'code' => 'T-HR-INDEX-PG',
        'name' => 'Test Cooperative HR Index Pagination',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-HR-INDEX-PG']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    return OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-IDX',
        'name' => 'HR Test Branch Index',
        'is_active' => true,
    ]);
}

test('leave team index returns paginator props and echoes query filters', function (): void {
    (new RoleSeeder)->run();
    $branch = teamHrIndexSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)
        ->get(route('leave.team', [
            'page' => 2,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'sort' => 'status',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leave/Team')
            ->where('teamEmployeeLeaves.current_page', 2)
            ->where('teamEmployeeLeaves.per_page', 10)
            ->has('teamEmployeeLeaves.data')
            ->where('leaveTeamFilters.page', 2)
            ->where('leaveTeamFilters.date_from', '2026-05-01')
            ->where('leaveTeamFilters.date_to', '2026-05-31')
            ->where('leaveTeamFilters.sort', 'status')
            ->where('leaveTeamFilters.direction', 'asc')
            ->where('leaveTeamFilters.employee_id_number', null)
            ->where('leaveTeamFilters.leave_type', null)
            ->has('leaveEmployeeFilterOptions'));
});

test('overtime team index returns paginator props and echoes query filters', function (): void {
    (new RoleSeeder)->run();
    $branch = teamHrIndexSeedOrgWithSelectableBranchRoot();

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ]);

    $this->actingAs($user)
        ->get(route('overtime.team', [
            'page' => 2,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'sort' => 'status',
            'direction' => 'asc',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overtime/Team')
            ->where('teamEmployeeOvertimes.current_page', 2)
            ->where('teamEmployeeOvertimes.per_page', 10)
            ->has('teamEmployeeOvertimes.data')
            ->where('overtimeTeamFilters.page', 2)
            ->where('overtimeTeamFilters.date_from', '2026-05-01')
            ->where('overtimeTeamFilters.date_to', '2026-05-31')
            ->where('overtimeTeamFilters.sort', 'status')
            ->where('overtimeTeamFilters.direction', 'asc')
            ->where('overtimeTeamFilters.policy_code', null));
});
