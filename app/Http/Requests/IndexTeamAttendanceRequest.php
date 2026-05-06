<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['all', 'complete', 'ongoing', 'incomplete'])],
            'punctuality' => ['nullable', Rule::in(['all', 'on_time', 'late', 'not_applicable'])],
            'recording_style' => ['nullable', Rule::in(['all', 'simple', 'split', 'overnight'])],
            'chart_half' => ['nullable', Rule::in(['first_half', 'second_half'])],
            'sort' => ['required', Rule::in(['work_date'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('q') && is_string($this->input('q'))) {
            $merge['q'] = trim($this->input('q'));
        }

        if (! $this->has('page')) {
            $merge['page'] = 1;
        }

        if (! $this->has('per_page')) {
            $merge['per_page'] = 10;
        }

        $perPage = max(1, min(100, (int) $this->input('per_page', 10)));
        $merge['per_page'] = $perPage;

        $merge['page'] = max(1, (int) $this->input('page', 1));

        if (! $this->has('date_from') || ! $this->has('date_to')) {
            $merge['date_from'] = now()->startOfMonth()->toDateString();
            $merge['date_to'] = now()->endOfMonth()->toDateString();
        }

        if (! $this->filled('status')) {
            $merge['status'] = 'all';
        }

        if (! $this->filled('punctuality')) {
            $merge['punctuality'] = 'all';
        }

        if (! $this->filled('recording_style')) {
            $merge['recording_style'] = 'all';
        }

        if (! $this->filled('chart_half')) {
            $merge['chart_half'] = 'first_half';
        }

        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), ['work_date'], true)) {
            $merge['sort'] = 'work_date';
        }

        if (! $this->has('direction') || ! in_array((string) $this->input('direction'), ['asc', 'desc'], true)) {
            $merge['direction'] = 'desc';
        }

        $this->merge($merge);
    }

    /**
     * Normalized filters for Inertia so optional keys are always present (null when unused).
     *
     * @return array<string, mixed>
     */
    public function inertiaTeamAttendanceFilters(): array
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return [
            'page' => (int) ($v['page'] ?? 1),
            'per_page' => (int) ($v['per_page'] ?? 10),
            'q' => isset($v['q']) && is_string($v['q']) ? $v['q'] : '',
            'unit_id' => array_key_exists('unit_id', $v) && $v['unit_id'] !== null
                ? (int) $v['unit_id']
                : null,
            'date_from' => $v['date_from'],
            'date_to' => $v['date_to'],
            'status' => $v['status'] ?? 'all',
            'punctuality' => $v['punctuality'] ?? 'all',
            'recording_style' => $v['recording_style'] ?? 'all',
            'chart_half' => $v['chart_half'] ?? 'first_half',
            'sort' => $v['sort'] ?? 'work_date',
            'direction' => $v['direction'] ?? 'desc',
        ];
    }
}
