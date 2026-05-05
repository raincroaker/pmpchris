<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AdminUserActionAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AdminUserActionAccessService $accessService */
        $accessService = app(AdminUserActionAccessService::class);

        return $accessService->canAccessAdministration($this->user(), $this);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->targetUser()->id),
            ],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct', 'exists:organizational_units,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim((string) $this->input('email', '')),
            'password' => (string) $this->input('password', ''),
            'password_confirmation' => (string) $this->input('password_confirmation', ''),
            'branch_ids' => is_array($this->input('branch_ids'))
                ? array_values(array_unique(array_map(
                    static fn ($value): int => (int) $value,
                    $this->input('branch_ids')
                )))
                : [],
        ]);
    }

    public function targetUser(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
