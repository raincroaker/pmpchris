<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DestroyCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'apply_to' => ['nullable', 'string', 'in:entire_series,single_occurrence'],
            'occurrence_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'apply_to' => trim((string) $this->input('apply_to', $this->input('applyTo', 'entire_series'))),
            'occurrence_date' => trim((string) $this->input('occurrence_date', $this->input('occurrenceDate', ''))),
        ]);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($this->applyTo() === 'single_occurrence' && $this->occurrenceDate() === null) {
                $validator->errors()->add('occurrence_date', 'Occurrence date is required for single occurrence delete.');
            }
        });
    }

    public function applyTo(): string
    {
        $value = (string) $this->string('apply_to');

        return $value === 'single_occurrence' ? 'single_occurrence' : 'entire_series';
    }

    public function occurrenceDate(): ?string
    {
        $value = trim((string) $this->input('occurrence_date', ''));

        return $value === '' ? null : $value;
    }
}
