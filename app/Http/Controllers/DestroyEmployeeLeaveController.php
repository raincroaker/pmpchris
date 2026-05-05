<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyEmployeeLeaveRequest;
use App\Models\EmployeeLeave;
use Illuminate\Http\RedirectResponse;

class DestroyEmployeeLeaveController extends Controller
{
    public function __invoke(DestroyEmployeeLeaveRequest $request, EmployeeLeave $employeeLeave): RedirectResponse
    {
        $employeeLeave->delete();

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/leave/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('leave.team');
    }
}
