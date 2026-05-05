<?php

namespace App\Services;

use App\Models\Area;
use App\Models\BranchManager;
use App\Models\EmployeeAffiliation;
use App\Models\EmployeeAssignment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\UnitType;
use App\Models\UnitTypeParent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class OrganizationChartUnitMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private OrganizationChartActionAccessService $organizationChartActionAccessService,
    ) {}

    /**
     * @return array{id: int, name: string, code: string, parent_id: int|null, unit_type_id: int}
     */
    public function createUnit(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        string $unitTypeName,
        string $name,
        string $code,
        ?int $areaId = null,
    ): array {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to add units in this branch.');
        }

        $parentUnit = null;
        if (str_starts_with($nodeId, 'unit-')) {
            $nodeUnitId = (int) substr($nodeId, strlen('unit-'));
            $parentUnit = $this->organizationChartActionAccessService->findNodeWithinRootById($chartRoot, $nodeUnitId);
            if (! $parentUnit instanceof OrganizationalUnit) {
                throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
            }

            if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $parentUnit)) {
                throw new AuthorizationException('You are not allowed to add units under this parent.');
            }
        } elseif (str_starts_with($nodeId, 'organization-')) {
            if (! $this->organizationChartActionAccessService->canManageOrganizationNode($user, $chartRoot)) {
                throw new AuthorizationException('You are not allowed to add root units.');
            }
        } else {
            throw ValidationException::withMessages([
                'node_id' => 'Invalid target node.',
            ]);
        }

        $unitType = UnitType::query()
            ->where('name', $unitTypeName)
            ->where('is_active', true)
            ->first();
        if (! $unitType instanceof UnitType) {
            throw ValidationException::withMessages([
                'unit_type_name' => 'Invalid unit type.',
            ]);
        }

        if ($parentUnit === null) {
            if (! $unitType->can_be_root) {
                throw ValidationException::withMessages([
                    'unit_type_name' => 'Selected unit type cannot be added at organization level.',
                ]);
            }

            if ($areaId === null) {
                throw ValidationException::withMessages([
                    'area_id' => 'Area is required when adding a unit under the organization.',
                ]);
            }
        } else {
            $isAllowedTypePair = UnitTypeParent::query()
                ->where('parent_unit_type_id', $parentUnit->unit_type_id)
                ->where('child_unit_type_id', $unitType->id)
                ->where('is_active', true)
                ->exists();

            if (! $isAllowedTypePair) {
                throw ValidationException::withMessages([
                    'unit_type_name' => 'Selected unit type is not allowed under this parent.',
                ]);
            }
        }

        $resolvedAreaId = null;
        if ($areaId !== null) {
            $area = Area::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->whereKey($areaId)
                ->first();
            if (! $area instanceof Area) {
                throw ValidationException::withMessages([
                    'area_id' => 'Selected area is invalid.',
                ]);
            }

            $resolvedAreaId = (int) $area->id;
        }

        $duplicateExists = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->exists();
        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'code' => 'Code is already in use for this organization.',
            ]);
        }

        $unit = OrganizationalUnit::query()->create([
            'code' => $code,
            'name' => $name,
            'unit_type_id' => $unitType->id,
            'organization_id' => $organization->id,
            'parent_id' => $parentUnit?->id,
            'area_id' => $resolvedAreaId,
            'is_active' => true,
        ]);

        return [
            'id' => (int) $unit->id,
            'name' => (string) $unit->name,
            'code' => (string) $unit->code,
            'parent_id' => $unit->parent_id !== null ? (int) $unit->parent_id : null,
            'unit_type_id' => (int) $unit->unit_type_id,
        ];
    }

    /**
     * @return array{status: 'idle'|'available'|'taken'|'invalid', message: string}
     */
    public function checkCodeAvailability(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        string $code,
        ?int $ignoreUnitId = null,
    ): array {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            return [
                'status' => 'idle',
                'message' => '',
            ];
        }

        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to add units in this branch.');
        }

        if (str_starts_with($nodeId, 'organization-')) {
            if (! $this->organizationChartActionAccessService->canManageOrganizationNode($user, $chartRoot)) {
                throw new AuthorizationException('You are not allowed to add root units.');
            }
        } elseif (str_starts_with($nodeId, 'unit-')) {
            $nodeUnitId = (int) substr($nodeId, strlen('unit-'));
            $node = $this->organizationChartActionAccessService->findNodeWithinRootById($chartRoot, $nodeUnitId);
            if (! $node instanceof OrganizationalUnit) {
                throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
            }

            if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $node)) {
                throw new AuthorizationException('You are not allowed to add units under this parent.');
            }
        } else {
            throw ValidationException::withMessages([
                'node_id' => 'Invalid target node.',
            ]);
        }

        $query = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode]);
        if ($ignoreUnitId !== null) {
            $query->whereKeyNot($ignoreUnitId);
        }

        if ($query->exists()) {
            return [
                'status' => 'taken',
                'message' => 'Code is already in use.',
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Code is available.',
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, parent_id: int|null, unit_type_id: int}
     */
    public function updateUnit(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        OrganizationalUnit $unit,
        string $name,
        string $code,
    ): array {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to edit units in this branch.');
        }

        $expectedUnit = $this->resolveNodeUnitWithinRoot($chartRoot, $nodeId);
        if (! $expectedUnit instanceof OrganizationalUnit || (int) $expectedUnit->id !== (int) $unit->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit does not match selected node.',
            ]);
        }

        if (! $this->organizationChartActionAccessService->canManageNode($user, $chartRoot, $unit)) {
            throw new AuthorizationException('You are not allowed to edit this unit.');
        }

        if ((int) $unit->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit is outside the current organization scope.',
            ]);
        }

        $duplicateExists = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->whereKeyNot($unit->id)
            ->exists();
        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'code' => 'Code is already in use for this organization.',
            ]);
        }

        $unit->name = $name;
        $unit->code = $code;
        $unit->save();

        return [
            'id' => (int) $unit->id,
            'name' => (string) $unit->name,
            'code' => (string) $unit->code,
            'parent_id' => $unit->parent_id !== null ? (int) $unit->parent_id : null,
            'unit_type_id' => (int) $unit->unit_type_id,
            'is_active' => (bool) $unit->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, code: string, parent_id: int|null, unit_type_id: int, is_active: bool}
     */
    public function activateUnit(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        OrganizationalUnit $unit,
    ): array {
        $targetUnit = $this->resolveTargetUnitForMutation($user, $chartBranchId, $nodeId, $unit);

        $targetUnit->is_active = true;
        $targetUnit->save();

        return $this->mapUnitMutationPayload($targetUnit);
    }

    /**
     * @return array{id: int, name: string, code: string, parent_id: int|null, unit_type_id: int, is_active: bool}
     */
    public function deactivateUnit(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        OrganizationalUnit $unit,
    ): array {
        $targetUnit = $this->resolveTargetUnitForMutation($user, $chartBranchId, $nodeId, $unit);

        $hasActiveAssignments = EmployeeAssignment::query()
            ->where('organizational_unit_id', $targetUnit->id)
            ->whereNull('end_date')
            ->exists();

        if ($hasActiveAssignments) {
            throw ValidationException::withMessages([
                'unit' => 'Cannot deactivate this unit while active employee assignments exist.',
            ]);
        }

        $hasActiveChildren = OrganizationalUnit::query()
            ->where('parent_id', $targetUnit->id)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveChildren) {
            throw ValidationException::withMessages([
                'unit' => 'Cannot deactivate this unit while active child units still reference it.',
            ]);
        }

        $targetUnit->is_active = false;
        $targetUnit->save();

        return $this->mapUnitMutationPayload($targetUnit);
    }

    /**
     * @return array{id: int, mode: 'soft_deleted'|'hard_deleted'}
     */
    public function deleteUnit(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        OrganizationalUnit $unit,
    ): array {
        $targetUnit = $this->resolveTargetUnitForMutation($user, $chartBranchId, $nodeId, $unit);

        $hasChildren = OrganizationalUnit::query()
            ->where('parent_id', $targetUnit->id)
            ->exists();

        if ($hasChildren) {
            throw ValidationException::withMessages([
                'unit' => 'Cannot delete this unit while child units still exist.',
            ]);
        }

        $hasAssignmentHistory = EmployeeAssignment::withTrashed()
            ->where('organizational_unit_id', $targetUnit->id)
            ->exists();
        $hasAffiliationHistory = EmployeeAffiliation::withTrashed()
            ->where('root_unit_id', $targetUnit->id)
            ->exists();
        $hasBranchManagerHistory = BranchManager::query()
            ->where('root_unit_id', $targetUnit->id)
            ->exists();

        $hasHistoryReferences = $hasAssignmentHistory || $hasAffiliationHistory || $hasBranchManagerHistory;

        if ($hasHistoryReferences) {
            $targetUnit->is_active = false;
            $targetUnit->save();
            $targetUnit->delete();

            return [
                'id' => (int) $targetUnit->id,
                'mode' => 'soft_deleted',
            ];
        }

        $deletedUnitId = (int) $targetUnit->id;
        $targetUnit->forceDelete();

        return [
            'id' => $deletedUnitId,
            'mode' => 'hard_deleted',
        ];
    }

    private function resolveTargetUnitForMutation(
        ?User $user,
        int $chartBranchId,
        string $nodeId,
        OrganizationalUnit $unit,
    ): OrganizationalUnit {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $chartRoot = $this->branchContextService->findSelectableBranchRoot($chartBranchId, $organization);
        if (! $chartRoot instanceof OrganizationalUnit) {
            throw (new ModelNotFoundException)->setModel(OrganizationalUnit::class);
        }

        if (! $this->organizationChartActionAccessService->canManageChartBranch($user, $chartRoot)) {
            throw new AuthorizationException('You are not allowed to manage units in this branch.');
        }

        if (! str_starts_with($nodeId, 'unit-')) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit does not match selected node.',
            ]);
        }

        $nodeUnitId = (int) substr($nodeId, strlen('unit-'));
        if ($nodeUnitId <= 0 || $nodeUnitId !== (int) $unit->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit does not match selected node.',
            ]);
        }

        if (! $this->isNodeWithinRootIncludingInactive($unit, $chartRoot)) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit is outside the selected branch scope.',
            ]);
        }

        if ((int) $unit->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'node_id' => 'Target unit is outside the current organization scope.',
            ]);
        }

        return $unit;
    }

    /**
     * @return array{id: int, name: string, code: string, parent_id: int|null, unit_type_id: int, is_active: bool}
     */
    private function mapUnitMutationPayload(OrganizationalUnit $unit): array
    {
        return [
            'id' => (int) $unit->id,
            'name' => (string) $unit->name,
            'code' => (string) $unit->code,
            'parent_id' => $unit->parent_id !== null ? (int) $unit->parent_id : null,
            'unit_type_id' => (int) $unit->unit_type_id,
            'is_active' => (bool) $unit->is_active,
        ];
    }

    private function isNodeWithinRootIncludingInactive(OrganizationalUnit $node, OrganizationalUnit $root): bool
    {
        if ((int) $node->id === (int) $root->id) {
            return true;
        }

        $parentId = $node->parent_id;
        while ($parentId !== null) {
            if ((int) $parentId === (int) $root->id) {
                return true;
            }

            $parentId = OrganizationalUnit::withTrashed()
                ->where('organization_id', $root->organization_id)
                ->whereKey($parentId)
                ->value('parent_id');
        }

        return false;
    }

    private function resolveNodeUnitWithinRoot(OrganizationalUnit $chartRoot, string $nodeId): ?OrganizationalUnit
    {
        if (! str_starts_with($nodeId, 'unit-')) {
            return null;
        }

        $nodeUnitId = (int) substr($nodeId, strlen('unit-'));

        return $this->organizationChartActionAccessService->findNodeWithinRootById($chartRoot, $nodeUnitId);
    }
}
