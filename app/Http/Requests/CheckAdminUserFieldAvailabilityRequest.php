<?php

namespace App\Http\Requests;

use App\Services\AdminUserActionAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckAdminUserFieldAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AdminUserActionAccessService $accessService */
        $accessService = app(AdminUserActionAccessService::class);

        return $accessService->canAccessAdministration($this->user(), $this);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'max:255'],
            'ignore_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'email' => trim((string) $this->input('email', '')),
        ];

        if ($this->has('ignore_user_id')) {
            $id = (int) $this->input('ignore_user_id', 0);
            $payload['ignore_user_id'] = $id > 0 ? $id : null;
        }

        $this->merge($payload);
    }

    public function email(): string
    {
        return trim((string) $this->input('email', ''));
    }

    public function ignoreUserId(): ?int
    {
        $id = (int) $this->input('ignore_user_id', 0);

        return $id > 0 ? $id : null;
    }
}
