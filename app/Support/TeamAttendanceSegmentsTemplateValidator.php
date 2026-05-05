<?php

namespace App\Support;

use App\Enums\WorkScheduleClockPattern;
use App\Models\WorkScheduleTemplate;
use Closure;

/**
 * Ensures submitted Team Attendance segment rows mirror the structural schedule on {@see WorkScheduleTemplate}.
 */
final class TeamAttendanceSegmentsTemplateValidator
{
    /**
     * @param  list<mixed>|array<mixed>  $segments
     * @param  Closure(string): void  $fail
     */
    public static function validate(array $segments, WorkScheduleTemplate $template, Closure $fail): void
    {
        if ($template->clock_pattern === WorkScheduleClockPattern::SinglePair) {
            self::validateSinglePair($segments, $template, $fail);

            return;
        }

        if ($template->clock_pattern === WorkScheduleClockPattern::SplitSessions) {
            self::validateSplitSessions($segments, $template, $fail);
        }
    }

    /**
     * @param  list<mixed>|array<mixed>  $segments
     * @param  Closure(string): void  $fail
     */
    private static function validateSinglePair(array $segments, WorkScheduleTemplate $template, Closure $fail): void
    {
        if (count($segments) !== 1) {
            return;
        }

        $row = $segments[0];
        if (! is_array($row)) {
            $fail('Scheduled times must match the employee’s work schedule template.');

            return;
        }

        $expIn = AttendanceScheduleHm::normalize((string) $template->time_in);
        $expOut = AttendanceScheduleHm::normalize((string) $template->time_out);
        $gotIn = AttendanceScheduleHm::normalize((string) ($row['scheduled_in'] ?? ''));
        $gotOut = AttendanceScheduleHm::normalize((string) ($row['scheduled_out'] ?? ''));

        if ($gotIn !== $expIn || $gotOut !== $expOut) {
            $fail('Scheduled times must match the employee’s work schedule template.');
        }
    }

    /**
     * @param  list<mixed>|array<mixed>  $segments
     * @param  Closure(string): void  $fail
     */
    private static function validateSplitSessions(array $segments, WorkScheduleTemplate $template, Closure $fail): void
    {
        $rawSegs = $template->segments;
        if (! is_array($rawSegs)) {
            $fail('This work schedule template does not define two split sessions.');

            return;
        }

        $tmplSegs = array_values($rawSegs);
        if (count($tmplSegs) !== 2) {
            $fail('This work schedule template does not define two split sessions.');

            return;
        }

        if (count($segments) !== 2) {
            return;
        }

        foreach ([0, 1] as $i) {
            $tSeg = $tmplSegs[$i];
            if (! is_array($tSeg)) {
                $fail('This work schedule template does not define two split sessions.');

                return;
            }

            if (! empty($tSeg['is_overnight'])) {
                $fail('Split sessions that cross midnight are not supported for team attendance.');

                return;
            }

            $row = $segments[$i] ?? null;
            if (! is_array($row)) {
                $fail('Scheduled times must match the employee’s work schedule template.');

                continue;
            }

            $ti = isset($tSeg['time_in']) && is_string($tSeg['time_in']) ? $tSeg['time_in'] : '';
            $to = isset($tSeg['time_out']) && is_string($tSeg['time_out']) ? $tSeg['time_out'] : '';
            if ($ti === '' || $to === '') {
                $fail('This work schedule template does not define two split sessions.');

                return;
            }

            $expIn = AttendanceScheduleHm::normalize($ti);
            $expOut = AttendanceScheduleHm::normalize($to);
            $gotIn = AttendanceScheduleHm::normalize((string) ($row['scheduled_in'] ?? ''));
            $gotOut = AttendanceScheduleHm::normalize((string) ($row['scheduled_out'] ?? ''));

            if ($gotIn !== $expIn || $gotOut !== $expOut) {
                $fail('Scheduled times must match the employee’s work schedule template.');
            }
        }
    }
}
