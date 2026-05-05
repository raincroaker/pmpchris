<?php

use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveDay;
use App\Services\EmployeeLeaveDaysSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('syncs explicit disjoint days replacing previous rows', function (): void {
    $leave = EmployeeLeave::factory()->create([
        'start_date' => '2026-06-02',
        'end_date' => '2026-06-06',
        'is_half_day_start' => false,
        'is_half_day_end' => false,
    ]);

    app(EmployeeLeaveDaysSyncService::class)->sync($leave, [
        ['date' => '2026-06-02', 'is_half_day' => true],
        ['date' => '2026-06-06', 'is_half_day' => false],
    ]);

    $rows = EmployeeLeaveDay::query()
        ->where('employee_leave_id', $leave->id)
        ->orderBy('leave_date')
        ->get(['leave_date', 'is_half_day']);

    expect($rows)->toHaveCount(2);
    expect($rows[0]->leave_date->format('Y-m-d'))->toBe('2026-06-02');
    expect($rows[0]->is_half_day)->toBeTrue();
    expect($rows[1]->leave_date->format('Y-m-d'))->toBe('2026-06-06');
    expect($rows[1]->is_half_day)->toBeFalse();
});

it('expands legacy span when explicit payload is omitted', function (): void {
    $leave = EmployeeLeave::factory()->create([
        'start_date' => '2026-05-26',
        'end_date' => '2026-05-28',
        'is_half_day_start' => false,
        'is_half_day_end' => false,
    ]);

    app(EmployeeLeaveDaysSyncService::class)->sync($leave, null);

    expect(
        EmployeeLeaveDay::query()->where('employee_leave_id', $leave->id)->count(),
    )->toBe(3);
});
