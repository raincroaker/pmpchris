<?php

namespace App\Services;

use App\Models\BranchManager;
use App\Models\EmployeeAffiliation;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BranchContextService
{
    public const SESSION_BRANCH_ID = 'selected_branch_id';

    public const SESSION_BRANCH_META = 'selected_branch_meta';

    public function defaultOrganization(): ?Organization
    {
        $code = config('hris.default_organization_code');

        if (is_string($code) && $code !== '') {
            return Organization::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();
        }

        return Organization::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    public function branchUnitTypeId(): ?int
    {
        return UnitType::query()->where('name', 'Branch')->value('id');
    }

    public function canSwitchBranchContext(?User $user): bool
    {
        if ($user === null || ! config('hris.branch_picker_enabled', true)) {
            return false;
        }

        /** @var list<string> $codes */
        $codes = config('hris.picker_role_codes', []);
        if ($user->hasAnyRole($codes)) {
            return true;
        }

        if ($this->isOrgWideUser($user)) {
            return true;
        }

        return $this->hasMultiBranchAffiliations($user);
    }

    public function findSelectableBranchRoot(
        int $branchId,
        ?Organization $organization = null,
        ?User $user = null,
    ): ?OrganizationalUnit {
        $organization ??= $this->defaultOrganization();

        if ($organization === null) {
            return null;
        }

        return $this->selectableRootQuery($organization, $user)
            ->whereKey($branchId)
            ->first();
    }

    public function isValidSessionBranchId(int $branchId, ?User $user = null): bool
    {
        if ($branchId <= 0) {
            return false;
        }

        return $this->findSelectableBranchRoot($branchId, user: $user) !== null;
    }

    /**
     * Default org-chart root when the user has no branch picker session: first Head Office root, else first selectable root by id.
     */
    public function defaultChartRoot(Organization $organization): ?OrganizationalUnit
    {
        $headOfficeTypeId = UnitType::query()->where('name', 'Head Office')->value('id');

        if ($headOfficeTypeId !== null) {
            $headOffice = OrganizationalUnit::query()
                ->where('organization_id', $organization->id)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('unit_type_id', $headOfficeTypeId)
                ->orderBy('id')
                ->first();

            if ($headOffice instanceof OrganizationalUnit) {
                return $headOffice;
            }
        }

        $fallbackRoot = $this->selectableRootQuery($organization)->orderBy('id')->first();

        return $fallbackRoot instanceof OrganizationalUnit ? $fallbackRoot : null;
    }

    /**
     * Selectable org roots: active units with no parent whose unit type is active and `can_be_root`.
     * Order: Head Office type first, then other roots by area id (seed order), then name.
     *
     * @return Collection<int, array{id: int, code: string, name: string, area_name: string|null, group_label: string}>
     */
    public function branchesForPicker(?User $user = null): Collection
    {
        $organization = $this->defaultOrganization();

        if ($organization === null) {
            return collect();
        }

        $orgId = $organization->id;
        $headOfficeTypeId = UnitType::query()->where('name', 'Head Office')->value('id');

        return $this->selectableRootQuery($organization, $user)
            ->with([
                'area' => function ($query) use ($orgId): void {
                    $query->select(['id', 'name', 'organization_id'])
                        ->where('organization_id', $orgId);
                },
                'unitType' => function ($query): void {
                    $query->select(['id', 'name']);
                },
            ])
            ->get(['id', 'code', 'name', 'area_id', 'unit_type_id'])
            ->sort(function (OrganizationalUnit $a, OrganizationalUnit $b) use ($headOfficeTypeId): int {
                $aHo = $headOfficeTypeId !== null && (int) $a->unit_type_id === (int) $headOfficeTypeId;
                $bHo = $headOfficeTypeId !== null && (int) $b->unit_type_id === (int) $headOfficeTypeId;

                if ($aHo !== $bHo) {
                    return $aHo ? -1 : 1;
                }

                if ($aHo && $bHo) {
                    return strcasecmp((string) $a->name, (string) $b->name);
                }

                $aAreaId = $a->area_id;
                $bAreaId = $b->area_id;

                if ($aAreaId === null && $bAreaId !== null) {
                    return 1;
                }

                if ($aAreaId !== null && $bAreaId === null) {
                    return -1;
                }

                if ($aAreaId !== null && $bAreaId !== null && $aAreaId !== $bAreaId) {
                    return $aAreaId <=> $bAreaId;
                }

                return strcasecmp((string) $a->name, (string) $b->name);
            })
            ->values()
            ->map(function (OrganizationalUnit $unit): array {
                $area = $unit->area;
                $raw = $area?->name;
                $areaName = is_string($raw) && $raw !== '' ? $raw : null;

                $typeRaw = $unit->unitType?->name;
                $typeName = is_string($typeRaw) && $typeRaw !== '' ? $typeRaw : null;

                $groupLabel = $areaName ?? $typeName ?? 'Unassigned';

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'area_name' => $areaName,
                    'group_label' => $groupLabel,
                ];
            });
    }

    /**
     * @return list<int>
     */
    public function managedBranchRootIdsFor(User $user): array
    {
        $organization = $this->defaultOrganization();
        if ($organization === null) {
            return [];
        }

        $now = now()->toDateTimeString();

        return BranchManager::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('rootUnit', function (Builder $query) use ($organization): void {
                $this->applySelectableRootConstraints($query, $organization);
            })
            ->orderBy('root_unit_id')
            ->pluck('root_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string, area_name: string|null, group_label: string}>
     */
    public function managedBranchesForPicker(User $user): Collection
    {
        $organization = $this->defaultOrganization();
        if ($organization === null) {
            return collect();
        }

        $managedRootIds = $this->managedBranchRootIdsFor($user);
        if ($managedRootIds === []) {
            return collect();
        }

        $orgId = $organization->id;
        $headOfficeTypeId = UnitType::query()->where('name', 'Head Office')->value('id');

        return $this->applySelectableRootConstraints(OrganizationalUnit::query(), $organization)
            ->whereIn('id', $managedRootIds)
            ->with([
                'area' => function ($query) use ($orgId): void {
                    $query->select(['id', 'name', 'organization_id'])
                        ->where('organization_id', $orgId);
                },
                'unitType' => function ($query): void {
                    $query->select(['id', 'name']);
                },
            ])
            ->get(['id', 'code', 'name', 'area_id', 'unit_type_id'])
            ->sort(function (OrganizationalUnit $a, OrganizationalUnit $b) use ($headOfficeTypeId): int {
                $aHo = $headOfficeTypeId !== null && (int) $a->unit_type_id === (int) $headOfficeTypeId;
                $bHo = $headOfficeTypeId !== null && (int) $b->unit_type_id === (int) $headOfficeTypeId;

                if ($aHo !== $bHo) {
                    return $aHo ? -1 : 1;
                }

                if ($aHo && $bHo) {
                    return strcasecmp((string) $a->name, (string) $b->name);
                }

                $aAreaId = $a->area_id;
                $bAreaId = $b->area_id;

                if ($aAreaId === null && $bAreaId !== null) {
                    return 1;
                }

                if ($aAreaId !== null && $bAreaId === null) {
                    return -1;
                }

                if ($aAreaId !== null && $bAreaId !== null && $aAreaId !== $bAreaId) {
                    return $aAreaId <=> $bAreaId;
                }

                return strcasecmp((string) $a->name, (string) $b->name);
            })
            ->values()
            ->map(function (OrganizationalUnit $unit): array {
                $area = $unit->area;
                $raw = $area?->name;
                $areaName = is_string($raw) && $raw !== '' ? $raw : null;

                $typeRaw = $unit->unitType?->name;
                $typeName = is_string($typeRaw) && $typeRaw !== '' ? $typeRaw : null;

                $groupLabel = $areaName ?? $typeName ?? 'Unassigned';

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'area_name' => $areaName,
                    'group_label' => $groupLabel,
                ];
            });
    }

    /**
     * @return list<int>
     */
    public function affiliatedBranchRootIdsFor(User $user): array
    {
        $organization = $this->defaultOrganization();
        if ($organization === null) {
            return [];
        }

        if ($user->employee_id === null) {
            return [];
        }

        $today = now()->toDateString();

        return EmployeeAffiliation::query()
            ->where('employee_id', $user->employee_id)
            ->where('organization_id', $organization->id)
            ->whereNotNull('root_unit_id')
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->orderBy('root_unit_id')
            ->pluck('root_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function isOrgWideUser(User $user): bool
    {
        $organization = $this->defaultOrganization();
        if ($organization === null || $user->employee_id === null) {
            return false;
        }

        $today = now()->toDateString();

        return EmployeeAffiliation::query()
            ->where('employee_id', $user->employee_id)
            ->where('organization_id', $organization->id)
            ->whereNull('root_unit_id')
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->exists();
    }

    /**
     * Current workspace branch (session picker or single active affiliation root), matching Inertia `branchContext`.
     *
     * @return array{id: int, code: string, name: string}|null
     */
    public function workspaceBranchContext(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        if (! $this->canSwitchBranchContext($user)) {
            return $this->singleAffiliatedBranchRootContext($user);
        }

        $branchId = (int) $request->session()->get(self::SESSION_BRANCH_ID, 0);

        if ($branchId <= 0) {
            return null;
        }

        if (! $this->isValidSessionBranchId($branchId, $user)) {
            return null;
        }

        $meta = $this->sessionBranchMeta($request->session());

        if ($meta !== null) {
            return [
                'id' => $branchId,
                'code' => $meta['code'],
                'name' => $meta['name'],
            ];
        }

        $unit = OrganizationalUnit::query()->find($branchId);

        if ($unit === null) {
            return null;
        }

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string}|null
     */
    public function singleAffiliatedBranchRootContext(User $user): ?array
    {
        if ($user->employee_id === null) {
            return null;
        }

        $organization = $this->defaultOrganization();
        if ($organization === null) {
            return null;
        }

        $today = now()->toDateString();
        $activeRootAffiliations = EmployeeAffiliation::query()
            ->where('employee_id', $user->employee_id)
            ->where('organization_id', $organization->id)
            ->whereNotNull('root_unit_id', 'and')
            ->whereNull('deleted_at', 'and', false)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date', 'and', false)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->with('rootUnit:id,code,name')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($activeRootAffiliations->count() !== 1) {
            return null;
        }

        $root = $activeRootAffiliations->first()?->rootUnit;
        if ($root === null) {
            return null;
        }

        return [
            'id' => (int) $root->id,
            'code' => (string) $root->code,
            'name' => (string) $root->name,
        ];
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public function sessionBranchMeta(\Illuminate\Contracts\Session\Session $session): ?array
    {
        $meta = $session->get(self::SESSION_BRANCH_META);

        if (! is_array($meta)) {
            return null;
        }

        if (! isset($meta['code'], $meta['name']) || ! is_string($meta['code']) || ! is_string($meta['name'])) {
            return null;
        }

        return [
            'code' => $meta['code'],
            'name' => $meta['name'],
        ];
    }

    /**
     * @param  Builder<OrganizationalUnit>  $query
     */
    private function applySelectableRootConstraints(Builder $query, Organization $organization): Builder
    {
        return $query
            ->where('organization_id', $organization->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereHas('unitType', function (Builder $q): void {
                $q->where('can_be_root', true)->where('is_active', true);
            });
    }

    /**
     * @return Builder<OrganizationalUnit>
     */
    private function selectableRootQuery(Organization $organization, ?User $user = null): Builder
    {
        $query = $this->applySelectableRootConstraints(OrganizationalUnit::query(), $organization);

        if ($user === null) {
            return $query;
        }

        if (! $this->canSwitchBranchContext($user)) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        if ($this->hasFullBranchAccess($user) || $this->isOrgWideUser($user)) {
            return $query;
        }

        if ($this->isManagerScoped($user)) {
            $pickerRootIds = array_values(array_unique(array_merge(
                $this->managedBranchRootIdsFor($user),
                $this->affiliatedBranchRootIdsFor($user),
            )));

            return $this->applyRootIdScope($query, $pickerRootIds);
        }

        if ($this->hasMultiBranchAffiliations($user)) {
            return $this->applyRootIdScope($query, $this->affiliatedBranchRootIdsFor($user));
        }

        $query->whereRaw('1 = 0');

        return $query;
    }

    private function hasFullBranchAccess(User $user): bool
    {
        return $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
    }

    private function isManagerScoped(User $user): bool
    {
        return $user->hasRole(Role::CODE_HR_MANAGER) && ! $this->hasFullBranchAccess($user);
    }

    private function hasMultiBranchAffiliations(User $user): bool
    {
        return count($this->affiliatedBranchRootIdsFor($user)) >= 2;
    }

    /**
     * @param  list<int>  $rootIds
     * @return Builder<OrganizationalUnit>
     */
    private function applyRootIdScope(Builder $query, array $rootIds): Builder
    {
        if ($rootIds === []) {
            $query->whereRaw('1 = 0');

            return $query;
        }

        return $query->whereIn('id', $rootIds);
    }
}
