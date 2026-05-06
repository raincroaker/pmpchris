<?php

namespace App\Support;

use App\Http\Controllers\ScheduleAssignmentController;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeePosition;
use App\Models\OrganizationalUnit;

final class TeamHrEmployeeDirectoryExtras
{
    /**
     * Active employee positions for directory-style UI (aligned with {@see ScheduleAssignmentController::mapEmployeeRow()}).
     *
     * @return list<array{id: int, code: string, title: string, is_primary: bool}>
     */
    public static function mapActivePositions(Employee $employee): array
    {
        return $employee->positions
            ->filter(fn (EmployeePosition $ep): bool => $ep->position !== null)
            ->sort(function (EmployeePosition $a, EmployeePosition $b): int {
                if ($a->is_primary !== $b->is_primary) {
                    return $b->is_primary <=> $a->is_primary;
                }

                return strcmp($a->position->title, $b->position->title);
            })
            ->values()
            ->map(fn (EmployeePosition $ep): array => [
                'id' => (int) $ep->position->id,
                'code' => (string) $ep->position->code,
                'title' => (string) $ep->position->title,
                'is_primary' => (bool) $ep->is_primary,
            ])
            ->all();
    }

    /**
     * Hex color from the unit’s {@see OrganizationalUnit::$unitType} for placement badge borders.
     */
    public static function unitTypeHexColor(?OrganizationalUnit $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        $unitType = $unit->unitType;
        if ($unitType === null || ! filled($unitType->color)) {
            return null;
        }

        return (string) $unitType->color;
    }

    /**
     * Whether the employee has a current assignment marking this org unit as primary.
     */
    public static function primaryPlacementOnUnit(Employee $employee, ?int $organizationalUnitId): bool
    {
        if ($organizationalUnitId === null) {
            return false;
        }

        $assignment = $employee->assignments->firstWhere('organizational_unit_id', $organizationalUnitId);

        return $assignment !== null && (bool) $assignment->is_primary;
    }

    /**
     * Nested eager load on {@see Employee} for team / my HR leave & overtime row presenters
     * (active positions + current assignments; mirrors schedule index expectations).
     *
     * @return \Closure(object): void
     */
    public static function eagerLoadEmployeeForPresenters(string $today): \Closure
    {
        return function ($query) use ($today): void {
            $query->with([
                'user:id,employee_id,avatar_path',
                'positions' => function ($q) use ($today): void {
                    $q->whereNull('deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with(['position' => function ($q3): void {
                            $q3->select(['positions.id', 'positions.code', 'positions.title']);
                        }])
                        ->orderByDesc('is_primary')
                        ->orderBy('id');
                },
                'assignments' => function ($q) use ($today): void {
                    $q->whereNull('deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                },
            ]);
        };
    }

    /**
     * Same as {@see eagerLoadEmployeeForPresenters} plus {@see EmployeeAssignment::$organizationalUnit}
     * for team attendance rows when {@see EmployeeAttendanceDay::$organizational_unit_id} is null.
     *
     * @return \Closure(object): void
     */
    public static function eagerLoadEmployeeForAttendancePresenters(string $today): \Closure
    {
        return function ($query) use ($today): void {
            $query->with([
                'user:id,employee_id,avatar_path',
                'positions' => function ($q) use ($today): void {
                    $q->whereNull('deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with(['position' => function ($q3): void {
                            $q3->select(['positions.id', 'positions.code', 'positions.title']);
                        }])
                        ->orderByDesc('is_primary')
                        ->orderBy('id');
                },
                'assignments' => function ($q) use ($today): void {
                    $q->whereNull('deleted_at')
                        ->where(function ($q2) use ($today): void {
                            $q2->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        })
                        ->with([
                            'organizationalUnit' => function ($ou): void {
                                $ou->select([
                                    'organizational_units.id',
                                    'organizational_units.code',
                                    'organizational_units.name',
                                    'organizational_units.organization_id',
                                    'organizational_units.unit_type_id',
                                ])->with(['unitType:id,name,color']);
                            },
                        ]);
                },
            ]);
        };
    }
}
