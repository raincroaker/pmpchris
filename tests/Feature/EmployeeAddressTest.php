<?php

use App\Models\Employee;
use App\Models\EmployeeAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee address factory persists expected attributes', function () {
    $address = EmployeeAddress::factory()->create([
        'type' => 'permanent',
        'address_line_1' => '123 Rizal Avenue',
        'address_line_2' => null,
        'barangay' => 'Poblacion',
        'city' => 'Davao City',
        'province' => 'Davao del Sur',
        'zip_code' => '8000',
        'country' => 'Philippines',
        'is_primary' => true,
    ]);

    expect($address->exists)->toBeTrue()
        ->and($address->type)->toBe('permanent')
        ->and($address->address_line_1)->toBe('123 Rizal Avenue')
        ->and($address->address_line_2)->toBeNull()
        ->and($address->barangay)->toBe('Poblacion')
        ->and($address->city)->toBe('Davao City')
        ->and($address->province)->toBe('Davao del Sur')
        ->and($address->zip_code)->toBe('8000')
        ->and($address->country)->toBe('Philippines')
        ->and($address->is_primary)->toBeTrue();
});

test('employee has many addresses', function () {
    $employee = Employee::factory()->create();

    EmployeeAddress::factory()->count(2)->for($employee)->create();

    expect($employee->addresses)->toHaveCount(2);
});

test('current factory state sets type to current', function () {
    $address = EmployeeAddress::factory()->current()->create();

    expect($address->type)->toBe('current');
});
