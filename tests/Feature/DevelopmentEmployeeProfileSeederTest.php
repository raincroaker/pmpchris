<?php

use App\Models\Employee;
use Database\Seeders\DevelopmentEmployeeProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('development employee profile seeder attaches contacts and addresses for EMP-SEED employees', function () {
    $employee = Employee::factory()->create([
        'id_number' => 'EMP-SEED-099',
    ]);

    (new DevelopmentEmployeeProfileSeeder)->run();

    $employee->refresh();

    expect($employee->contacts)->toHaveCount(3)
        ->and($employee->addresses)->toHaveCount(2);
});
