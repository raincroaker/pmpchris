<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreservesOrganizationChartEditListingInputs;
use App\Models\UnitType;
use App\Services\OrganizationChartEditStructureMutationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationChartUnitTypeRequest extends FormRequest
{
    use PreservesOrganizationChartEditListingInputs;

    public function authorize(): bool
    {
        return $this->user()?->canEditOrganizationStructure() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var UnitType|null $unitType */
        $unitType = $this->route('unitType');
        $palette = array_map(
            static fn (string $h): string => strtolower($h),
            OrganizationChartEditStructureMutationService::allowedUnitTypePaletteHexNormalized(),
        );

        return array_merge([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('unit_types', 'name')->ignore($unitType?->id),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['required', 'string', Rule::in($palette)],
            'can_be_root' => ['required', 'boolean'],
            'parent_type_ids' => ['present', 'array'],
            'parent_type_ids.*' => ['integer', 'distinct', Rule::exists('unit_types', 'id')->where('is_active', true)],
        ], $this->preservationFieldRules());
    }

    /**
     * @return array{name: string, description: string, color: string, can_be_root: bool, parent_type_ids: list<int>}
     */
    public function unitTypePayload(): array
    {
        /** @var list<int> $parentIds */
        $parentIds = array_map(
            static fn (mixed $id): int => (int) $id,
            $this->input('parent_type_ids', []),
        );

        return [
            'name' => (string) $this->input('name'),
            'description' => (string) $this->input('description', ''),
            'color' => strtolower((string) $this->input('color')),
            'can_be_root' => $this->boolean('can_be_root'),
            'parent_type_ids' => $parentIds,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'description' => trim((string) $this->input('description', '')),
            'color' => strtolower(trim((string) $this->input('color', ''))),
        ]);
    }
}
