<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user?->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ])) {
            return false;
        }

        return app(BranchContextService::class)->defaultOrganization() instanceof Organization;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'colorKey' => ['required', 'string', 'in:cyan,amber,lime,rose,violet,sky'],
            'pay_policy' => ['required', 'string', 'in:Double Pay,No Premium,Custom Multiplier'],
            'custom_multiplier' => ['nullable', 'string', 'max:32'],
            'premiumNote' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->has('name') ? trim((string) $this->input('name', '')) : $this->input('name'),
            'colorKey' => strtolower(trim((string) ($this->input('colorKey', $this->input('color_key', ''))))),
            'pay_policy' => trim((string) $this->input('pay_policy', '')),
            'custom_multiplier' => $this->filled('custom_multiplier')
                ? trim((string) $this->input('custom_multiplier'))
                : null,
            'premiumNote' => $this->filled('premiumNote')
                ? trim((string) $this->input('premiumNote'))
                : ($this->filled('premium_note') ? trim((string) $this->input('premium_note')) : null),
        ]);
    }

    public function colorKey(): string
    {
        return (string) $this->string('colorKey');
    }

    public function payPolicy(): string
    {
        return (string) $this->string('pay_policy');
    }

    public function customMultiplier(): ?string
    {
        $value = $this->input('custom_multiplier');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function premiumNote(): ?string
    {
        $value = $this->input('premiumNote');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function optionalNameForCustomType(): ?string
    {
        if (! $this->has('name')) {
            return null;
        }

        $name = trim((string) $this->input('name', ''));

        return $name === '' ? null : $name;
    }
}
