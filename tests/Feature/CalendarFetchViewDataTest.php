<?php

use App\Models\BranchCalendarEvent;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\TeamCalendarEvent;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('company calendar fetches categories and events from database', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Operations',
        'slug' => 'operations',
        'color_key' => 'blue',
        'is_active' => true,
    ]);

    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Company Planning',
        'starts_at' => '2026-05-10 09:00:00',
        'ends_at' => '2026-05-10 10:00:00',
        'is_all_day' => false,
        'location' => 'HQ',
        'details' => 'Planning event',
        'recurrence' => null,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'June Event',
        'starts_at' => '2026-06-10 09:00:00',
        'ends_at' => '2026-06-10 10:00:00',
        'is_all_day' => false,
        'location' => 'HQ',
        'details' => 'June event',
        'recurrence' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('calendar.company', ['month' => '2026-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Company')
            ->has('calendarCategories', 1)
            ->where('calendarCategories.0.name', 'Operations')
            ->has('companyEvents', 1)
            ->where('companyEvents.0.title', 'Company Planning')
            ->where('companyEvents.0.categoryId', $category->id)
        );
});

test('branch calendar fetches scoped branch events from database', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $otherRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'PAN',
        'name' => 'Panabo Branch',
        'is_active' => true,
    ]);

    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Branch Ops',
        'slug' => 'branch-ops',
        'color_key' => 'teal',
        'is_active' => true,
    ]);

    BranchCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $selectedRoot->id,
        'category_id' => $category->id,
        'title' => 'Selected Branch Event',
        'starts_at' => '2026-05-11 09:00:00',
        'ends_at' => '2026-05-11 10:00:00',
        'is_all_day' => false,
    ]);
    BranchCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $selectedRoot->id,
        'category_id' => $category->id,
        'title' => 'Selected Branch June Event',
        'starts_at' => '2026-06-11 09:00:00',
        'ends_at' => '2026-06-11 10:00:00',
        'is_all_day' => false,
    ]);
    BranchCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $otherRoot->id,
        'category_id' => $category->id,
        'title' => 'Other Branch Event',
        'starts_at' => '2026-05-12 09:00:00',
        'ends_at' => '2026-05-12 10:00:00',
        'is_all_day' => false,
    ]);

    Role::query()->create(['code' => Role::CODE_HR_HEAD, 'name' => 'HR Head']);
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_HR_HEAD);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.branch', ['month' => '2026-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Branch')
            ->has('branchEvents', 1)
            ->where('branchEvents.0.title', 'Selected Branch Event')
        );
});

test('team calendar fetches scoped unit events from database', function () {
    $organizationCode = (string) config('hris.default_organization_code', 'PMPC');
    $organization = Organization::factory()->create([
        'code' => $organizationCode,
        'is_active' => true,
    ]);

    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $teamType = UnitType::factory()->create([
        'name' => 'Team',
        'can_be_root' => false,
        'is_active' => true,
    ]);
    $selectedRoot = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'code' => 'TAG',
        'name' => 'Tagum Branch',
        'is_active' => true,
    ]);
    $teamA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $selectedRoot->id,
        'code' => 'TAG-A',
        'name' => 'Team A',
        'is_active' => true,
    ]);

    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Team',
        'slug' => 'team',
        'color_key' => 'indigo',
        'is_active' => true,
    ]);

    TeamCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $selectedRoot->id,
        'unit_id' => $teamA->id,
        'category_id' => $category->id,
        'title' => 'Team A Event',
        'starts_at' => '2026-05-13 09:00:00',
        'ends_at' => '2026-05-13 10:00:00',
        'is_all_day' => false,
    ]);
    TeamCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => null,
        'unit_id' => null,
        'category_id' => $category->id,
        'title' => 'Org Scope Event',
        'starts_at' => '2026-05-14 09:00:00',
        'ends_at' => '2026-05-14 10:00:00',
        'is_all_day' => false,
    ]);

    Role::query()->create(['code' => Role::CODE_SUPER_ADMIN, 'name' => 'Super Administrator']);
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole(Role::CODE_SUPER_ADMIN);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team', ['month' => '2026-05']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('calendarCategories', 1)
            ->has('teamEvents', 1)
            ->where('teamEvents.0.title', 'Org Scope Event')
            ->where('teamEvents.0.unitId', null)
        );

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $selectedRoot->id,
            BranchContextService::SESSION_BRANCH_META => [
                'code' => $selectedRoot->code,
                'name' => $selectedRoot->name,
            ],
        ])
        ->get(route('calendar.team', ['month' => '2026-05', 'unit_id' => $teamA->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Team')
            ->has('teamEvents', 1)
            ->where('teamEvents.0.title', 'Team A Event')
            ->where('teamEvents.0.unitId', $teamA->id)
        );
});
