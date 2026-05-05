<?php

namespace App\Http\Requests;

use App\Enums\OvertimePolicyContext;
use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOvertimePolicyRequest extends FormRequest
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
                Rule::unique('overtime_policies', 'code')->where('organization_id', $organizationId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'context' => ['required', Rule::enum(OvertimePolicyContext::class)],
            'rate_multiplier' => ['required', 'numeric', 'min:0'],
            'daily_threshold_hours' => ['required', 'numeric', 'min:0'],
            'daily_cap_hours' => ['nullable', 'numeric', 'min:0'],
            'weekly_cap_hours' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['sometimes', 'boolean'],
            'minimum_lead_time_hours' => ['nullable', 'numeric', 'min:0'],
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
            'code.unique' => 'An overtime policy with this code already exists for the organization.',
        ];
    }
}
