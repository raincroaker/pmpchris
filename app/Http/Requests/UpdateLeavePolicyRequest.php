<?php

namespace App\Http\Requests;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use App\Models\LeavePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeavePolicyRequest extends FormRequest
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
        /** @var LeavePolicy $policy */
        $policy = $this->route('leavePolicy');
        $organizationId = (int) $policy->organization_id;

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('leave_policies', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($policy->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'unit' => ['required', Rule::enum(LeavePolicyUnit::class)],
            'annual_entitlement' => ['required', 'numeric', 'min:0'],
            'use_accrual' => ['sometimes', 'boolean'],
            'accrual_cadence' => [
                Rule::requiredIf(fn (): bool => $this->boolean('use_accrual')),
                'nullable',
                Rule::enum(LeavePolicyAccrualCadence::class),
            ],
            'accrual_per_period' => [
                Rule::requiredIf(fn (): bool => $this->boolean('use_accrual')),
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_balance' => ['nullable', 'numeric', 'min:0'],
            'carryover_allowed' => ['sometimes', 'boolean'],
            'carryover_cap' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'applies_after_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'A leave policy with this code already exists for the organization.',
        ];
    }
}
