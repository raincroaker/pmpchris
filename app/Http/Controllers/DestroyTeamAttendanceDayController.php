<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyTeamAttendanceDayRequest;
use App\Models\EmployeeAttendanceDay;
use App\Services\TeamAttendanceDayMutationService;
use Illuminate\Http\RedirectResponse;

class DestroyTeamAttendanceDayController extends Controller
{
    public function __construct(
        private TeamAttendanceDayMutationService $teamAttendanceDayMutationService,
    ) {}

    public function __invoke(DestroyTeamAttendanceDayRequest $request, EmployeeAttendanceDay $employeeAttendanceDay): RedirectResponse
    {
        $this->teamAttendanceDayMutationService->destroy($employeeAttendanceDay);

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/attendance/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('attendance.team');
    }
}
