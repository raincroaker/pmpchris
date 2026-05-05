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

test('hr manager assigned to selected branch can create branch calendar event', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-BR-CREATE']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-BR-CREATE',
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
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
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
        ->postJson(route('calendar.branch-events.store'), [
            'title' => 'Branch Event',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 10:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'Main Hall',
            'details' => 'Event details',
            'recurrence' => null,
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);

    expect(BranchCalendarEvent::query()
        ->where('organization_id', $organization->id)
        ->where('root_unit_id', $root->id)
        ->where('title', 'Branch Event')
        ->where('set_by_user_id', $user->id)
        ->exists())->toBeTrue();
});

test('hr manager cannot update company calendar event', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-UPD-DENY']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-UPD-DENY',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Company Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->patchJson(route('calendar.company-events.update', $event), [
            'title' => 'Updated',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 10:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => '',
            'details' => '',
            'recurrence' => null,
        ])
        ->assertForbidden();
});

test('super admin can update company calendar event', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-UPD-OK']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-UPD-OK',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Company Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->patchJson(route('calendar.company-events.update', $event), [
            'title' => 'Updated Company Event',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 11:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'HQ',
            'details' => 'Updated details',
            'recurrence' => null,
        ])
        ->assertOk();

    expect(CompanyCalendarEvent::query()->whereKey($event->id)->value('title'))->toBe('Updated Company Event');
});

test('team calendar org-scope create is forbidden for hr manager', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-TEAM-ORG-DENY']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-TEAM-ORG-DENY',
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
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
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
        ->postJson(route('calendar.team-events.store'), [
            'title' => 'Org Scope Team Event',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 10:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'Main Hall',
            'details' => 'Event details',
            'recurrence' => null,
            'unit_id' => null,
        ])
        ->assertForbidden();

    expect(TeamCalendarEvent::query()->get()->count())->toBe(0);
});

test('invalid recurrence payload is rejected on create', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-REC-VALID']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-REC-VALID',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.company-events.store'), [
            'title' => 'Company Event',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 10:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'HQ',
            'details' => 'Details',
            'recurrence' => [
                'frequency' => 'weekly',
                'interval' => 0,
                'byWeekday' => [],
                'ends' => ['type' => 'count', 'count' => 0],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'recurrence.interval',
            'recurrence.byWeekday',
            'recurrence.ends.count',
        ]);
});

test('single occurrence delete adds recurrence exception instead of deleting series', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-SINGLE-DEL']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-SINGLE-DEL',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Recurring Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [3],
            'ends' => ['type' => 'never'],
        ],
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->deleteJson(route('calendar.company-events.destroy', $event), [
            'apply_to' => 'single_occurrence',
            'occurrence_date' => '2026-05-27',
        ])
        ->assertOk();

    $event->refresh();
    expect($event->deleted_at)->toBeNull();
    expect($event->recurrence_exceptions)->toBeArray();
});

test('single occurrence edit is rejected on update', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-SINGLE-EDIT-REJECT']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-SINGLE-EDIT-REJECT',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Recurring Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [3],
            'ends' => ['type' => 'never'],
        ],
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->patchJson(route('calendar.company-events.update', $event), [
            'title' => 'Occurrence Override',
            'starts_at' => '2026-05-27 09:15',
            'ends_at' => '2026-05-27 10:15',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'Room A',
            'details' => 'Edited only this occurrence',
            'recurrence' => $event->recurrence,
            'apply_to' => 'single_occurrence',
            'occurrence_date' => '2026-05-27',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['apply_to']);
});

test('entire series edit can restore skipped occurrences', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-RESTORE-SKIP']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-RESTORE-SKIP',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Recurring Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [3],
            'ends' => ['type' => 'never'],
        ],
        'recurrence_exceptions' => [
            ['date' => '2026-05-27', 'action' => 'skip'],
            [
                'date' => '2026-06-03',
                'action' => 'override',
                'title' => 'Override title',
            ],
        ],
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->patchJson(route('calendar.company-events.update', $event), [
            'title' => 'Recurring Event Updated',
            'starts_at' => '2026-05-20 09:00',
            'ends_at' => '2026-05-20 10:00',
            'is_all_day' => false,
            'category_id' => $category->id,
            'location' => 'HQ',
            'details' => 'Updated details',
            'recurrence' => $event->recurrence,
            'apply_to' => 'entire_series',
            'restore_occurrence_dates' => ['2026-05-27'],
        ])
        ->assertOk();

    $event->refresh();
    expect($event->recurrence_exceptions)->toBeArray();
    $exceptions = collect($event->recurrence_exceptions);
    expect(
        $exceptions->contains(
            fn (array $row): bool => ($row['action'] ?? null) === 'skip'
                && ($row['date'] ?? null) === '2026-05-27',
        ),
    )->toBeFalse();
    expect(
        $exceptions->contains(
            fn (array $row): bool => ($row['action'] ?? null) === 'override'
                && ($row['date'] ?? null) === '2026-06-03',
        ),
    )->toBeTrue();
});

test('single occurrence action rejects date outside recurrence pattern', function (): void {
    config(['hris.default_organization_code' => 'T-EVT-SINGLE-BAD-DATE']);
    $organization = Organization::factory()->create([
        'code' => 'T-EVT-SINGLE-BAD-DATE',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Meeting',
        'slug' => 'meeting',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $event = CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Recurring Event',
        'starts_at' => '2026-05-20 09:00:00',
        'ends_at' => '2026-05-20 10:00:00',
        'is_all_day' => false,
        'recurrence' => [
            'frequency' => 'weekly',
            'interval' => 1,
            'byWeekday' => [3],
            'ends' => ['type' => 'never'],
        ],
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->deleteJson(route('calendar.company-events.destroy', $event), [
            'apply_to' => 'single_occurrence',
            'occurrence_date' => '2026-05-28',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occurrence_date']);
});
