<?php

namespace App\Services;

use App\Models\HolidayType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class OrganizationHolidayMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HolidayViewDataService $holidayViewData,
    ) {}

    /**
     * @param  array<string, mixed>|null  $recurrence
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $startDate,
        string $endDate,
        string $typeSlug,
        ?string $notes,
        ?array $recurrence,
        ?int $actorUserId,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        $type = $this->resolveHolidayTypeBySlug((int) $organization->id, $typeSlug);

        $holiday = OrganizationHoliday::query()->create([
            'organization_id' => $organization->id,
            'holiday_type_id' => $type->id,
            'name' => trim($name),
            'start_date' => Carbon::parse($startDate)->startOfDay(),
            'end_date' => Carbon::parse($endDate)->startOfDay(),
            'notes' => $notes,
            'recurrence' => $recurrence,
            'set_by_user_id' => $actorUserId,
            'last_edited_by_user_id' => $actorUserId,
        ]);

        $holiday->load([
            'holidayType',
            'setByUser:id,name',
            'lastEditedByUser:id,name',
        ]);

        return $this->holidayViewData->serializeOrganizationHoliday($holiday);
    }

    /**
     * @param  array<string, mixed>|null  $recurrence
     * @return array<string, mixed>
     */
    public function update(
        OrganizationHoliday $holiday,
        string $name,
        string $startDate,
        string $endDate,
        string $typeSlug,
        ?string $notes,
        ?array $recurrence,
        ?int $actorUserId,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        $target = $this->resolveHolidayInOrganization($holiday, (int) $organization->id);
        $type = $this->resolveHolidayTypeBySlug((int) $organization->id, $typeSlug);

        $target->holiday_type_id = $type->id;
        $target->name = trim($name);
        $target->start_date = Carbon::parse($startDate)->startOfDay();
        $target->end_date = Carbon::parse($endDate)->startOfDay();
        $target->notes = $notes;
        $target->recurrence = $recurrence;
        $target->last_edited_by_user_id = $actorUserId;
        $target->save();

        $target->load([
            'holidayType',
            'setByUser:id,name',
            'lastEditedByUser:id,name',
        ]);

        return $this->holidayViewData->serializeOrganizationHoliday($target);
    }

    /**
     * @return array{id: int}
     */
    public function delete(OrganizationHoliday $holiday): array
    {
        $organization = $this->resolveDefaultOrganization();
        $target = $this->resolveHolidayInOrganization($holiday, (int) $organization->id);

        $deletedId = (int) $target->id;
        $target->deleteOrFail();

        return [
            'id' => $deletedId,
        ];
    }

    private function resolveDefaultOrganization(): Organization
    {
        $organization = $this->branchContextService->defaultOrganization();
        if (! $organization instanceof Organization) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        return $organization;
    }

    private function resolveHolidayInOrganization(OrganizationHoliday $holiday, int $organizationId): OrganizationHoliday
    {
        if ((int) $holiday->organization_id !== $organizationId) {
            throw (new ModelNotFoundException)->setModel(OrganizationHoliday::class);
        }

        return $holiday;
    }

    private function resolveHolidayTypeBySlug(int $organizationId, string $slug): HolidayType
    {
        $type = HolidayType::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->first();

        if (! $type instanceof HolidayType) {
            throw ValidationException::withMessages([
                'type_id' => 'The selected holiday type is invalid for this organization.',
            ]);
        }

        return $type;
    }
}
