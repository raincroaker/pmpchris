<?php

use App\Enums\AttendanceRecordStatus;
use App\Support\AttendancePunctualityResolver;
use App\Support\AttendanceRecordStatusResolver;

test('attendance record status is incomplete for empty punch list', function (): void {
    expect(AttendanceRecordStatusResolver::fromPunchSegments([]))
        ->toBe(AttendanceRecordStatus::Incomplete);
});

test('attendance record status is complete when every session has in and out', function (): void {
    $rows = [
        ['actual_in' => '08:30', 'actual_out' => '12:00'],
        ['actual_in' => '13:00', 'actual_out' => '17:00'],
    ];

    expect(AttendanceRecordStatusResolver::fromPunchSegments($rows))
        ->toBe(AttendanceRecordStatus::Complete);
});

test('attendance record status is ongoing when a session has clock-in without clock-out', function (): void {
    $rows = [
        ['actual_in' => '08:35', 'actual_out' => null],
    ];

    expect(AttendanceRecordStatusResolver::fromPunchSegments($rows))
        ->toBe(AttendanceRecordStatus::Ongoing);
});

test('attendance record status is incomplete when clock-out exists without clock-in', function (): void {
    $rows = [
        ['actual_in' => null, 'actual_out' => '16:00'],
    ];

    expect(AttendanceRecordStatusResolver::fromPunchSegments($rows))
        ->toBe(AttendanceRecordStatus::Incomplete);
});

test('attendance record status is incomplete when a later session is empty after a completed session', function (): void {
    $rows = [
        ['actual_in' => '08:28', 'actual_out' => '12:02'],
        ['actual_in' => null, 'actual_out' => null],
    ];

    expect(AttendanceRecordStatusResolver::fromPunchSegments($rows))
        ->toBe(AttendanceRecordStatus::Incomplete);
});

test('attendance punctuality is null when first clock-in is missing', function (): void {
    expect(AttendancePunctualityResolver::fromFirstSegmentClockIn(
        ['scheduled_in' => '08:30', 'actual_in' => null],
        15,
    ))->toBeNull();
});

test('attendance punctuality is on time within grace of scheduled start', function (): void {
    expect(AttendancePunctualityResolver::fromFirstSegmentClockIn(
        ['scheduled_in' => '08:30', 'actual_in' => '08:44'],
        15,
    ))->toBe('on_time');
});

test('attendance punctuality is late after grace of scheduled start', function (): void {
    expect(AttendancePunctualityResolver::fromFirstSegmentClockIn(
        ['scheduled_in' => '08:30', 'actual_in' => '09:10'],
        15,
    ))->toBe('late');
});
