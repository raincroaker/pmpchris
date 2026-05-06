<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use App\Models\OvertimePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyOvertimePolicyRequest extends FormRequest
{
    use AuthorizesLeaveOvertimePolicyManagement;

    public function authorize(): bool
    {
        if (! $this->userMayMutatePolicies()) {
            return false;
        }

        $organizationId = $this->defaultOrganizationId();
        if ($organizationId === null) {
            return false;
        }

        $policy = $this->route('overtimePolicy');
        if (! $policy instanceof OvertimePolicy) {
            return false;
        }

        return (int) $policy->organization_id === (int) $organizationId;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
