<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesTeamHrLeaveOvertimeRecords;
use App\Models\EmployeeAttendanceDay;
use App\Services\TeamAttendanceDayMutationService;
use Illuminate\Foundation\Http\FormRequest;

class DestroyTeamAttendanceDayRequest extends FormRequest
{
    use AuthorizesTeamHrLeaveOvertimeRecords;

    public function authorize(): bool
    {
        if (! $this->userMayMutateTeamHrRecords()) {
            return false;
        }

        $organization = $this->defaultOrganization();
        if ($organization === null || $this->workspaceBranchRootId() === null) {
            return false;
        }

        $day = $this->route('employeeAttendanceDay');
        if (! $day instanceof EmployeeAttendanceDay) {
            return false;
        }

        if ((int) $day->organization_id !== (int) $organization->id) {
            return false;
        }

        return app(TeamAttendanceDayMutationService::class)->isAttendanceDayMutableInWorkspace($day, $this);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
