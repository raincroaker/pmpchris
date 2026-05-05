<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchOrgChartEmployeesRequest extends FormRequest
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
            'chart_branch_id' => ['required', 'integer', 'min:1'],
            'node_id' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function chartBranchId(): int
    {
        return (int) $this->integer('chart_branch_id');
    }

    public function limit(): int
    {
        $limit = (int) $this->integer('limit', 20);

        return min(max($limit, 1), 50);
    }

    public function queryText(): string
    {
        return trim((string) $this->input('q', ''));
    }

    public function parsedNodeUnitId(): ?int
    {
        $raw = trim((string) $this->input('node_id', ''));
        if ($raw === '' || ! str_starts_with($raw, 'unit-')) {
            return null;
        }

        $id = (int) substr($raw, strlen('unit-'));

        return $id > 0 ? $id : null;
    }
}
