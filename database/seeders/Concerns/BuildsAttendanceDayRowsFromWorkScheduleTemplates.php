<?php

namespace Database\Seeders\Concerns;

use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Employee;
use App\Models\WorkScheduleTemplate;
use App\Support\AttendancePunctualityResolver;
use App\Support\AttendanceRecordStatusResolver;
use App\Support\AttendanceTemplateScheduledNetHours;

trait BuildsAttendanceDayRowsFromWorkScheduleTemplates
{
    /**
     * Same gate as manual Team Attendance: non-empty biometric id + assigned schedule template row.
     */
    protected function attendanceRecordingPrerequisitesSatisfied(Employee $employee): bool
    {
        $attendanceProfileId = $employee->attendance_id;
        if (! is_string($attendanceProfileId) || trim($attendanceProfileId) === '') {
            return false;
        }

        return $employee->work_schedule_template_id !== null;
    }

    protected function resolveEmployeeWorkScheduleTemplate(Employee $employee): ?WorkScheduleTemplate
    {
        return WorkScheduleTemplate::query()->find((int) $employee->work_schedule_template_id);
    }

    /**
     * Stable device-style key (unique per org via employee id + work date).
     */
    protected function buildDeviceAttendanceIngestKey(string $workDate, int $employeeId): string
    {
        $compactDate = str_replace('-', '', $workDate);
        $key = sprintf('ATD-%s-%06d', $compactDate, $employeeId);
        if (strlen($key) > 80) {
            return substr($key, 0, 80);
        }

        return $key;
    }

    protected function netHoursForAttendanceDayIfComplete(
        AttendanceRecordStatus $status,
        WorkScheduleTemplate $template,
    ): ?float {
        if ($status !== AttendanceRecordStatus::Complete) {
            return null;
        }

        return AttendanceTemplateScheduledNetHours::fromTemplate($template);
    }

    /**
     * @return array{status: AttendanceRecordStatus, punctuality: ?string, net_hours: ?float}
     */
    protected function deriveAttendanceDerivedFieldsFromRows(
        array $segmentRows,
        WorkScheduleTemplate $template,
    ): array {
        $status = AttendanceRecordStatusResolver::fromPunchSegments($segmentRows);
        $grace = max(0, (int) $template->grace_late_arrival_minutes);
        $punctuality = AttendancePunctualityResolver::fromFirstSegmentClockIn(
            $segmentRows[0] ?? null,
            $grace,
        );
        $netHours = $this->netHoursForAttendanceDayIfComplete($status, $template);

        return [
            'status' => $status,
            'punctuality' => $punctuality,
            'net_hours' => $netHours,
        ];
    }

    /**
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string, actual_in: ?string, actual_out: ?string}>
     */
    protected function buildSegmentRowsForSeedEmployee(string $idNumber, WorkScheduleTemplate $template): array
    {
        $override = $this->seedSegmentOverrideRows($idNumber, $template);
        if ($override !== null) {
            return $override;
        }

        return $this->standardFilledSegmentRowsFromTemplate($template);
    }

    /**
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string, actual_in: ?string, actual_out: ?string}>|null
     */
    protected function seedSegmentOverrideRows(string $idNumber, WorkScheduleTemplate $template): ?array
    {
        return match ($idNumber) {
            'EMP-SEED-001', 'EMP-SEED-007', 'EMP-SEED-011' => $this->splitLateFirstClockInRows($template),
            'EMP-SEED-005' => $this->branchWeekCompleteRows($template),
            'EMP-SEED-014' => $this->splitCompleteExtendedOvertimeTailRows($template, 34),
            default => null,
        };
    }

    protected function splitLateFirstClockInRows(WorkScheduleTemplate $template): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        if ($baseline === []) {
            return [];
        }

        $rows = [];
        foreach ($baseline as $index => $row) {
            if ($index === 0) {
                $lateIn = $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], 17);
                $rows[] = [
                    'label' => $row['label'],
                    'scheduled_in' => $row['scheduled_in'],
                    'scheduled_out' => $row['scheduled_out'],
                    'actual_in' => $lateIn,
                    'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], 2),
                ];

