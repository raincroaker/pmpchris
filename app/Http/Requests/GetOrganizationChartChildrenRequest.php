<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetOrganizationChartChildrenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_unit_id' => ['required', 'integer', 'min:1'],
            'chart_scope' => ['nullable', 'in:organization'],
        ];
    }

    public function parentUnitId(): int
    {
        return (int) $this->integer('parent_unit_id');
    }

    public function chartScope(): string
    {
        return strtolower(trim((string) $this->input('chart_scope', 'organization')));
    }
}
