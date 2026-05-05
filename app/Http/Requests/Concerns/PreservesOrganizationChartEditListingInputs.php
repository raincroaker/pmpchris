<?php

namespace App\Http\Requests\Concerns;

trait PreservesOrganizationChartEditListingInputs
{
    /**
     * @return array<string, mixed>
     */
    protected function preservationFieldRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,created_at,id'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search?: string, sort: string, direction: string, per_page: int, page: int}
     */
    public function preservedListingQuery(): array
    {
        $search = trim((string) $this->input('search', ''));
        $sort = (string) $this->input('sort', 'name');
        $direction = strtolower((string) $this->input('direction', 'asc'));
        $perPage = (int) $this->integer('per_page', 10);
        $page = (int) $this->integer('page', 1);

        if (! in_array($sort, ['name', 'created_at', 'id'], true)) {
            $sort = 'name';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query = [
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => min(max($perPage, 5), 100),
            'page' => max($page, 1),
        ];

        if ($search !== '') {
            $query['search'] = $search;
        }

        return $query;
    }
}
