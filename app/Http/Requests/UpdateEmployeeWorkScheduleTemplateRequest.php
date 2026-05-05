<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeWorkScheduleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return app(ScheduleAssignmentAccessService::class)->allows($user, $this);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = app(BranchContextService::class)->defaultOrganization();

        if ($organization === null) {
            return [
                'work_schedule_template_id' => ['prohibited'],
                'attendance_id' => ['prohibited'],
            ];
        }

        /** @var Employee|null $employee */
        $employee = $this->route('employee');
        $lockedTemplateId = $employee instanceof Employee
            ? $employee->work_schedule_template_id
            : null;

        return [
            'attendance_id' => ['nullable', 'string', 'max:50'],
            'work_schedule_template_id' => [
                'nullable',
                'integer',
                Rule::exists('work_schedule_templates', 'id')
                    ->where(function ($query) use ($organization, $lockedTemplateId): void {
                        $query->where('organization_id', $organization->id)
                            ->whereNull('deleted_at')
                            ->where(function ($inner) use ($lockedTemplateId): void {
                                $inner->where('is_active', true);

                                if ($lockedTemplateId !== null) {
                                    $inner->orWhere('id', (int) $lockedTemplateId);
                                }
                            });
                    }),
            ],
        ];
    }
}
