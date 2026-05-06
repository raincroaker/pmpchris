<?php

namespace App\Support;

use App\Http\Controllers\SearchTeamHrFormEmployeesController;
use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\Role;
use App\Models\User;

final class TeamHrFormEligibleEmployeeResolver
{
    /**
     * Matches {@see SearchTeamHrFormEmployeesController} visibility: active employment, branch affiliation
     * scope with optional org-wide root, and assignment on {@code $unitId}.
     *
     * @param  list<string>|array<int|string, mixed>  $with
     */
    public static function firstOrNull(
        ?User $actingUser,
        int $employeeId,
        int $organizationId,
        int $branchRootId,
        int $unitId,
        array $with = [],
    ): ?Employee {
        $today = now()->toDateString();

        $canIncludeOrgWide = $actingUser?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;

        $query = Employee::query()
            ->whereKey($employeeId)
            ->whereNull('deleted_at')
            ->whereHas('employments', function ($query): void {
                $query->whereNull('deleted_at')
                    ->where('is_current', true)
                    ->where('employment_status', EmployeeEmployment::STATUS_ACTIVE);
            })
            ->where(function ($affiliationScope) use ($organizationId, $branchRootId, $today, $canIncludeOrgWide): void {
                $affiliationScope->whereHas('affiliations', function ($query) use ($organizationId, $branchRootId, $today): void {
                    $query->where('organization_id', $organizationId)
                        ->where('root_unit_id', $branchRootId)
                        ->whereNull('deleted_at')
                        ->where(function ($scoped) use ($today): void {
                            $scoped->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                });

                if (! $canIncludeOrgWide) {
                    return;
                }

                $affiliationScope->orWhereHas('affiliations', function ($query) use ($organizationId, $today): void {
                    $query->where('organization_id', $organizationId)
                        ->whereNull('root_unit_id')
                        ->whereNull('deleted_at')
                        ->where(function ($scoped) use ($today): void {
                            $scoped->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                });
            })
            ->whereHas('assignments', function ($query) use ($unitId, $today): void {
                $query->where('organizational_unit_id', $unitId)
                    ->whereNull('deleted_at')
                    ->where(function ($dateScope) use ($today): void {
                        $dateScope->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    });
            });

        if ($with !== []) {
            $query->with($with);
        }

        return $query->first();
    }
}
