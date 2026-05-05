<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationHolidayRequest extends FormRequest
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

        $organization = app(BranchContextService::class)->defaultOrganization();
        if (! $organization instanceof Organization) {
            return false;
        }

        $holiday = $this->route('organizationHoliday');
        if ($holiday instanceof OrganizationHoliday) {
            return (int) $holiday->organization_id === (int) $organization->id;
        }

        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'type_id' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recurrence' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'type_id' => strtolower(trim((string) ($this->input('type_id', $this->input('typeId', ''))))),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function holidayName(): string
    {
        return (string) $this->string('name');
    }

    public function startDate(): string
    {
        return (string) $this->string('start_date');
    }

    public function endDate(): string
    {
        return (string) $this->string('end_date');
    }

    public function typeSlug(): string
    {
        return (string) $this->string('type_id');
    }

    public function notes(): ?string
    {
        $value = $this->input('notes');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recurrencePayload(): ?array
    {
        /** @var mixed $raw */
        $raw = $this->input('recurrence');

        return is_array($raw) ? $raw : null;
    }
}
