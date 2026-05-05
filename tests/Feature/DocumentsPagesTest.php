<?php

use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('documents drive inertia pages render for authenticated user', function (string $routeName, string $component): void {
    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['documents.my', 'Documents/My'],
    ['documents.team', 'Documents/Team'],
    ['documents.branch', 'Documents/Branch'],
    ['documents.company', 'Documents/Company'],
    ['documents.trash', 'Documents/Trash'],
]);

test('documents team page shares assignment unit ids for filtering', function (): void {
    (new RoleSeeder)->run();

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('documents.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents')
            ->where('documents.teamAssignmentUnitIds', [])
            ->where('documents.teamHeadUnitIds', []));
});

test('documents admin view flags are false for employee role', function (): void {
    (new RoleSeeder)->run();

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->get(route('documents.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.canViewDocumentAdminViewTeam', false)
            ->where('can.canViewDocumentAdminViewBranch', false)
            ->where('can.canViewDocumentAdminViewCompany', false));
});

test('documents admin view flags are true for hr head with workspace branch', function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);

    $branchRoot = OrganizationalUnit::query()->where('code', 'PAN')->whereNull('parent_id')->firstOrFail();

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $branchRoot->id])
        ->get(route('documents.team'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.canViewDocumentAdminViewTeam', true)
            ->where('can.canViewDocumentAdminViewBranch', true)
            ->where('can.canViewDocumentAdminViewCompany', true));
});
