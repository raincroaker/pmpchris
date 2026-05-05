<?php

use App\Models\User;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\HolidayTypesSeeder;
use Database\Seeders\OrganizationHolidaysSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar organization holidays index returns filtered rows', function (): void {
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new DevelopmentUserSeeder)->run();
    (new HolidayTypesSeeder)->run();
    (new OrganizationHolidaysSeeder)->run();

    $user = User::query()->where('email', 'superadmin@example.com')->firstOrFail();

    $response = $this->actingAs($user)
        ->getJson(route('calendar.organization-holidays.index', [
            'month' => '2026-04',
            'range' => 'custom',
            'custom_from' => '2026-04-01',
            'custom_to' => '2026-04-10',
            'search' => 'Black Saturday',
            'type_ids' => 'builtin-special-non-working',
            'per_page' => 50,
        ]));

    $response->assertOk();
    $response->assertJsonPath('meta.hasMore', false);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Black Saturday');
    $response->assertJsonPath('data.0.type_id', 'builtin-special-non-working');
});
