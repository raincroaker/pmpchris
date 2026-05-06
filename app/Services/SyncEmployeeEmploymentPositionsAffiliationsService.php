<?php

namespace App\Services;

use App\Models\EmployeeAffiliation;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use Illuminate\Support\Facades\DB;

class SyncEmployeeEmploymentPositionsAffiliationsService
{
    /**
     * @param  list<array<string, mixed>>  $positions
     * @param  list<array<string, mixed>>  $affiliations
     */
    public function sync(EmployeeEmployment $employment, array $positions, array $affiliations): void
    {
        DB::transaction(function () use ($employment, $positions, $affiliations): void {
            $this->syncPositions($employment, $positions);
            $this->syncAffiliations($employment, $affiliations);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncPositions(EmployeeEmployment $employment, array $rows): void
    {
        $incomingIds = collect($rows)->pluck('id')->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id): int => (int) $id)->all();

        EmployeePosition::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->when(
                $incomingIds !== [],
                fn ($query) => $query->whereNotIn('id', $incomingIds),
                fn ($query) => $query,
            )
            ->get()
            ->each(function (EmployeePosition $row): void {
                $row->delete();
            });

        foreach ($rows as $row) {
            $positionId = (int) $row['position_id'];
            $startDate = (string) $row['start_date'];
            $endRaw = $row['end_date'] ?? null;
            $endDate = is_string($endRaw) && $endRaw !== '' ? $endRaw : null;
            $isPrimary = (bool) ($row['is_primary'] ?? false);

            if (isset($row['id']) && is_numeric($row['id'])) {
                $existing = EmployeePosition::query()
                    ->whereKey((int) $row['id'])
                    ->where('employee_employment_id', $employment->getKey())
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill([
                        'employee_id' => $employment->employee_id,
                        'position_id' => $positionId,
                        'is_primary' => $isPrimary,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ])->save();

                    continue;
                }
            }

            EmployeePosition::query()->create([
                'employee_id' => $employment->employee_id,
                'employee_employment_id' => $employment->getKey(),
                'position_id' => $positionId,
                'is_primary' => $isPrimary,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncAffiliations(EmployeeEmployment $employment, array $rows): void
    {
        $incomingIds = collect($rows)->pluck('id')->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id): int => (int) $id)->all();

        EmployeeAffiliation::query()
            ->where('employee_employment_id', $employment->getKey())
            ->whereNull('deleted_at')
            ->when(
                $incomingIds !== [],
                fn ($query) => $query->whereNotIn('id', $incomingIds),
                fn ($query) => $query,
            )
            ->get()
            ->each(function (EmployeeAffiliation $row): void {
                $row->delete();
            });

        $organization = app(BranchContextService::class)->defaultOrganization();

        foreach ($rows as $row) {
            $rootRaw = $row['root_unit_id'] ?? null;
            $rootUnitId = ($rootRaw === null || $rootRaw === '')
                ? null
                : (int) $rootRaw;
            $startDate = (string) $row['start_date'];
            $endRaw = $row['end_date'] ?? null;
            $endDate = is_string($endRaw) && $endRaw !== '' ? $endRaw : null;
            $isPrimary = (bool) ($row['is_primary'] ?? false);

            if ($rootUnitId === null && $organization === null) {
                continue;
            }

            if (isset($row['id']) && is_numeric($row['id'])) {
                $existing = EmployeeAffiliation::query()
                    ->whereKey((int) $row['id'])
                    ->where('employee_employment_id', $employment->getKey())
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing !== null) {
                    if ($rootUnitId === null) {
                        $existing->forceFill([
                            'employee_id' => $employment->employee_id,
                            'organization_id' => $organization?->id,
                            'root_unit_id' => null,
                            'is_primary' => $isPrimary,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ]);
                    } else {
                        $existing->forceFill([
                            'employee_id' => $employment->employee_id,
                            'root_unit_id' => $rootUnitId,
                            'is_primary' => $isPrimary,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ]);
                    }

                    $existing->save();

                    continue;
                }
            }

            if ($rootUnitId === null && $organization !== null) {
                EmployeeAffiliation::query()->create([
                    'employee_id' => $employment->employee_id,
                    'employee_employment_id' => $employment->getKey(),
                    'organization_id' => $organization->id,
                    'root_unit_id' => null,
                    'is_primary' => $isPrimary,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

                continue;
            }

            if ($rootUnitId === null) {
                continue;
            }

            EmployeeAffiliation::query()->create([
                'employee_id' => $employment->employee_id,
                'employee_employment_id' => $employment->getKey(),
                'root_unit_id' => $rootUnitId,
                'is_primary' => $isPrimary,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }
    }
}
