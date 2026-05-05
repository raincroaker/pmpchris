<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchTeamHrDecisionMakerEmployeesRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:120'],
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
}
