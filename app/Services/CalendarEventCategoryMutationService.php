<?php

namespace App\Services;

use App\Models\BranchCalendarEvent;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Organization;
use App\Models\TeamCalendarEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarEventCategoryMutationService
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    /**
     * @return array{id: int, name: string, colorKey: string}
     */
    public function createCategory(string $name, string $colorKey, ?int $actorUserId = null): array
    {
        $organization = $this->resolveDefaultOrganization();
        $normalizedName = trim($name);
        $slug = Str::slug($normalizedName);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Category name is required.',
            ]);
        }

        $alreadyExists = CalendarEventCategory::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'name' => 'Category name already exists for this organization.',
            ]);
        }

        $category = CalendarEventCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => $normalizedName,
            'slug' => $slug,
            'color_key' => trim($colorKey),
            'is_active' => true,
            'created_by_user_id' => $actorUserId,
            'updated_by_user_id' => $actorUserId,
        ]);

        return $this->toPayload($category);
    }

    /**
     * @return array{id: int, name: string, colorKey: string}
     */
    public function updateCategory(
        CalendarEventCategory $category,
        string $name,
        string $colorKey,
        ?int $actorUserId = null,
    ): array {
        $target = $this->resolveCategoryInDefaultOrganization($category);
        $normalizedName = trim($name);
        $slug = Str::slug($normalizedName);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Category name is required.',
            ]);
        }

        $alreadyExists = CalendarEventCategory::query()
            ->where('organization_id', $target->organization_id)
            ->where('slug', $slug)
            ->whereKeyNot($target->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'name' => 'Category name already exists for this organization.',
            ]);
        }

        $target->name = $normalizedName;
        $target->slug = $slug;
        $target->color_key = trim($colorKey);
        $target->updated_by_user_id = $actorUserId;
        $target->save();

        return $this->toPayload($target);
    }

    /**
     * @return array{id: int}
     */
    public function deleteCategory(CalendarEventCategory $category): array
    {
        $target = $this->resolveCategoryInDefaultOrganization($category);

        $isReferenced = CompanyCalendarEvent::query()
            ->where('category_id', $target->id)
            ->exists()
            || BranchCalendarEvent::query()
                ->where('category_id', $target->id)
                ->exists()
            || TeamCalendarEvent::query()
                ->where('category_id', $target->id)
                ->exists();

        if ($isReferenced) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete this category because it is referenced by calendar events.',
            ]);
        }

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

    private function resolveCategoryInDefaultOrganization(CalendarEventCategory $category): CalendarEventCategory
    {
        $organization = $this->resolveDefaultOrganization();
        if ((int) $category->organization_id !== (int) $organization->id) {
            throw (new ModelNotFoundException)->setModel(CalendarEventCategory::class);
        }

        return $category;
    }

    /**
     * @return array{id: int, name: string, colorKey: string}
     */
    private function toPayload(CalendarEventCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'colorKey' => (string) $category->color_key,
        ];
    }
}
