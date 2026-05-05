<?php

use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\UnitType;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('organizational unit parent child and position branch relations resolve', function () {
    $organization = Organization::factory()->create();

    $rootType = UnitType::factory()->create([
        'name' => 'RootType-'.uniqid('', true),
        'can_be_root' => true,
    ]);

    $childType = UnitType::factory()->create([
        'name' => 'ChildType-'.uniqid('', true),
        'can_be_root' => false,
    ]);

    $root = OrganizationalUnit::factory()
        ->for($organization)
        ->create([
            'unit_type_id' => $rootType->id,
            'parent_id' => null,
            'code' => 'ROOT-1',
        ]);

    $child = OrganizationalUnit::factory()
        ->for($organization)
        ->create([
            'unit_type_id' => $childType->id,
            'parent_id' => $root->id,
            'code' => 'CHILD-1',
        ]);

    expect($child->parent_id)->toBe($root->id);
    expect($root->children()->count())->toBe(1);
    expect($root->children->first()->is($child))->toBeTrue();

    $position = Position::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $position->load('organization');

    expect($position->organization->is($organization))->toBeTrue();
});
