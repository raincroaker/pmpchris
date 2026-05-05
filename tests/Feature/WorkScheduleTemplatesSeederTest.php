<?php

use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('work schedule templates seeder persists weekday and mon saturday split overtime templates', function (): void {
    $this->seed([
        OrganizationalStructureSeeder::class,
        RoleSeeder::class,
        DemoCooperativeSeeder::class,
        DevelopmentUserSeeder::class,
        WorkScheduleTemplatesSeeder::class,
    ]);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();

    expect(
        WorkScheduleTemplate::query()
            ->where('organization_id', $organization->id)
            ->count(),
    )->toBe(2);

    $weekday = WorkScheduleTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('name', WorkScheduleTemplatesSeeder::TEMPLATE_NAME_WEEKDAY_SPLIT_OT)
        ->firstOrFail();

    expect($weekday->is_overnight)->toBeFalse()
        ->and($weekday->segments)->toHaveCount(3)
        ->and($weekday->days)->toBe(['mon', 'tue', 'wed', 'thu', 'fri']);

    $sat = WorkScheduleTemplate::query()
        ->where('organization_id', $organization->id)
        ->where('name', WorkScheduleTemplatesSeeder::TEMPLATE_NAME_MON_SAT_SPLIT_OT)
        ->firstOrFail();

    expect($sat->segments)->toHaveCount(3)
        ->and($sat->days)->toContain('sat')
        ->and($sat->unpaid_break_minutes)->toBe(60);
});
