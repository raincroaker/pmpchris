<?php

use App\Models\OrganizationHoliday;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\HolidayTypesSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\OrganizationHolidaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('organization holidays seeder inserts holidays for PMPC', function (): void {
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    (new HolidayTypesSeeder)->run();
    (new OrganizationHolidaysSeeder)->run();

    $holidayNames = OrganizationHoliday::query()
        ->pluck('name')
        ->all();

    expect($holidayNames)
        ->toContain(
            "New Year's Day",
            'Maundy Thursday',
            'Good Friday',
            'Araw ng Kagitingan (Day of Valor)',
            'Labor Day',
            'Independence Day',
            'National Heroes Day',
            'Bonifacio Day',
            'Christmas Day',
            'Rizal Day',
            'Ninoy Aquino Day',
            "All Saints' Day",
            "All Souls' Day",
            'Feast of the Immaculate Conception of Mary',
            'Christmas Eve',
            'Chinese New Year',
            'Black Saturday',
            'Last Day of the Year (special non-working — when proclaimed)',
            'EDSA People Power Revolution Anniversary',
        );
});
