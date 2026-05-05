<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMyEmployeeOvertimesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(['all', 'approved', 'rejected'])],
            'policy_code' => ['nullable', 'string', 'max:40'],
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

        if ($this->has('policy_code') && $this->input('policy_code') === '') {
            $merge['policy_code'] = null;
        }

        $this->merge($merge);
    }

    /**
     * Normalized filters for Inertia.
     *
     * @return array<string, mixed>
     */
    public function inertiaMyOvertimeFilters(): array
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
            'policy_code' => $v['policy_code'] ?? null,
        ];
    }
}
