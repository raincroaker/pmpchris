<?php

namespace App\Services;

use App\Http\Requests\UpdateEmployeeEmploymentDatesRequest;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\EmployeePosition;
use Carbon\Carbon;

final class EmploymentHireAdjustmentBoundary
{
    /**
     * Latest calendar date hire may be set to (inclusive) so it is still on or before every
     * position, affiliation, and org-chart assignment start — aligns with
     * {@see UpdateEmployeeEmploymentDatesRequest::assertHireVsAssignmentStarts()}.
     *
     * @param  list<int|numeric-string>  $employmentIds
     * @return array<int, string|null> employment id => Y-m-d, or null when no related spans exist
     */
    public static function latestPermittedHireDatesByEmploymentIds(array $employmentIds): array
    {
        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $employmentIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, string|null> $result */
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = null;
        }

        $consume = static function ($group) use (&$result): void {
            foreach ($group as $employmentId => $minStart) {
                if ($minStart === null || $minStart === '') {
                    continue;
                }
                $eid = (int) $employmentId;
                $iso = Carbon::parse((string) $minStart)->toDateString();
                if (! array_key_exists($eid, $result)) {
                    continue;
                }
                if ($result[$eid] === null || $iso < $result[$eid]) {
                    $result[$eid] = $iso;
                }
            }
        };

        $consume(EmployeeAffiliation::query()
            ->whereIn('employee_employment_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('employee_employment_id')
            ->selectRaw('employee_employment_id, MIN(start_date) as min_start')
            ->pluck('min_start', 'employee_employment_id'));

        $consume(EmployeePosition::query()
            ->whereIn('employee_employment_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('employee_employment_id')
            ->selectRaw('employee_employment_id, MIN(start_date) as min_start')
            ->pluck('min_start', 'employee_employment_id'));

        $consume(EmployeeAssignment::query()
            ->whereIn('employee_employment_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('employee_employment_id')
            ->selectRaw('employee_employment_id, MIN(start_date) as min_start')
            ->pluck('min_start', 'employee_employment_id'));

        return $result;
    }

    public static function latestPermittedHireDateIsoForEmployment(int $employmentId): ?string
    {
        $map = self::latestPermittedHireDatesByEmploymentIds([$employmentId]);

        return $map[$employmentId] ?? null;
    }
}
