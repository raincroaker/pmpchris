<?php

use App\Models\BranchCalendarEvent;
use App\Models\BranchManager;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\TeamCalendarEvent;
use App\Models\UnitType;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('company events index returns paginated month events', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-COMP']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-COMP',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 5; $i += 1) {
        CompanyCalendarEvent::query()->create([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'title' => 'Event '.$i,
            'starts_at' => sprintf('2026-05-%02d 09:00:00', 10 + $i),
            'ends_at' => sprintf('2026-05-%02d 10:00:00', 10 + $i),
            'is_all_day' => false,
        ]);
    }

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson(route('calendar.company-events.index', [
            'month' => '2026-05',
            'page' => 1,
            'per_page' => 2,
            'range' => 'month',
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.hasMore', true)
        ->assertJsonPath('meta.nextPage', 2);
});

test('team events index respects selected unit id', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-TEAM']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-TEAM',
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
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
    $teamA = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $root->id,
        'is_active' => true,
    ]);
    $teamB = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $root->id,
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);

    TeamCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'unit_id' => $teamA->id,
        'category_id' => $category->id,
        'title' => 'Team A Event',
        'starts_at' => '2026-05-12 09:00:00',
        'ends_at' => '2026-05-12 10:00:00',
        'is_all_day' => false,
    ]);
    TeamCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'unit_id' => $teamB->id,
        'category_id' => $category->id,
        'title' => 'Team B Event',
        'starts_at' => '2026-05-13 09:00:00',
        'ends_at' => '2026-05-13 10:00:00',
        'is_all_day' => false,
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => ['code' => $root->code, 'name' => $root->name],
        ])
        ->getJson(route('calendar.team-events.index', [
            'month' => '2026-05',
            'unit_id' => $teamA->id,
            'range' => 'month',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Team A Event');
});

test('company events index applies search and category filters', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-FILTER']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-FILTER',
        'is_active' => true,
    ]);
    $meetingCategory = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $opsCategory = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Operations',
        'slug' => 'operations',
        'color_key' => 'teal',
        'is_active' => true,
    ]);

    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $meetingCategory->id,
        'title' => 'Leadership meeting',
        'starts_at' => '2026-05-15 09:00:00',
        'ends_at' => '2026-05-15 10:00:00',
        'is_all_day' => false,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $opsCategory->id,
        'title' => 'Operations sync',
        'starts_at' => '2026-05-16 09:00:00',
        'ends_at' => '2026-05-16 10:00:00',
        'is_all_day' => false,
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson(route('calendar.company-events.index', [
            'month' => '2026-05',
            'search' => 'leadership',
            'category_ids' => $meetingCategory->id,
            'range' => 'month',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Leadership meeting');
});

test('company events index includes recurring occurrences in list results', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-RECUR']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-RECUR',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Training',
        'slug' => 'training',
        'color_key' => 'emerald',
        'is_active' => true,
    ]);

    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Weekly training',
        'starts_at' => '2026-05-04 09:00:00',
        'ends_at' => '2026-05-04 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [1], // Monday
            'ends' => [
                'type' => 'count',
                'count' => 4,
            ],
        ],
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson(route('calendar.company-events.index', [
            'month' => '2026-05',
            'range' => 'month',
            'per_page' => 20,
        ]))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.title', 'Weekly training')
        ->assertJsonPath('data.0.seriesId', 'company-1');
});

test('branch events index includes recurring occurrences in list results', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-BR-RECUR']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-BR-RECUR',
        'is_active' => true,
    ]);
    $rootType = UnitType::factory()->create([
        'name' => 'Branch',
        'can_be_root' => true,
        'is_active' => true,
    ]);
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Operations',
        'slug' => 'operations',
        'color_key' => 'emerald',
        'is_active' => true,
    ]);
    BranchCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'category_id' => $category->id,
        'title' => 'Branch recurring huddle',
        'starts_at' => '2026-05-06 08:30:00',
        'ends_at' => '2026-05-06 09:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [3], // Wednesday
            'ends' => [
                'type' => 'count',
                'count' => 4,
            ],
        ],
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => ['code' => $root->code, 'name' => $root->name],
        ])
        ->getJson(route('calendar.branch-events.index', [
            'month' => '2026-05',
            'range' => 'month',
            'per_page' => 20,
        ]))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.title', 'Branch recurring huddle')
        ->assertJsonPath('data.0.seriesId', 'branch-1');
});

