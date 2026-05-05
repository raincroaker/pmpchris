<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrganizationChartEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canEditOrganizationStructure() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,created_at,id'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'root_unit_filter' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ];
    }

    public function search(): string
    {
        return trim((string) $this->input('search', ''));
    }

    public function sort(): string
    {
        $value = (string) $this->input('sort', 'name');

        return in_array($value, ['name', 'created_at', 'id'], true) ? $value : 'name';
    }

    public function direction(): string
    {
        $value = strtolower((string) $this->input('direction', 'asc'));

        return in_array($value, ['asc', 'desc'], true) ? $value : 'asc';
    }

    public function perPage(): int
    {
        $value = (int) $this->integer('per_page', 10);

        return min(max($value, 5), 100);
    }

    public function rootUnitFilter(): ?int
    {
        if (! $this->filled('root_unit_filter')) {
            return null;
        }

        $value = (int) $this->input('root_unit_filter');

        return $value > 0 ? $value : null;
    }
}
