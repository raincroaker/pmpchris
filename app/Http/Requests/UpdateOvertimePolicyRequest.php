<?php

namespace App\Http\Requests;

use App\Enums\OvertimePolicyContext;
use App\Http\Requests\Concerns\AuthorizesLeaveOvertimePolicyManagement;
use App\Models\OvertimePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimePolicyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OvertimePolicy $policy */
        $policy = $this->route('overtimePolicy');
        $organizationId = (int) $policy->organization_id;

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('overtime_policies', 'code')
                    ->where('organization_id', $organizationId)
                    ->ignore($policy->id),
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
