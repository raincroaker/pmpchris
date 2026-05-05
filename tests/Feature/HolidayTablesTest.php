<?php

use App\Models\HolidayType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('holiday_types and organization_holidays tables exist', function (): void {
    expect(Schema::hasTable('holiday_types'))->toBeTrue();
    expect(Schema::hasTable('organization_holidays'))->toBeTrue();
});

test('organization holiday persists with holiday type', function (): void {
    $organization = Organization::factory()->create();

    $type = HolidayType::query()->create([
        'organization_id' => $organization->id,
        'slug' => 'builtin-regular',
        'name' => 'Regular Holiday',
        'kind' => 'builtin',
        'color_key' => 'lime',
        'pay_policy' => 'Double Pay',
        'custom_multiplier' => null,
        'premium_note' => null,
    ]);

    $holiday = OrganizationHoliday::query()->create([
        'organization_id' => $organization->id,
        'holiday_type_id' => $type->id,
        'name' => 'New Year',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-01',
        'notes' => null,
        'recurrence' => null,
    ]);

    expect($holiday->fresh()->holidayType->id)->toBe($type->id);
    expect($holiday->fresh()->start_date->format('Y-m-d'))->toBe('2026-01-01');
});
