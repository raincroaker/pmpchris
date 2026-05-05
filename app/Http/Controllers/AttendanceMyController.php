<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMyAttendanceRequest;
use App\Services\MyAttendancePageService;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceMyController extends Controller
{
    public function __construct(
        private MyAttendancePageService $myAttendancePageService,
    ) {}

    public function __invoke(IndexMyAttendanceRequest $request): Response
    {
        $employeeIdRaw = $request->user()?->employee_id;
        $employeeId = $employeeIdRaw !== null ? (int) $employeeIdRaw : null;

        $paginator = $this->myAttendancePageService->buildPage($request, $employeeId);

        return Inertia::render('Attendance/My', [
            'myAttendanceDays' => $paginator,
            'myAttendanceFilters' => $request->inertiaMyAttendanceFilters(),
            'hasEmployeeRecord' => $employeeId !== null,
        ]);
    }
}
