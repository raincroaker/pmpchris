<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeWorkScheduleTemplateRequest;
use App\Models\Employee;
use App\Services\BranchContextService;
use App\Services\ScheduleAssignmentAccessService;
use Illuminate\Http\RedirectResponse;

class UpdateEmployeeWorkScheduleTemplateController extends Controller
{
    public function __invoke(
        UpdateEmployeeWorkScheduleTemplateRequest $request,
        Employee $employee,
        BranchContextService $branchContextService,
        ScheduleAssignmentAccessService $scheduleAssignmentAccess,
    ): RedirectResponse {
        $organization = $branchContextService->defaultOrganization();
        abort_if($organization === null, 404);

        abort_unless(
            $scheduleAssignmentAccess->employeeBelongsToDirectory($employee, $organization, $request),
            403,
        );

        /** @var array{work_schedule_template_id?: int|null, attendance_id?: string|null} $data */
        $data = $request->validated();

        $employee->update([
            'work_schedule_template_id' => $data['work_schedule_template_id'] ?? null,
            'attendance_id' => array_key_exists('attendance_id', $data)
                ? $data['attendance_id']
                : $employee->attendance_id,
        ]);

        return redirect()->back();
    }
}
