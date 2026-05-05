<?php

use App\Models\Employee;
use App\Models\EmployeeContact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('employee contact factory persists expected attributes', function () {
    $contact = EmployeeContact::factory()->create([
        'category' => 'personal',
        'type' => 'mobile',
        'contact_number' => '+639171234567',
        'is_primary' => true,
    ]);

    expect($contact->exists)->toBeTrue()
        ->and($contact->category)->toBe('personal')
        ->and($contact->type)->toBe('mobile')
        ->and($contact->contact_person)->toBeNull()
        ->and($contact->relationship)->toBeNull()
        ->and($contact->contact_number)->toBe('+639171234567')
        ->and($contact->is_primary)->toBeTrue();
});

test('employee has many contacts', function () {
    $employee = Employee::factory()->create();

    EmployeeContact::factory()->count(2)->for($employee)->create();

    expect($employee->contacts)->toHaveCount(2);
});

test('emergency factory state sets contact person and relationship', function () {
    $contact = EmployeeContact::factory()->emergency()->create();

    expect($contact->category)->toBe('emergency')
        ->and($contact->contact_person)->not->toBeNull()
        ->and($contact->relationship)->not->toBeNull();
});
