<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\PhaseOneInitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('phase one bootstrap super admin uses generic super admin identity', function (): void {
    (new PhaseOneInitialSystemSeeder)->run();

    $user = User::query()->where('email', 'superadmin@hrnexus.com')->firstOrFail();

    expect($user->name)->toBe('Super Admin');

    /** @var Employee|null $employee */
    $employee = $user->employee;
    expect($employee)->not->toBeNull()
        ->and((string) $employee->first_name)->toBe('Super')
        ->and((string) $employee->last_name)->toBe('Admin')
        ->and((string) $employee->id_number)->toBe('20200024');
});

test('phase one bootstrap hr head uses generic identity', function (): void {
    (new PhaseOneInitialSystemSeeder)->run();

    $user = User::query()->where('email', 'hrhead@hrnexus.com')->firstOrFail();

    expect($user->name)->toBe('HR Head');

    /** @var Employee|null $employee */
    $employee = $user->employee;
    expect($employee)->not->toBeNull()
        ->and((string) $employee->first_name)->toBe('HR')
        ->and((string) $employee->last_name)->toBe('Head')
        ->and((string) $employee->id_number)->toBe('20170032');
});
