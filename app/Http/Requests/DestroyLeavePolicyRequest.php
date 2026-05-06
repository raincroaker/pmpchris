<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use App\Models\LeavePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyLeavePolicyRequest extends FormRequest
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

        $policy = $this->route('leavePolicy');
        if (! $policy instanceof LeavePolicy) {
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
