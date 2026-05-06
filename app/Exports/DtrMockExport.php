<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class DtrMockExport implements FromView, WithTitle
{
    /**
     * @param  Collection<int, array{
     *     day: int,
     *     dayName: string,
     *     spanMiddle: string|null,
     *     amIn: string,
     *     amOut: string,
     *     pmIn: string,
     *     pmOut: string,
     *     otIn: string,
     *     otOut: string,
     *     remarks: string,
     *     hours: string
     * }>  $rows
     * @param  array{
     *     template_name: string,
     *     is_overnight: bool,
     *     is_active: bool,
     *     scheduled_days: string,
     *     clock_pattern: string,
     *     expected_windows: string,
     *     gross_time: string,
     *     net_time: string,
     *     unpaid_break: string,
     *     late_grace_minutes: int,
     *     notes: string|null
     * }|null  $workScheduleReference
     * @param  Collection<int, array{date: string, type: string}>  $otRequests
     * @param  Collection<int, array{date: string, type: string}>  $leaveRequests
     * @param  Collection<int, array{date: string, name: string}>  $holidaysInMonth
     */
    public function __construct(
        public string $employeeName,
        public string $employeeId,
        public string $attendanceId,
        public string $position,
        public string $unit,
        public string $branchOrDept,
        public string $monthLabel,
        public string $durationLabel,
        public string $generatedAt,
        public int $absenceCount,
        public int $leaveCountMonth,
        public int $lateIncidentCount,
        public int $lateMinutesTotal,
        public int $earlyOutIncidentCount,
        public int $earlyOutMinutesTotal,
        public string $grossTimeTotal,
        public string $netTimeTotal,
        public Collection $rows,
        public ?array $workScheduleReference,
        public Collection $otRequests,
        public Collection $leaveRequests,
        public Collection $holidaysInMonth,
    ) {}

    public function view(): View
    {
        return view('exports.dtr-mock', [
            'employeeName' => $this->employeeName,
            'employeeId' => $this->employeeId,
            'attendanceId' => $this->attendanceId,
            'position' => $this->position,
            'unit' => $this->unit,
            'branchOrDept' => $this->branchOrDept,
            'monthLabel' => $this->monthLabel,
            'durationLabel' => $this->durationLabel,
            'generatedAt' => $this->generatedAt,
            'absenceCount' => $this->absenceCount,
            'leaveCountMonth' => $this->leaveCountMonth,
            'lateIncidentCount' => $this->lateIncidentCount,
            'lateMinutesTotal' => $this->lateMinutesTotal,
            'earlyOutIncidentCount' => $this->earlyOutIncidentCount,
            'earlyOutMinutesTotal' => $this->earlyOutMinutesTotal,
            'grossTimeTotal' => $this->grossTimeTotal,
            'netTimeTotal' => $this->netTimeTotal,
            'rows' => $this->rows,
            'workScheduleReference' => $this->workScheduleReference,
            'otRequests' => $this->otRequests,
            'leaveRequests' => $this->leaveRequests,
            'holidaysInMonth' => $this->holidaysInMonth,
        ]);
    }

    public function title(): string
    {
        $lastName = self::extractLastName($this->employeeName);
        $periodCode = CarbonImmutable::parse('1 '.$this->monthLabel)->format('MY');
        $employeeCode = self::sanitizeIdentifier($this->employeeId);

        return self::fitExcelSheetTitle("{$lastName}_{$periodCode}_{$employeeCode}");
    }

    public function downloadFileName(): string
    {
        $lastName = self::extractLastName($this->employeeName);
        $periodCode = CarbonImmutable::parse('1 '.$this->monthLabel)->format('M-Y');
        $employeeCode = self::sanitizeIdentifier($this->employeeId);

        return "DTR_{$lastName}_{$employeeCode}_{$periodCode}.xlsx";
    }

    public static function sample(): self
    {
        $rows = collect();

        $absenceDays = [12, 26];
        $leaveDay = 19;
        $otDay = 15;

        for ($day = 1; $day <= 31; $day++) {
            $date = CarbonImmutable::create(2026, 5, $day);
            $isWeekend = $date->isWeekend();

            if ($isWeekend) {
                $rows->push(self::rowSpanBand($day, $date->format('D'), 'REST DAY'));

                continue;
            }

            if (in_array($day, $absenceDays, true)) {
                $rows->push(self::rowSpanBand($day, $date->format('D'), 'ABSENCE'));

                continue;
            }

            if ($day === $leaveDay) {
                $rows->push([
                    'day' => $day,
                    'dayName' => $date->format('D'),
                    'spanMiddle' => null,
                    'amIn' => '',
                    'amOut' => '',
                    'pmIn' => '',
                    'pmOut' => '',
                    'otIn' => '',
                    'otOut' => '',
                    'remarks' => 'OL',
                    'hours' => '',
                ]);

                continue;
            }

            if ($day === $otDay) {
                $rows->push([
                    'day' => $day,
                    'dayName' => $date->format('D'),
                    'spanMiddle' => null,
                    'amIn' => '08:00',
                    'amOut' => '12:00',
                    'pmIn' => '13:00',
                    'pmOut' => '17:00',
                    'otIn' => '18:30',
                    'otOut' => '20:00',
                    'remarks' => 'OT',
                    'hours' => '9.50',
                ]);

                continue;
            }

            $rows->push([
                'day' => $day,
                'dayName' => $date->format('D'),
                'spanMiddle' => null,
                'amIn' => '08:00',
                'amOut' => '12:00',
                'pmIn' => '13:00',
                'pmOut' => '17:00',
                'otIn' => '',
                'otOut' => '',
                'remarks' => '',
                'hours' => '8.00',
            ]);
        }

        $periodStart = CarbonImmutable::create(2026, 5, 1);
        $periodEnd = CarbonImmutable::create(2026, 5, 31);
        $durationLabel = self::formatShortDurationRange($periodStart, $periodEnd);

        $generatedAt = CarbonImmutable::now()->format('Y-m-d H:i');

        $otRequests = collect([
            [
                'date' => 'May 15, 2026',
                'type' => 'Ordinary weekday OT',
            ],
            [
                'date' => 'May 22, 2026',
                'type' => 'Post-shift filing OT',
            ],
        ]);

        $leaveRequests = collect([
            [
                'date' => 'May 19, 2026',
                'type' => 'Vacation leave (1 day)',
            ],
            [
                'date' => 'May 30, 2026',
                'type' => 'Sick leave (half day)',
            ],
        ]);

        $holidaysInMonth = collect([
            [
                'date' => 'May 1, 2026',
                'name' => 'Labor Day',
            ],
        ]);

        $workScheduleReference = [
            'template_name' => 'HR Admin — Split day (mock)',
            'is_overnight' => false,
            'is_active' => true,
            'scheduled_days' => 'Mon, Tue, Wed, Thu, Fri',
            'clock_pattern' => 'Split sessions (same day)',
            'expected_windows' => "Session 1: 08:00–12:00\nSession 2: 13:00–17:00",
            'gross_time' => '9:00',
            'net_time' => '8:30',
            'unpaid_break' => '30 min (between sessions)',
            'late_grace_minutes' => 10,
            'notes' => 'Main branch default desk schedule. Mirrors Work Schedules index: name, days, window, gross/net, breaktime, grace.',
        ];

        return new self(
            'Santos, Maria Clara R.',
            'EMP-2024-0042',
            'ATT-2026-0512-8841',
            'HR Specialist II',
            'Human Resources — Main Branch',
            'Main Branch — Human Resources',
            'May 2026',
            $durationLabel,
            $generatedAt,
            2,
            1,
            2,
            35,
            0,
            0,
            '184:00',
            '176:30',
            $rows,
            $workScheduleReference,
            $otRequests,
            $leaveRequests,
            $holidaysInMonth,
        );
    }

    /**
     * Compact range, e.g. "May 1 - May 31, 2026" (year only on end when same calendar year).
     */
    public static function formatShortDurationRange(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j').' - '.$end->format('M j, Y');
    }

    private static function extractLastName(string $employeeName): string
    {
        $parts = explode(',', $employeeName);
        $lastName = trim($parts[0] ?? $employeeName);

        return $lastName !== '' ? preg_replace('/\s+/', '', $lastName) ?? 'Employee' : 'Employee';
    }

    private static function sanitizeIdentifier(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '';

        return $sanitized !== '' ? $sanitized : 'EMP';
    }

    private static function fitExcelSheetTitle(string $title): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title) ?? 'Sheet';

        return mb_substr($clean, 0, 31);
    }

    /**
     * @return array{
     *     day: int,
     *     dayName: string,
     *     spanMiddle: string,
     *     amIn: string,
     *     amOut: string,
     *     pmIn: string,
     *     pmOut: string,
     *     otIn: string,
     *     otOut: string,
     *     remarks: string,
     *     hours: string
     * }
     */
    private static function rowSpanBand(int $day, string $dayName, string $label): array
    {
        return [
            'day' => $day,
            'dayName' => $dayName,
            'spanMiddle' => $label,
            'amIn' => '',
            'amOut' => '',
            'pmIn' => '',
            'pmOut' => '',
            'otIn' => '',
            'otOut' => '',
            'remarks' => '',
            'hours' => '',
        ];
    }
}
