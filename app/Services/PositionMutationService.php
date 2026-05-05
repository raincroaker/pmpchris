<?php

namespace App\Services;

use App\Models\EmployeePosition;
use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class PositionMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return array{id: int, code: string, title: string, description: string|null, is_active: bool}
     */
    public function createPosition(string $code, string $title, ?string $description = null): array
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'code' => 'Position code is required.',
            ]);
        }

        $availability = $this->checkCodeAvailability($normalizedCode);
        if ($availability['status'] !== 'available') {
            throw ValidationException::withMessages([
                'code' => $availability['message'],
            ]);
        }

        $position = Position::query()->create([
            'organization_id' => $organization->id,
            'code' => $normalizedCode,
            'title' => trim($title),
            'description' => $description !== null ? trim($description) : null,
            'is_active' => true,
        ]);

        return $this->mapPositionPayload($position);
    }

    /**
     * @return array{id: int, code: string, title: string, description: string|null, is_active: bool}
     */
    public function updatePosition(Position $position, string $code, string $title, ?string $description = null): array
    {
        $targetPosition = $this->resolvePositionInDefaultOrganization($position);

        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'code' => 'Position code is required.',
            ]);
        }

        $availability = $this->checkCodeAvailability($normalizedCode, (int) $targetPosition->id);
        if ($availability['status'] !== 'available') {
            throw ValidationException::withMessages([
                'code' => $availability['message'],
            ]);
        }

        $targetPosition->code = $normalizedCode;
        $targetPosition->title = trim($title);
        $targetPosition->description = $description !== null ? trim($description) : null;
        $targetPosition->save();

        return $this->mapPositionPayload($targetPosition);
    }

    /**
     * @return array{id: int, code: string, title: string, description: string|null, is_active: bool}
     */
    public function deactivatePosition(Position $position): array
    {
        $targetPosition = $this->resolvePositionInDefaultOrganization($position);

        if (! $targetPosition->is_active) {
            return $this->mapPositionPayload($targetPosition);
        }

        $targetPosition->is_active = false;
        $targetPosition->save();

        return $this->mapPositionPayload($targetPosition);
    }

    /**
     * @return array{id: int}
     */
    public function deletePosition(Position $position): array
    {
        $position = $this->resolvePositionInDefaultOrganization($position);

        $isReferencedByEmployees = EmployeePosition::withTrashed()
            ->where('position_id', $position->id)
            ->exists();

        if ($isReferencedByEmployees) {
            throw ValidationException::withMessages([
                'position' => 'Cannot delete this position because it is referenced by employee records.',
            ]);
        }

        $deletedPositionId = (int) $position->id;
        $position->delete();

        return [
            'id' => $deletedPositionId,
        ];
    }

    /**
     * @return array{status: 'idle'|'available'|'taken'|'invalid', message: string}
     */
    public function checkCodeAvailability(string $code, ?int $ignorePositionId = null): array
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            return [
                'status' => 'idle',
                'message' => '',
            ];
        }

        $query = Position::query()
            ->where('organization_id', $organization->id)
            ->where('code', $normalizedCode);

        if ($ignorePositionId !== null) {
            $query->whereKeyNot($ignorePositionId);
        }

        if ($query->exists()) {
            return [
                'status' => 'taken',
                'message' => 'Position code is already in use for this organization.',
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Position code is available.',
        ];
    }

    private function resolvePositionInDefaultOrganization(Position $position): Position
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        if ((int) $position->organization_id !== (int) $organization->id) {
            throw (new ModelNotFoundException)->setModel(Position::class);
        }

        return $position;
    }

    /**
     * @return array{id: int, code: string, title: string, description: string|null, is_active: bool}
     */
    private function mapPositionPayload(Position $position): array
    {
        return [
            'id' => (int) $position->id,
            'code' => (string) $position->code,
            'title' => (string) $position->title,
            'description' => is_string($position->description) ? $position->description : null,
            'is_active' => (bool) $position->is_active,
        ];
    }
}
