<?php

use App\Models\Area;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo cooperative seeder creates one area and two roots with PAN/TAG units', function () {
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    expect(Area::query()->count())->toBe(1)
        ->and(OrganizationalUnit::query()->whereNull('parent_id')->count())->toBe(2)
        ->and(Position::query()->count())->toBe(2);

    foreach (['PAN', 'TAG'] as $prefix) {
        expect(
            OrganizationalUnit::query()->where('code', 'like', $prefix.'-%')->exists()
        )->toBeTrue();
    }

    foreach (['TIB', 'BAJ'] as $prefix) {
        expect(
            OrganizationalUnit::query()->where('code', 'like', $prefix.'-%')->exists()
        )->toBeFalse();
    }
});

test('demo branch picker lists panabo first among davao del norte branches', function () {
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();

    $branches = app(BranchContextService::class)->branchesForPicker();

    expect($branches)->toHaveCount(2)
        ->and($branches->first()['code'])->toBe('PAN')
        ->and($branches->first()['area_name'])->toBe('Davao del Norte')
        ->and($branches->first()['group_label'])->toBe('Davao del Norte')
        ->and($branches->slice(0, 2)->pluck('area_name')->unique()->values()->all())->toBe(['Davao del Norte']);
});
