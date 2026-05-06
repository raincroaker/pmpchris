<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\OrganizationHoliday;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyOrganizationHolidayRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
