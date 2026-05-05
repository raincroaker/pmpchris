<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyEmployeeOvertimeRequest;
use App\Models\EmployeeOvertime;
use Illuminate\Http\RedirectResponse;

class DestroyEmployeeOvertimeController extends Controller
{
    public function __invoke(DestroyEmployeeOvertimeRequest $request, EmployeeOvertime $employeeOvertime): RedirectResponse
    {
        $employeeOvertime->delete();

        $previous = url()->previous();

        if ($previous !== '' && str_contains($previous, '/overtime/team')) {
            return redirect()->to($previous);
        }

        return redirect()->route('overtime.team');
    }
}
