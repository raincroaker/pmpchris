<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTeamAttendanceDayRequest;
use App\Models\EmployeeAttendanceDay;
use App\Services\TeamAttendanceDayMutationService;
use Illuminate\Http\RedirectResponse;

class UpdateTeamAttendanceDayController extends Controller
{
    public function __construct(
        private TeamAttendanceDayMutationService $teamAttendanceDayMutationService,
    ) {}

    public function __invoke(UpdateTeamAttendanceDayRequest $request, EmployeeAttendanceDay $employeeAttendanceDay): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->teamAttendanceDayMutationService->update(
            $employeeAttendanceDay,
            $request->validated(),
            $user,
        );

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/attendance/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('attendance.team');
    }
}
