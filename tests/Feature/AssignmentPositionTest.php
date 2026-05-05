<?php

use App\Models\AssignmentPosition;
use App\Models\EmployeeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('assignment position factory persists expected attributes', function () {
    $row = AssignmentPosition::factory()->create([
        'is_primary_for_assignment' => true,
        'start_date' => '2024-05-01',
        'end_date' => '2025-05-01',
    ]);

    expect($row->exists)->toBeTrue()
        ->and($row->is_primary_for_assignment)->toBeTrue()
        ->and($row->start_date->format('Y-m-d'))->toBe('2024-05-01')
        ->and($row->end_date->format('Y-m-d'))->toBe('2025-05-01');
});

test('employee assignment has many assignment positions', function () {
    $assignment = EmployeeAssignment::factory()->create();

    AssignmentPosition::factory()->count(2)->create([
        'employee_assignment_id' => $assignment->id,
    ]);

    expect($assignment->assignmentPositions)->toHaveCount(2);
});
