<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPositionsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'all'])],
            'sort' => ['required', Rule::in(['code', 'title', 'created_at', 'id'])],
            'direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => trim((string) $this->input('search')),
            ]);
        }

        if (! $this->has('status')) {
            $this->merge(['status' => 'active']);
        }

        $allowedSorts = ['code', 'title', 'created_at', 'id'];
        if (! $this->has('sort') || ! in_array((string) $this->input('sort'), $allowedSorts, true)) {
            $this->merge(['sort' => 'code']);
        }

        if (! $this->has('direction') || ! in_array((string) $this->input('direction'), ['asc', 'desc'], true)) {
            $this->merge(['direction' => 'asc']);
        }

        $perPage = $this->has('per_page')
            ? max(1, (int) $this->input('per_page'))
            : 10;
        $this->merge(['per_page' => min($perPage, 50)]);

        if (! $this->has('page')) {
            $this->merge(['page' => 1]);
        }

        $page = max(1, (int) $this->input('page', 1));
        $this->merge(['page' => $page]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The selected status is invalid.',
            'sort.in' => 'The selected sort column is invalid.',
            'direction.in' => 'The sort direction must be asc or desc.',
            'per_page.max' => 'The page size may not be greater than 50.',
        ];
    }
}
