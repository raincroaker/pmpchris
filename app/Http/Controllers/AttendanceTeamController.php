<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamAttendanceRequest;
use App\Services\EmployeeTeamHrPagesAccess;
use App\Services\TeamAttendancePageService;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceTeamController extends Controller
{
    public function __construct(
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
        private TeamAttendancePageService $teamAttendancePageService,
    ) {}

    public function __invoke(IndexTeamAttendanceRequest $request): Response
    {
        abort_unless($this->employeeTeamHrPagesAccess->allows($request->user(), $request), 403);

        $built = $this->teamAttendancePageService->buildPage($request);

        return Inertia::render('Attendance/Team', [
            'teamAttendanceDays' => $built['paginator'],
            'attendanceTeamFilters' => $built['filters'],
        ]);
    }
}
