<?php

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\Position;
use App\Support\TeamHrEmployeeDirectoryExtras;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('map active positions returns titles aligned with schedule index shape', function (): void {
    $position = Position::factory()->create([
        'title' => 'Clerk',
        'code' => 'CLK',
    ]);
    $employee = Employee::factory()->create();
    EmployeePosition::factory()
        ->for($employee)
        ->for($position, 'position')
        ->create([
            'is_primary' => true,
            'end_date' => null,
        ]);

    $employee->load([
        'positions' => function ($q): void {
            $q->whereNull('deleted_at')->with(['position']);
        },
    ]);

    $rows = TeamHrEmployeeDirectoryExtras::mapActivePositions($employee);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'title' => 'Clerk',
            'code' => 'CLK',
            'is_primary' => true,
        ])
        ->and($rows[0]['id'])->toBeInt();
});
