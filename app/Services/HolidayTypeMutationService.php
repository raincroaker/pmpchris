<?php

namespace App\Services;

use App\Models\HolidayType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HolidayTypeMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HolidayViewDataService $holidayViewData,
    ) {}

    public function findManagedTypeBySlug(string $slug): HolidayType
    {
        $organization = $this->resolveDefaultOrganization();
        $type = HolidayType::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->first();

        if (! $type instanceof HolidayType) {
            throw (new ModelNotFoundException)->setModel(HolidayType::class);
        }

        return $type;
    }

    /**
     * @return array<string, mixed>
     */
    public function createCustomType(
        string $name,
        string $colorKey,
        string $payPolicy,
        ?string $customMultiplier,
        ?string $premiumNote,
        ?int $actorUserId,
    ): array {
        $organization = $this->resolveDefaultOrganization();
        $normalizedName = trim($name);
        $slug = $this->uniqueSlugForOrganization((int) $organization->id, $normalizedName);

        $type = HolidayType::query()->create([
            'organization_id' => $organization->id,
            'slug' => $slug,
            'name' => $normalizedName,
            'kind' => 'custom',
            'color_key' => $this->normalizeColorKey($colorKey),
            'pay_policy' => $payPolicy,
            'custom_multiplier' => $this->normalizedCustomMultiplier($payPolicy, $customMultiplier),
            'premium_note' => $premiumNote,
            'created_by_user_id' => $actorUserId,
            'updated_by_user_id' => $actorUserId,
        ]);

        return $this->holidayViewData->serializeHolidayType($type);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateType(
        HolidayType $type,
        ?string $name,
        string $colorKey,
        string $payPolicy,
        ?string $customMultiplier,
        ?string $premiumNote,
        ?int $actorUserId,
    ): array {
        $target = $this->resolveTypeInDefaultOrganization($type);

        if ($target->kind === 'builtin') {
            $target->color_key = $this->normalizeColorKey($colorKey);
            $target->pay_policy = $payPolicy;
            $target->custom_multiplier = $this->normalizedCustomMultiplier($payPolicy, $customMultiplier);
            $target->premium_note = $premiumNote;
            $target->updated_by_user_id = $actorUserId;
            $target->save();

            return $this->holidayViewData->serializeHolidayType($target->fresh());
        }

        if ($name === null || trim($name) === '') {
            throw ValidationException::withMessages([
                'name' => 'Holiday type name is required.',
            ]);
        }

        $normalizedName = trim($name);
        $slug = $this->uniqueSlugForOrganization((int) $target->organization_id, $normalizedName, (int) $target->id);

        $target->name = $normalizedName;
        $target->slug = $slug;
        $target->color_key = $this->normalizeColorKey($colorKey);
        $target->pay_policy = $payPolicy;
        $target->custom_multiplier = $this->normalizedCustomMultiplier($payPolicy, $customMultiplier);
        $target->premium_note = $premiumNote;
        $target->updated_by_user_id = $actorUserId;
        $target->save();

        return $this->holidayViewData->serializeHolidayType($target->fresh());
    }

    /**
     * @return array{id: string}
     */
    public function deleteType(HolidayType $type): array
    {
        $target = $this->resolveTypeInDefaultOrganization($type);

        if ($target->kind !== 'custom') {
            throw ValidationException::withMessages([
                'holiday_type' => 'Built-in holiday types cannot be deleted.',
            ]);
        }

        $isReferenced = OrganizationHoliday::query()
            ->where('holiday_type_id', $target->id)
            ->exists();

        if ($isReferenced) {
            throw ValidationException::withMessages([
                'holiday_type' => 'Cannot delete this type because it is used by organization holidays.',
            ]);
        }

        $slug = (string) $target->slug;
        $target->deleteOrFail();

        return [
            'id' => $slug,
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

    private function resolveTypeInDefaultOrganization(HolidayType $type): HolidayType
    {
        $organization = $this->resolveDefaultOrganization();
        if ((int) $type->organization_id !== (int) $organization->id) {
            throw (new ModelNotFoundException)->setModel(HolidayType::class);
        }

        return $type;
    }

    private function uniqueSlugForOrganization(int $organizationId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'holiday-type';
        }

        $slug = $base;
        $suffix = 1;

        while (HolidayType::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function normalizeColorKey(string $colorKey): string
    {
        $normalized = strtolower(trim($colorKey));
        $allowed = ['cyan', 'amber', 'lime', 'rose', 'violet', 'sky'];

        return in_array($normalized, $allowed, true) ? $normalized : 'sky';
    }

    private function normalizedCustomMultiplier(string $payPolicy, ?string $customMultiplier): ?string
    {
        if ($payPolicy !== 'Custom Multiplier') {
            return null;
        }

        $trimmed = trim((string) $customMultiplier);

        return $trimmed === '' ? null : $trimmed;
    }
}
