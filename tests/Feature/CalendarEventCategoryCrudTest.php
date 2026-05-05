<?php

use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    (new RoleSeeder)->run();
});

test('guests cannot create calendar event categories', function (): void {
    $this->postJson(route('calendar.event-categories.store'), [
        'name' => 'Operations',
        'colorKey' => 'blue',
    ])->assertUnauthorized();
});

test('employee role cannot create calendar event categories', function (): void {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.event-categories.store'), [
            'name' => 'Operations',
            'colorKey' => 'blue',
        ])
        ->assertForbidden();
});

test('hr head can create calendar event categories', function (): void {
    config(['hris.default_organization_code' => 'T-CAL-CAT-STORE']);
    $organization = Organization::factory()->create([
        'code' => 'T-CAL-CAT-STORE',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.event-categories.store'), [
            'name' => 'Operations',
            'colorKey' => 'blue',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Operations')
        ->assertJsonPath('data.colorKey', 'blue');

    expect(CalendarEventCategory::query()
        ->where('organization_id', $organization->id)
        ->where('slug', 'operations')
        ->exists())->toBeTrue();
});

test('hr head can update calendar event category', function (): void {
    config(['hris.default_organization_code' => 'T-CAL-CAT-UPD']);
    $organization = Organization::factory()->create([
        'code' => 'T-CAL-CAT-UPD',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Ops',
        'slug' => 'ops',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->patchJson(route('calendar.event-categories.update', $category), [
            'name' => 'Operations',
            'colorKey' => 'teal',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Operations')
        ->assertJsonPath('data.colorKey', 'teal');
});

test('delete category is blocked when referenced by calendar events', function (): void {
    config(['hris.default_organization_code' => 'T-CAL-CAT-DEL-REF']);
    $organization = Organization::factory()->create([
        'code' => 'T-CAL-CAT-DEL-REF',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Ops',
        'slug' => 'ops',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    CompanyCalendarEvent::query()->create([
        'organization_id' => $organization->id,
        'category_id' => $category->id,
        'title' => 'Used category event',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'is_all_day' => false,
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->deleteJson(route('calendar.event-categories.destroy', $category))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category']);

    expect(CalendarEventCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('super admin can delete unreferenced category', function (): void {
    config(['hris.default_organization_code' => 'T-CAL-CAT-DEL-OK']);
    $organization = Organization::factory()->create([
        'code' => 'T-CAL-CAT-DEL-OK',
        'is_active' => true,
    ]);
    $category = CalendarEventCategory::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Ops',
        'slug' => 'ops',
        'color_key' => 'blue',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->deleteJson(route('calendar.event-categories.destroy', $category))
        ->assertOk()
        ->assertJsonPath('data.id', $category->id);

    expect(CalendarEventCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});
