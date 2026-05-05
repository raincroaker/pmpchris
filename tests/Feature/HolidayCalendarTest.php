<?php

use App\Models\HolidayType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
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

test('holiday calendar page includes types and month-scoped holidays for default org', function (): void {
    config(['hris.default_organization_code' => 'T-HOL-PAGE']);
    $organization = Organization::factory()->create([
        'code' => 'T-HOL-PAGE',
        'is_active' => true,
    ]);
    HolidayType::query()->create([
        'organization_id' => $organization->id,
        'slug' => 'builtin-regular',
        'name' => 'Regular Holiday',
        'kind' => 'builtin',
        'color_key' => 'lime',
        'pay_policy' => 'Double Pay',
        'custom_multiplier' => null,
        'premium_note' => null,
    ]);
    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->get(route('calendar.holidays', ['month' => '2026-06']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar/Holidays')
            ->where('calendarDisplayMonth', '2026-06')
            ->has('holidayTypes', 1));
});

test('super admin can create organization holiday', function (): void {
    config(['hris.default_organization_code' => 'T-HOL-STORE']);
    $organization = Organization::factory()->create([
        'code' => 'T-HOL-STORE',
        'is_active' => true,
    ]);
    HolidayType::query()->create([
        'organization_id' => $organization->id,
        'slug' => 'builtin-regular',
        'name' => 'Regular Holiday',
        'kind' => 'builtin',
        'color_key' => 'lime',
        'pay_policy' => 'Double Pay',
        'custom_multiplier' => null,
        'premium_note' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.organization-holidays.store'), [
            'name' => 'Test Holiday',
            'start_date' => '2026-06-12',
            'end_date' => '2026-06-12',
            'type_id' => 'builtin-regular',
            'notes' => null,
            'recurrence' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Test Holiday');

    expect(OrganizationHoliday::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Test Holiday')
        ->exists())->toBeTrue();
});

test('employee cannot create organization holiday', function (): void {
    config(['hris.default_organization_code' => 'T-HOL-EMP']);
    $organization = Organization::factory()->create([
        'code' => 'T-HOL-EMP',
        'is_active' => true,
    ]);
    HolidayType::query()->create([
        'organization_id' => $organization->id,
        'slug' => 'builtin-regular',
        'name' => 'Regular Holiday',
        'kind' => 'builtin',
        'color_key' => 'lime',
        'pay_policy' => 'Double Pay',
        'custom_multiplier' => null,
        'premium_note' => null,
    ]);

    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.organization-holidays.store'), [
            'name' => 'X',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'type_id' => 'builtin-regular',
        ])
        ->assertForbidden();
});

test('hr manager cannot create organization holiday', function (): void {
    config(['hris.default_organization_code' => 'T-HOL-SYS']);
    $organization = Organization::factory()->create([
        'code' => 'T-HOL-SYS',
        'is_active' => true,
    ]);
    HolidayType::query()->create([
        'organization_id' => $organization->id,
        'slug' => 'builtin-regular',
        'name' => 'Regular Holiday',
        'kind' => 'builtin',
        'color_key' => 'lime',
        'pay_policy' => 'Double Pay',
        'custom_multiplier' => null,
        'premium_note' => null,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.organization-holidays.store'), [
            'name' => 'X',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'type_id' => 'builtin-regular',
        ])
        ->assertForbidden();
});

test('hr head can create custom holiday type', function (): void {
    config(['hris.default_organization_code' => 'T-HOL-TYPE']);
    Organization::factory()->create([
        'code' => 'T-HOL-TYPE',
        'is_active' => true,
    ]);
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->postJson(route('calendar.holiday-types.store'), [
            'name' => 'Extra Premium Day',
            'colorKey' => 'cyan',
            'pay_policy' => 'Double Pay',
            'custom_multiplier' => null,
            'premiumNote' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Extra Premium Day');

    $organization = Organization::query()->where('code', 'T-HOL-TYPE')->first();
    expect(HolidayType::query()
        ->where('organization_id', $organization->id)
        ->where('slug', 'extra-premium-day')
        ->exists())->toBeTrue();
});