                continue;
            }

            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], -2),
                'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], 3),
            ];
        }

        return $rows;
    }

    protected function splitSessionsOpenAtIndexRows(WorkScheduleTemplate $template, int $openIndex): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        if ($baseline === []) {
            return [];
        }

        $rows = [];
        foreach ($baseline as $index => $row) {
            if ($index < $openIndex) {
                $rows[] = [
                    'label' => $row['label'],
                    'scheduled_in' => $row['scheduled_in'],
                    'scheduled_out' => $row['scheduled_out'],
                    'actual_in' => $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], -2),
                    'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], 2),
                ];

                continue;
            }

            if ($index === $openIndex) {
                $rows[] = [
                    'label' => $row['label'],
                    'scheduled_in' => $row['scheduled_in'],
                    'scheduled_out' => $row['scheduled_out'],
                    'actual_in' => $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], -1),
                    'actual_out' => null,
                ];

                continue;
            }

            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => null,
                'actual_out' => null,
            ];
        }

        return $rows;
    }

    protected function splitSessionsAllPunchesAbsentRows(WorkScheduleTemplate $template): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        $rows = [];
        foreach ($baseline as $row) {
            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => null,
                'actual_out' => null,
            ];
        }

        return $rows;
    }

    protected function branchWeekCompleteRows(WorkScheduleTemplate $template): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        if ($baseline === []) {
            return [];
        }

        $rows = [];
        foreach ($baseline as $index => $row) {
            $inAdj = match ($index) {
                0 => 3,
                1 => 2,
                2 => 4,
                default => -2,
            };
            $outAdj = match ($index) {
                0 => -1,
                1 => 4,
                2 => -2,
                default => 2,
            };

            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], $inAdj),
                'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], $outAdj),
            ];
        }

        return $rows;
    }

    protected function splitIncompleteOutWithoutInRows(WorkScheduleTemplate $template): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        if ($baseline === []) {
            return [];
        }

        $rows = [];
        foreach ($baseline as $index => $row) {
            if ($index === 0) {
                $rows[] = [
                    'label' => $row['label'],
                    'scheduled_in' => $row['scheduled_in'],
                    'scheduled_out' => $row['scheduled_out'],
                    'actual_in' => null,
                    'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], -5),
                ];

                continue;
            }

            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => null,
                'actual_out' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string, actual_in: ?string, actual_out: ?string}>
     */
    protected function standardFilledSegmentRowsFromTemplate(WorkScheduleTemplate $template): array
    {
        $baseline = $this->scheduledSegmentsFromTemplate($template);
        $rows = [];
        foreach ($baseline as $row) {
            $rows[] = [
                'label' => $row['label'],
                'scheduled_in' => $row['scheduled_in'],
                'scheduled_out' => $row['scheduled_out'],
                'actual_in' => $this->normalizeHmThenOffsetMinutes($row['scheduled_in'], -2),
                'actual_out' => $this->normalizeHmThenOffsetMinutes($row['scheduled_out'], 2),
            ];
        }

        return $rows;
    }

    /**
     * Completed day identical to {@see standardFilledSegmentRowsFromTemplate} except last session clocks out later (work past scheduled OT window end).
     *
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string, actual_in: ?string, actual_out: ?string}>
     */
    protected function splitCompleteExtendedOvertimeTailRows(
        WorkScheduleTemplate $template,
        int $extraLastSessionOutMinutes = 32,
    ): array {
        $rows = $this->standardFilledSegmentRowsFromTemplate($template);
        if ($rows === []) {
            return [];
        }

        $lastIdx = array_key_last($rows);
        if ($lastIdx === null) {
            return $rows;
        }

        $last = $rows[$lastIdx];
        $rows[$lastIdx] = [
            ...$last,
            'actual_out' => $this->normalizeHmThenOffsetMinutes((string) $last['scheduled_out'], $extraLastSessionOutMinutes),
        ];

        return $rows;
    }

    /**
     * @return list<array{label: string, scheduled_in: string, scheduled_out: string}>
     */
    protected function scheduledSegmentsFromTemplate(WorkScheduleTemplate $template): array
    {
        if ($template->clock_pattern !== WorkScheduleClockPattern::SplitSessions) {
            return [];
        }

        $segments = $template->segments;
        if (! is_array($segments) || $segments === []) {
            return [];
        }

        $rows = [];
        foreach (array_values($segments) as $index => $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $timeIn = isset($seg['time_in']) && is_string($seg['time_in']) ? $seg['time_in'] : '09:00';
            $timeOut = isset($seg['time_out']) && is_string($seg['time_out']) ? $seg['time_out'] : '17:00';
            $label = isset($seg['label']) && is_string($seg['label']) && $seg['label'] !== ''
                ? $seg['label']
                : 'Session '.((int) $index + 1);

            $rows[] = [
                'label' => $label,
                'scheduled_in' => $this->normalizeHm($timeIn),
                'scheduled_out' => $this->normalizeHm($timeOut),
            ];
        }

        return $rows;
    }

    protected function normalizeHm(string $raw): string
    {
        $t = trim($raw);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m) !== 1) {
            return strlen($t) === 5 ? $t : '09:00';
        }

        $h = (int) $m[1];
        $mm = $m[2];
        if ($h < 0 || $h > 23) {
            return '09:00';
        }

        return sprintf('%02d:%s', $h, $mm);
    }

    protected function normalizeHmThenOffsetMinutes(string $raw, int $deltaMinutes): string
    {
        $n = $this->normalizeHm($raw);
        if (preg_match('/^(\d{2}):(\d{2})$/', $n, $m) !== 1) {
            return $n;
        }

        $total = (int) $m[1] * 60 + (int) $m[2] + $deltaMinutes;
        $total = (($total % (24 * 60)) + (24 * 60)) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
