<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarEventCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'color_key' => ['required', 'string', 'in:teal,blue,indigo,violet,fuchsia,rose,orange,emerald,slate'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'color_key' => strtolower(trim((string) ($this->input('color_key', $this->input('colorKey', ''))))),
        ]);
    }

    public function categoryName(): string
    {
        return (string) $this->string('name');
    }

    public function colorKey(): string
    {
        return (string) $this->string('color_key');
    }
}
