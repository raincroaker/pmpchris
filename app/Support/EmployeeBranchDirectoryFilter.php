<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EmployeeBranchDirectoryFilter
{
    /**
     * Restrict employees to those visible under the Employees index visibility rules:
     * branch workspace users see affiliation-matched colleagues; otherwise org-wide eligibility via positions / assignments.
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        int $organizationId,
        ?int $branchRootId,
        string $todayDate,
        bool $includeHistorical = false,
    ): void {
        if ($branchRootId !== null) {
            $query->whereExists(function ($q) use ($organizationId, $branchRootId, $todayDate, $includeHistorical): void {
                $q->selectRaw('1')
                    ->from('employee_affiliations')
                    ->whereColumn('employee_affiliations.employee_id', 'employees.id')
                    ->where('employee_affiliations.organization_id', $organizationId)
                    ->where(function ($q2) use ($branchRootId): void {
                        $q2->where('employee_affiliations.root_unit_id', $branchRootId)
                            ->orWhereNull('employee_affiliations.root_unit_id');
                    })
                    ->whereNull('employee_affiliations.deleted_at')
                    ->when(! $includeHistorical, function ($q3) use ($todayDate): void {
                        $q3->where(function ($q4) use ($todayDate): void {
                            $q4->whereNull('employee_affiliations.end_date')
                                ->orWhereDate('employee_affiliations.end_date', '>=', $todayDate);
                        });
                    });
            });

            return;
        }

        $query->where(function ($outer) use ($organizationId, $todayDate, $includeHistorical): void {
            $outer->whereExists(function ($q) use ($organizationId, $todayDate, $includeHistorical): void {
                $q->selectRaw('1')
                    ->from('employee_positions')
                    ->join('positions', 'positions.id', '=', 'employee_positions.position_id')
                    ->whereColumn('employee_positions.employee_id', 'employees.id')
                    ->where('positions.organization_id', $organizationId)
                    ->whereNull('employee_positions.deleted_at')
                    ->when(! $includeHistorical, function ($q2) use ($todayDate): void {
                        $q2->where(function ($q3) use ($todayDate): void {
                            $q3->whereNull('employee_positions.end_date')
                                ->orWhereDate('employee_positions.end_date', '>=', $todayDate);
                        });
                    });
            })->orWhereExists(function ($q) use ($organizationId, $todayDate, $includeHistorical): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->join('organizational_units', 'organizational_units.id', '=', 'employee_assignments.organizational_unit_id')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->where('organizational_units.organization_id', $organizationId)
                    ->whereNotNull('employee_assignments.organizational_unit_id')
                    ->whereNull('employee_assignments.deleted_at')
                    ->when(! $includeHistorical, function ($q2) use ($todayDate): void {
                        $q2->where(function ($q3) use ($todayDate): void {
                            $q3->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $todayDate);
                        });
                    });
            })->orWhereExists(function ($q) use ($organizationId, $todayDate, $includeHistorical): void {
                $q->selectRaw('1')
                    ->from('employee_assignments')
                    ->whereColumn('employee_assignments.employee_id', 'employees.id')
                    ->where('employee_assignments.organization_id', $organizationId)
                    ->whereNull('employee_assignments.organizational_unit_id')
                    ->whereNull('employee_assignments.deleted_at')
                    ->when(! $includeHistorical, function ($q2) use ($todayDate): void {
                        $q2->where(function ($q3) use ($todayDate): void {
                            $q3->whereNull('employee_assignments.end_date')
                                ->orWhereDate('employee_assignments.end_date', '>=', $todayDate);
                        });
                    });
            });
        });
    }
}
