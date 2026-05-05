<?php

use App\Enums\EmployeeBirthdayVisibility;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee factory persists expected attributes', function () {
    $employee = Employee::factory()->create([
        'id_number' => 'EMP-TEST-001',
        'attendance_id' => 'ATT-TEST-001',
        'first_name' => 'John',
        'middle_name' => 'Roe',
        'last_name' => 'Doe',
        'suffix' => null,
        'birthdate' => '1990-05-15',
        'sex' => 'male',
        'civil_status' => 'single',
        'nationality' => 'Filipino',
        'religion' => null,
        'birthday_visibility' => EmployeeBirthdayVisibility::Private,
    ]);

    expect($employee->exists)->toBeTrue()
        ->and($employee->id_number)->toBe('EMP-TEST-001')
        ->and($employee->attendance_id)->toBe('ATT-TEST-001')
        ->and($employee->first_name)->toBe('John')
        ->and($employee->middle_name)->toBe('Roe')
        ->and($employee->last_name)->toBe('Doe')
        ->and($employee->birthdate->format('Y-m-d'))->toBe('1990-05-15')
        ->and($employee->sex)->toBe('male')
        ->and($employee->civil_status)->toBe('single')
        ->and($employee->nationality)->toBe('Filipino')
        ->and($employee->religion)->toBeNull()
        ->and($employee->birthday_visibility)->toBe(EmployeeBirthdayVisibility::Private);
});

test('employee birthday_visibility persists as enum', function () {
    $employee = Employee::factory()->create([
        'birthday_visibility' => EmployeeBirthdayVisibility::Branch,
    ]);

    expect($employee->fresh()->birthday_visibility)->toBe(EmployeeBirthdayVisibility::Branch);
});

test('soft deleting an employee sets deleted_at', function () {
    $employee = Employee::factory()->create();

    $employee->delete();

    expect($employee->trashed())->toBeTrue()
        ->and($employee->fresh()->trashed())->toBeTrue();
});

test('user can load linked employee', function () {
    $employee = Employee::factory()->create();
    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $user->load('employee');

    expect($user->employee)->not->toBeNull()
        ->and($user->employee->is($employee))->toBeTrue();
});

test('employee has one user when linked', function () {
    $employee = Employee::factory()->create();
    User::factory()->create(['employee_id' => $employee->id]);

    $employee->load('user');

    expect($employee->user)->not->toBeNull()
        ->and($employee->user->employee_id)->toBe($employee->id);
});

test('two users cannot share the same employee_id', function () {
    $employee = Employee::factory()->create();
    User::factory()->create(['employee_id' => $employee->id]);

    expect(fn () => User::factory()->create(['employee_id' => $employee->id]))
        ->toThrow(QueryException::class);
});

test('employee attendance_id may be null', function () {
    $employee = Employee::factory()->create([
        'id_number' => 'EMP-NULL-ATT-'.uniqid(),
        'attendance_id' => null,
    ]);

    expect($employee->fresh()->attendance_id)->toBeNull();
});
