<?php

namespace Database\Seeders;

use App\Enums\AttendanceEntrySource;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceSegment;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\WorkScheduleTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\BuildsAttendanceDayRowsFromWorkScheduleTemplates;
use Illuminate\Database\Seeder;

/**
 * Seeds {@see EmployeeAttendanceDay} rows across a contiguous calendar span for demo DTR/export testing.
 *
 * Rows are inserted only when the calendar day lies on that employee's assigned schedule template {@see WorkScheduleTemplate::$days}
 * and does not collide with seeded {@see OrganizationHoliday} dates (exact-day match for the evaluation year).
 * Every seeded day is {@see AttendanceRecordStatus::Complete}: pseudo-random **late first clock-in** on ~2⁄9 of rows, **extended overtime clock-out** on ~1⁄11 (when templates have ≥3 sessions), otherwise on-time punches.
 */
class DevelopmentEmployeeAttendanceDtrDemoSeeder extends Seeder
{
    use BuildsAttendanceDayRowsFromWorkScheduleTemplates;

    /** Inclusive ISO span (evaluation year aligns with seeded PMPC holidays when yearly recurring). */
    private const RANGE_START = '2026-02-25';

    private const RANGE_END = '2026-03-27';

    /**
     * EMP-SEED-* id numbers guaranteed attendance + assigned template ({@see DevelopmentEmployeeAttendanceProfileSeeder}).
     *
     * @var list<string>
     */
    private const DTR_DEMO_SEED_IDS = [
        'EMP-SEED-001',
        'EMP-SEED-005',
        'EMP-SEED-007',
        'EMP-SEED-008',
        'EMP-SEED-014',
    ];

    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $orgId = (int) $organization->id;
        $rangeStart = CarbonImmutable::parse(self::RANGE_START)->startOfDay();
        $rangeEnd = CarbonImmutable::parse(self::RANGE_END)->startOfDay();
        $evaluationYear = (int) $rangeEnd->year;

        $holidayKeys = self::expandedHolidayCalendarKeysWithinRangeForOrganization(
            $orgId,
            $rangeStart,
            $rangeEnd,
            $evaluationYear,
        );

