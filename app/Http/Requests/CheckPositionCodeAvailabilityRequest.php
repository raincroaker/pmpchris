<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPositionCodeAvailabilityRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:100'],
            'ignore_position_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code', ''))),
        ]);
    }

    public function code(): string
    {
        return strtoupper(trim((string) $this->input('code', '')));
    }

    public function ignorePositionId(): ?int
    {
        $positionId = (int) $this->integer('ignore_position_id', 0);

        return $positionId > 0 ? $positionId : null;
    }
}
