<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateOrganizationChartUnitRequest extends FormRequest
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
            'chart_scope' => ['nullable', 'in:branch,all', 'not_in:all'],
            'chart_branch_id' => ['required', 'integer', 'min:1'],
            'node_id' => ['required', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'node_id' => trim((string) $this->input('node_id', '')),
        ]);
    }

    public function chartBranchId(): int
    {
        return (int) $this->integer('chart_branch_id');
    }

    public function nodeId(): string
    {
        return trim((string) $this->input('node_id', ''));
    }
}
