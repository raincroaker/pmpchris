<?php

namespace App\Support;

use App\Enums\WorkScheduleClockPattern;

final class WorkSchedulePayloadPreparer
{
    /**
     * Normalizes labels and mirrors first/last session times for split templates.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function fromValidated(array $validated): array
    {
        $pattern = $validated['clock_pattern'] ?? null;
        if ($pattern instanceof WorkScheduleClockPattern) {
            // Already normalized by Form Request + Rule::enum.
        } elseif (is_string($pattern)) {
            $validated['clock_pattern'] = WorkScheduleClockPattern::from($pattern);
            $pattern = $validated['clock_pattern'];
        }

        if (! $pattern instanceof WorkScheduleClockPattern) {
            return $validated;
        }

        if ($pattern === WorkScheduleClockPattern::SinglePair) {
            $validated['segments'] = null;

            return $validated;
        }

        /** @var list<array{label?: string, time_in: string, time_out: string, is_overnight?: bool}> $segments */
        $segments = $validated['segments'] ?? [];
        foreach ($segments as $idx => &$segment) {
            $segment['label'] = 'Session '.($idx + 1);
        }
        unset($segment);

        $validated['segments'] = $segments;
        $validated['time_in'] = $segments[0]['time_in'];
        $validated['time_out'] = $segments[array_key_last($segments)]['time_out'];
        $validated['is_overnight'] = false;

        return $validated;
    }
}
