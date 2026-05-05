<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationChartEmployeeAssignmentRequest extends FormRequest
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
            'position_id' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date'],
            'is_head' => ['required', 'boolean'],
            'is_primary' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'node_id' => trim((string) $this->input('node_id', '')),
            'effective_date' => trim((string) $this->input('effective_date', '')),
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

    public function positionId(): int
    {
        return (int) $this->integer('position_id');
    }

    public function isHead(): bool
    {
        return (bool) $this->boolean('is_head');
    }

    public function effectiveDate(): string
    {
        return trim((string) $this->input('effective_date', ''));
    }

    public function isPrimary(): bool
    {
        return (bool) $this->boolean('is_primary');
    }
}
