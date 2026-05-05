<?php

use App\Enums\AttendanceRecordStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\Organization;
use App\Models\WorkScheduleTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceDtrDemoSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceProfileSeeder;
use Database\Seeders\DevelopmentEmployeeAttendanceDaysSeeder;
use Database\Seeders\DevelopmentUserSeeder;
use Database\Seeders\HolidayTypesSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\OrganizationHolidaysSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Matches {@see DevelopmentEmployeeAttendanceDtrDemoSeeder} schedule + holiday exclusions (PHP Carbon parity).
 *
 * @return array<string, true>
 */
function dtr_demo_scheduled_day_keys(?WorkScheduleTemplate $template): array
{
    if ($template === null) {
        return [];
    }

    $days = $template->days;
    if (! is_array($days)) {
        return [];
    }

    $keys = [];
    foreach ($days as $d) {
        if (! is_string($d)) {
            continue;
        }

        $k = strtolower(trim($d));
        if ($k !== '') {
            $keys[$k] = true;
        }
    }

    return $keys;
}

function dtr_demo_expected_days_for_template(
    WorkScheduleTemplate $template,
    array $holidayKeys,
): int {
    $scheduled = dtr_demo_scheduled_day_keys($template);
    if ($scheduled === []) {
        return 0;
    }

    [$startStr, $endStr] = DevelopmentEmployeeAttendanceDtrDemoSeeder::dtrDemoRange();
    $rangeStart = CarbonImmutable::parse($startStr)->startOfDay();
    $rangeEnd = CarbonImmutable::parse($endStr)->startOfDay();
    $evaluationYear = (int) $rangeEnd->year;

    $matched = 0;
    for ($cursor = $rangeStart; $cursor->lte($rangeEnd); $cursor = $cursor->addDay()) {
        if ($evaluationYear !== (int) $cursor->year) {
            continue;
        }

        $dayKey = match ((int) $cursor->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => null,
        };
        if ($dayKey === null || ! isset($scheduled[$dayKey])) {
            continue;
        }

        $dateStr = $cursor->toDateString();
        if (isset($holidayKeys[$dateStr])) {
            continue;
        }

        $matched++;
    }

    return $matched;
}

test('dtr demo seeder lays down schedule aligned attendance days for multiple employees excluding holidays', function (): void {
    $this->seed([
        OrganizationalStructureSeeder::class,
        RoleSeeder::class,
        DemoCooperativeSeeder::class,
        DevelopmentUserSeeder::class,
        HolidayTypesSeeder::class,
        OrganizationHolidaysSeeder::class,
        WorkScheduleTemplatesSeeder::class,
        DevelopmentEmployeeAttendanceProfileSeeder::class,
        DevelopmentEmployeeAttendanceDaysSeeder::class,
        DevelopmentEmployeeAttendanceDtrDemoSeeder::class,
    ]);

    $organization = Organization::query()->where('code', 'PMPC')->firstOrFail();

    [$startStr, $endStr] = DevelopmentEmployeeAttendanceDtrDemoSeeder::dtrDemoRange();
    $evaluationYear = (int) CarbonImmutable::parse($endStr)->year;

    $holidayKeys = DevelopmentEmployeeAttendanceDtrDemoSeeder::expandedHolidayCalendarKeysWithinRangeForOrganization(
        (int) $organization->id,
        CarbonImmutable::parse($startStr)->startOfDay(),
        CarbonImmutable::parse($endStr)->startOfDay(),
        $evaluationYear,
    );

    $idNumbers = DevelopmentEmployeeAttendanceDtrDemoSeeder::dtrDemoSeededEmployeeIdNumbers();
    expect($idNumbers)->toHaveCount(5);

    $firstSunday = CarbonImmutable::parse($startStr);
    while ((int) $firstSunday->dayOfWeekIso !== 7) {
        $firstSunday = $firstSunday->addDay();
    }

    foreach ($idNumbers as $idNumber) {
        $employee = Employee::query()->where('id_number', $idNumber)->firstOrFail();
        $template = WorkScheduleTemplate::query()->find((int) $employee->work_schedule_template_id);
        expect($template)->not->toBeNull();

        $expected = dtr_demo_expected_days_for_template($template, $holidayKeys);

        $actual = EmployeeAttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
            ->count();

        expect($actual)->toBe($expected)
            ->and($actual)->toBeGreaterThan(15);

        expect(EmployeeAttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $firstSunday->toDateString())
            ->doesntExist())->toBeTrue();
    }

    $monSatEmp = Employee::query()->where('id_number', 'EMP-SEED-005')->firstOrFail();
    $weekdayEmp = Employee::query()->where('id_number', 'EMP-SEED-008')->firstOrFail();

    $countMonSat = EmployeeAttendanceDay::query()
        ->where('employee_id', $monSatEmp->id)
        ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
        ->count();
    $countWeekday = EmployeeAttendanceDay::query()
        ->where('employee_id', $weekdayEmp->id)
        ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
        ->count();

    expect($countMonSat)->toBeGreaterThan($countWeekday);

    $demoEmpIds = collect($idNumbers)
        ->map(fn (string $n): ?int => Employee::query()->where('id_number', $n)->value('id'))
        ->filter()
        ->values()
        ->map(fn ($id): int => (int) $id)
        ->all();

    $demoRangeDays = EmployeeAttendanceDay::query()
        ->whereIn('employee_id', $demoEmpIds)
        ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
        ->get(['id', 'employee_id', 'work_date', 'status']);

    expect($demoRangeDays)->not->toBeEmpty()
        ->and($demoRangeDays->every(
            fn (EmployeeAttendanceDay $d): bool => $d->status === AttendanceRecordStatus::Complete,
        ))->toBeTrue();

    $lateDemoCount = EmployeeAttendanceDay::query()
        ->whereIn('employee_id', $demoEmpIds)
        ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
        ->where('punctuality', 'late')
        ->count();

    expect($lateDemoCount)->toBeGreaterThan(12);

    $hmToMinutes = static function (string $hm): ?int {
        $hm = trim($hm);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hm, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    };

    $hasExtendedOvertimeClockOut = EmployeeAttendanceDay::query()
        ->whereIn('employee_id', $demoEmpIds)
        ->whereBetween('work_date', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
        ->with('segments')
        ->get()
        ->contains(function (EmployeeAttendanceDay $day) use ($hmToMinutes): bool {
            $lastSeg = $day->segments->sortBy('segment_index')->last();
            if ($lastSeg === null) {
                return false;
            }

            $out = $lastSeg->actual_out;
            $sched = $lastSeg->scheduled_out;
            if (! is_string($out) || ! is_string($sched)) {
                return false;
            }

            $actualM = $hmToMinutes($out);
            $scheduledM = $hmToMinutes($sched);
            if ($actualM === null || $scheduledM === null) {
                return false;
            }

            return ($actualM - $scheduledM) >= 26;
        });

    expect($hasExtendedOvertimeClockOut)->toBeTrue();
});
