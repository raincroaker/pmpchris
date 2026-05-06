<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamHrEmployeeLeavesRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['all', 'approved', 'rejected'])],
            'employee_id_number' => ['nullable', 'string', 'max:50'],
            'leave_type' => ['nullable', 'string', 'max:40'],
            'approve_from' => ['nullable', 'date'],
            'approve_to' => ['nullable', 'date', 'after_or_equal:approve_from'],
            'sort' => ['required', Rule::in(['dates', 'status', 'approve_date'])],
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

        if ($this->has('employee_id_number') && $this->input('employee_id_number') === '') {
            $merge['employee_id_number'] = null;
        }

        if ($this->has('leave_type') && $this->input('leave_type') === '') {
            $merge['leave_type'] = null;
        }

        if ($this->has('approve_from') && $this->input('approve_from') === '') {
            $merge['approve_from'] = null;
        }

        if ($this->has('approve_to') && $this->input('approve_to') === '') {
            $merge['approve_to'] = null;
        }

        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), ['dates', 'status', 'approve_date'], true)) {
            $merge['sort'] = 'dates';
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
    public function inertiaTeamLeaveFilters(): array
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
            'employee_id_number' => $v['employee_id_number'] ?? null,
            'leave_type' => $v['leave_type'] ?? null,
            'approve_from' => $v['approve_from'] ?? null,
            'approve_to' => $v['approve_to'] ?? null,
            'sort' => $v['sort'] ?? 'dates',
            'direction' => $v['direction'] ?? 'desc',
        ];
    }
}
