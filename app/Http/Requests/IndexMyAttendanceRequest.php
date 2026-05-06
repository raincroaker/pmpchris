<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMyAttendanceRequest extends FormRequest
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
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['all', 'complete', 'ongoing', 'incomplete'])],
            'recording_style' => ['nullable', Rule::in(['simple', 'split', 'overnight'])],
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

        if (! $this->filled('recording_style')) {
            $merge['recording_style'] = 'simple';
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
     * Normalized filters for Inertia.
     *
     * @return array<string, mixed>
     */
    public function inertiaMyAttendanceFilters(): array
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return [
            'page' => (int) ($v['page'] ?? 1),
            'per_page' => (int) ($v['per_page'] ?? 10),
            'q' => isset($v['q']) && is_string($v['q']) ? $v['q'] : '',
            'date_from' => $v['date_from'],
            'date_to' => $v['date_to'],
            'status' => $v['status'] ?? 'all',
            'recording_style' => $v['recording_style'] ?? 'simple',
            'sort' => $v['sort'] ?? 'work_date',
            'direction' => $v['direction'] ?? 'desc',
        ];
    }
}