test('team events index includes recurring occurrences in list results', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-TM-RECUR']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-TM-RECUR',
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
    $root = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $rootType->id,
        'parent_id' => null,
        'is_active' => true,
    ]);
    $unit = OrganizationalUnit::factory()->create([
        'organization_id' => $organization->id,
        'unit_type_id' => $teamType->id,
        'parent_id' => $root->id,
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Training',
        'slug' => 'training',
        'color_key' => 'fuchsia',
        'is_active' => true,
    ]);

    TeamCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'root_unit_id' => $root->id,
        'unit_id' => $unit->id,
        'category_id' => $category->id,
        'title' => 'Team recurring coaching',
        'starts_at' => '2026-05-05 15:00:00',
        'ends_at' => '2026-05-05 16:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [2], // Tuesday
            'ends' => [
                'type' => 'count',
                'count' => 4,
            ],
        ],
    ]);

    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();
    BranchManager::query()->create([
        'user_id' => $user->id,
        'root_unit_id' => $root->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            BranchContextService::SESSION_BRANCH_ID => $root->id,
            BranchContextService::SESSION_BRANCH_META => ['code' => $root->code, 'name' => $root->name],
        ])
        ->getJson(route('calendar.team-events.index', [
            'month' => '2026-05',
            'unit_id' => $unit->id,
            'range' => 'month',
            'per_page' => 20,
        ]))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.title', 'Team recurring coaching')
        ->assertJsonPath('data.0.seriesId', 'team-1');
});

test('company events index applies recurrence exceptions to occurrence list', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-EXCEPT']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-EXCEPT',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Training',
        'slug' => 'training',
        'color_key' => 'emerald',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Weekly training',
        'starts_at' => '2026-07-06 09:00:00',
        'ends_at' => '2026-07-06 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [1], // Monday
            'ends' => [
                'type' => 'count',
                'count' => 5,
            ],
        ],
        'recurrence_exceptions' => [
            [
                'date' => '2026-07-20',
                'action' => 'skip',
            ],
            [
                'date' => '2026-07-27',
                'action' => 'override',
                'title' => 'Weekly training (moved)',
            ],
        ],
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('calendar.company-events.index', [
        'month' => '2026-07',
        'range' => 'month',
        'per_page' => 20,
    ]));

    $response->assertOk()->assertJsonCount(3, 'data');
    $rows = $response->json('data');
    expect($rows)->toBeArray();
    expect(array_column($rows, 'id'))->not->toContain('company-'.$event->id.'::2026-07-20');
    expect(array_column($rows, 'title'))->toContain('Weekly training (moved)');
});

test('company events index paginates recurring occurrences across pages', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-PG-RECUR']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-PG-RECUR',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Training',
        'slug' => 'training',
        'color_key' => 'emerald',
        'is_active' => true,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Recurring workshop',
        'starts_at' => '2026-08-03 09:00:00',
        'ends_at' => '2026-08-03 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [1], // Monday
            'ends' => [
                'type' => 'count',
                'count' => 6,
            ],
        ],
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson(route('calendar.company-events.index', [
            'month' => '2026-08',
            'range' => 'month',
            'page' => 1,
            'per_page' => 2,
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.hasMore', true)
        ->assertJsonPath('meta.nextPage', 2);

    $this->actingAs($user)
        ->getJson(route('calendar.company-events.index', [
            'month' => '2026-08',
            'range' => 'month',
            'page' => 2,
            'per_page' => 2,
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.hasMore', true)
        ->assertJsonPath('meta.nextPage', 3);
});

test('company events index month range is anchored to requested display month', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-IDX-MONTH-ANCHOR']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-IDX-MONTH-ANCHOR',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'July planning',
        'starts_at' => '2026-07-15 09:00:00',
        'ends_at' => '2026-07-15 10:00:00',
        'is_all_day' => false,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'August planning',
        'starts_at' => '2026-08-15 09:00:00',
        'ends_at' => '2026-08-15 10:00:00',
        'is_all_day' => false,
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(route('calendar.company-events.index', [
        'month' => '2026-07',
        'range' => 'month',
        'per_page' => 20,
    ]));

    $response->assertOk()->assertJsonCount(1, 'data');
    $rows = $response->json('data');
    expect($rows)->toBeArray();
    expect(array_column($rows, 'title'))->toContain('July planning');
    expect(array_column($rows, 'title'))->not->toContain('August planning');
});
