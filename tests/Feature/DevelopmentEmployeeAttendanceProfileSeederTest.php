<?php

use App\Models\Employee;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceProfileSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('development employee attendance profile seeder assigns attendance and schedule rows', function (): void {
    $this->seed([
        OrganizationalStructureSeeder::class,
        RoleSeeder::class,
        DemoCooperativeSeeder::class,
        DevelopmentUserSeeder::class,
        WorkScheduleTemplatesSeeder::class,
        DevelopmentEmployeeAttendanceProfileSeeder::class,
    ]);

    $both = Employee::query()->where('id_number', 'EMP-SEED-002')->firstOrFail();
    expect($both->attendance_id)->toBe('BIO-HR001')
        ->and($both->work_schedule_template_id)->not->toBeNull();

    $attendanceOnly = Employee::query()->where('id_number', 'EMP-SEED-009')->firstOrFail();
    expect($attendanceOnly->attendance_id)->toBe('BIO-NOSCHED')
        ->and($attendanceOnly->work_schedule_template_id)->toBeNull();

    $templateOnly = Employee::query()->where('id_number', 'EMP-SEED-010')->firstOrFail();
    expect($templateOnly->attendance_id)->toBeNull()
        ->and($templateOnly->work_schedule_template_id)->not->toBeNull();

    $untouched = Employee::query()->where('id_number', 'EMP-SEED-012')->firstOrFail();
    expect($untouched->attendance_id)->toBeNull()
        ->and($untouched->work_schedule_template_id)->toBeNull();
});
