<?php

namespace App\Http\Requests;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeavePolicyRequest extends FormRequest
{
    use AuthorizesLeaveOvertimePolicyManagement;

    public function authorize(): bool
    {
        return $this->userMayMutatePolicies() && $this->defaultOrganizationId() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->defaultOrganizationId();

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('leave_policies', 'code')->where('organization_id', $organizationId),
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
