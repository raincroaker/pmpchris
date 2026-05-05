<?php

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeOvertime;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an employee leave with required approver and join-only policy', function (): void {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create();
    $approver = Employee::factory()->create();
    $policy = LeavePolicy::factory()->create(['organization_id' => $organization->id]);

    $leave = EmployeeLeave::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'leave_policy_id' => $policy->id,
        'approver_employee_id' => $approver->id,
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-12',
        'status' => EmployeeHrRecordStatus::Approved,
        'submitted_at' => '2026-05-01',
        'decided_at' => '2026-05-02',
    ]);

    $leave->load('leavePolicy', 'approver');

    expect($leave->leavePolicy->is($policy))->toBeTrue()
        ->and($leave->leavePolicy->name)->not->toBeEmpty()
        ->and($leave->approver->is($approver))->toBeTrue();
});

it('persists an employee overtime with required approver and join-only policy', function (): void {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create();
    $approver = Employee::factory()->create();
    $otPolicy = OvertimePolicy::factory()->create(['organization_id' => $organization->id]);

    $ot = EmployeeOvertime::factory()->create([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'overtime_policy_id' => $otPolicy->id,
        'approver_employee_id' => $approver->id,
        'ot_date' => '2026-05-15',
        'hours' => 4.5,
        'status' => EmployeeHrRecordStatus::Approved,
        'submitted_at' => '2026-05-14',
    ]);

    $ot->load('overtimePolicy');

    expect($ot->overtimePolicy->is($otPolicy))->toBeTrue()
        ->and($ot->overtimePolicy->code)->not->toBeEmpty();
});

it('requires approver_employee_id at the database layer', function (): void {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create();
    $policy = LeavePolicy::factory()->create(['organization_id' => $organization->id]);

    expect(fn () => EmployeeLeave::query()->insert([
        'organization_id' => $organization->id,
        'employee_id' => $employee->id,
        'organizational_unit_id' => null,
        'leave_policy_id' => $policy->id,
        'start_date' => '2026-05-10',
        'end_date' => '2026-05-12',
        'is_half_day_start' => false,
        'is_half_day_end' => false,
        'status' => EmployeeHrRecordStatus::Approved->value,
        'submitted_at' => '2026-05-01',
        'decided_at' => null,
        'approver_employee_id' => null,
        'reason' => null,
        'created_by_user_id' => null,
        'updated_by_user_id' => null,
        'deleted_by_user_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]))->toThrow(QueryException::class);
});

it('keeps leave policy organization aligned via factory', function (): void {
    $leave = EmployeeLeave::factory()->create();

    expect($leave->organization_id)->toBe($leave->leavePolicy->organization_id);
});

it('keeps overtime policy organization aligned via factory', function (): void {
    $ot = EmployeeOvertime::factory()->create();

    expect($ot->organization_id)->toBe($ot->overtimePolicy->organization_id);
});

it('exposes organization relations for employee leave and overtime', function (): void {
    $organization = Organization::factory()->create();
    EmployeeLeave::factory()->create(['organization_id' => $organization->id]);
    EmployeeOvertime::factory()->create(['organization_id' => $organization->id]);

    $organization->load('employeeLeaves', 'employeeOvertimes');

    expect($organization->employeeLeaves)->toHaveCount(1)
        ->and($organization->employeeOvertimes)->toHaveCount(1);
});