        foreach (self::DTR_DEMO_SEED_IDS as $idNumber) {
            $employee = Employee::query()
                ->where('id_number', $idNumber)
                ->whereNull('deleted_at')
                ->first();
            if ($employee === null) {
                continue;
            }

            if (! $this->attendanceRecordingPrerequisitesSatisfied($employee)) {
                continue;
            }

            $template = $this->resolveEmployeeWorkScheduleTemplate($employee);
            if ($template === null) {
                continue;
            }

            $scheduledDayKeys = $this->scheduledTemplateDayKeys($template);
            if ($scheduledDayKeys === []) {
                continue;
            }

            for ($cursor = $rangeStart; $cursor->lte($rangeEnd); $cursor = $cursor->addDay()) {
                $dayKey = $this->calendarDayIsoKey($cursor);
                if ($dayKey === null) {
                    continue;
                }

                if (! isset($scheduledDayKeys[$dayKey])) {
                    continue;
                }

                $dateStr = $cursor->toDateString();
                if (isset($holidayKeys[$dateStr])) {
                    continue;
                }

                if (EmployeeAttendanceDay::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', $dateStr)
                    ->exists()) {
                    continue;
                }

                $ingestKey = $this->buildDeviceAttendanceIngestKey($dateStr, (int) $employee->id);
                $segmentRows = $this->segmentRowsForDtrDemoCalendarDay($template, $idNumber, $dateStr);
                if ($segmentRows === []) {
                    continue;
                }

                $derived = $this->deriveAttendanceDerivedFieldsFromRows($segmentRows, $template);

                $day = EmployeeAttendanceDay::query()->create([
                    'organization_id' => $orgId,
                    'employee_id' => $employee->id,
                    'organizational_unit_id' => null,
                    'work_date' => $dateStr,
                    'work_schedule_template_id' => $template->id,
                    'clock_pattern' => $template->clock_pattern,
                    'is_overnight_schedule' => $template->is_overnight,
                    'ingest_key' => $ingestKey,
                    'original_entry_source' => AttendanceEntrySource::Device,
                    'last_modified_source' => AttendanceEntrySource::Device,
                    'status' => $derived['status'],
                    'punctuality' => $derived['punctuality'],
                    'net_hours' => $derived['net_hours'],
                    'variance_label' => null,
                ]);

                foreach ($segmentRows as $index => $row) {
                    EmployeeAttendanceSegment::query()->create([
                        'employee_attendance_day_id' => $day->id,
                        'segment_index' => $index,
                        'label' => $row['label'],
                        'scheduled_in' => $row['scheduled_in'],
                        'scheduled_out' => $row['scheduled_out'],
                        'actual_in' => $row['actual_in'],
                        'actual_out' => $row['actual_out'],
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, true> Map of YYYY-MM-DD (inclusive occurrence days for this evaluation year touching the demo range).
     */
    public static function expandedHolidayCalendarKeysWithinRangeForOrganization(
        int $organizationId,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        int $evaluationYear,
    ): array {
        $holidayDates = [];

        $holidays = OrganizationHoliday::query()
            ->where('organization_id', $organizationId)
            ->get(['start_date', 'end_date', 'recurrence']);

        foreach ($holidays as $holiday) {
            $recurrenceRaw = $holiday->recurrence;
            $frequency = null;
            if (is_array($recurrenceRaw)) {
                $freq = $recurrenceRaw['frequency'] ?? null;
                $frequency = is_string($freq) ? $freq : null;
            }

            $startStored = CarbonImmutable::parse($holiday->start_date)->startOfDay();
            $endStored = CarbonImmutable::parse($holiday->end_date)->startOfDay();

            if ($frequency === 'yearly') {
                $occStart = self::carryMonthDayOntoEvaluationYearSafe($evaluationYear, $startStored)
                    ?? $startStored;
                $occEnd = self::carryMonthDayOntoEvaluationYearSafe($evaluationYear, $endStored)
                    ?? $endStored;
            } else {
                $occStart = $startStored;
                $occEnd = $endStored;
            }

            if ($occEnd->lt($occStart)) {
                [$occStart, $occEnd] = [$occEnd, $occStart];
            }

            $cursorStart = $occStart->gte($rangeStart) ? $occStart : $rangeStart;
            $cursorEnd = $occEnd->lte($rangeEnd) ? $occEnd : $rangeEnd;
            if ($cursorEnd->lt($cursorStart)) {
                continue;
            }

            for ($cursor = $cursorStart; $cursor->lte($cursorEnd); $cursor = $cursor->addDay()) {
                if ($evaluationYear !== (int) $cursor->year) {
                    continue;
                }

                $holidayDates[$cursor->toDateString()] = true;
            }
        }

        return $holidayDates;
    }

    private static function carryMonthDayOntoEvaluationYearSafe(
        int $year,
        CarbonImmutable $calendarAnchor,
    ): ?CarbonImmutable {
        $month = (int) $calendarAnchor->month;
        $day = (int) $calendarAnchor->day;

        if (! checkdate($month, $day, $year)) {
            try {
                return CarbonImmutable::create($year, $month, 1)->endOfMonth()->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return CarbonImmutable::create($year, $month, $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Stable pseudo-random mix: most days on-time complete, ~two-ninths late first clock-in, ~one-eleventh with extended OT on the last scheduled session — all derived {@see AttendanceRecordStatus::Complete}.
     *
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string, actual_in: ?string, actual_out: ?string}>
     */
    private function segmentRowsForDtrDemoCalendarDay(
        WorkScheduleTemplate $template,
        string $employeeIdNumber,
        string $dateStr,
    ): array {
        $scheduled = $this->scheduledSegmentsFromTemplate($template);
        if ($scheduled === []) {
            return [];
        }

        $digest = crc32($dateStr.'|'.$employeeIdNumber);

        if (count($scheduled) >= 3 && ($digest % 11) === 0) {
            $extra = 34 + ($digest % 9);

            return $this->splitCompleteExtendedOvertimeTailRows($template, $extra);
        }

        if ((($digest >> 3) % 9) <= 1) {
            return $this->splitLateFirstClockInRows($template);
        }

        return $this->standardFilledSegmentRowsFromTemplate($template);
    }

    /** @return array<string, true> */
    private function scheduledTemplateDayKeys(WorkScheduleTemplate $template): array
    {
        $days = $template->days;
        if (! is_array($days)) {
            return [];
        }

        $keys = [];
        foreach ($days as $d) {
            if (! is_string($d)) {
                continue;
            }

            $key = strtolower(trim($d));
            if ($key === '') {
                continue;
            }

            $keys[$key] = true;
        }

        return $keys;
    }

    private function calendarDayIsoKey(CarbonImmutable $day): ?string
    {
        return match ((int) $day->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => null,
        };
    }

    /** @internal Introspection hook for deterministic tests against this seeder span. */
    public static function dtrDemoRange(): array
    {
        return [self::RANGE_START, self::RANGE_END];
    }

    /** @internal Employee id numbers seeded for demo DTR range. */
    public static function dtrDemoSeededEmployeeIdNumbers(): array
    {
        return self::DTR_DEMO_SEED_IDS;
    }
}
