<?php

use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use Database\Seeders\LeaveAndOvertimePoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('leave and overtime policies seeder upserts demo rows for PMPC', function (): void {
    $organization = Organization::factory()->create([
        'code' => 'PMPC',
        'is_active' => true,
    ]);

    (new LeaveAndOvertimePoliciesSeeder)->run();

    expect(LeavePolicy::query()->where('organization_id', $organization->id)->count())->toBe(5)
        ->and(OvertimePolicy::query()->where('organization_id', $organization->id)->count())->toBe(5)
        ->and(LeavePolicy::query()->where('organization_id', $organization->id)->where('code', 'VL')->value('name'))
        ->toBe('Vacation leave')
        ->and(OvertimePolicy::query()->where('organization_id', $organization->id)->where('code', 'OT-WD')->value('name'))
        ->toBe('Weekday overtime');
});

test('leave and overtime policies seeder is a no-op when PMPC organization is missing', function (): void {
    Organization::factory()->create([
        'code' => 'OTHER',
        'is_active' => true,
    ]);

    (new LeaveAndOvertimePoliciesSeeder)->run();

    expect(LeavePolicy::query()->count())->toBe(0)
        ->and(OvertimePolicy::query()->count())->toBe(0);
});
