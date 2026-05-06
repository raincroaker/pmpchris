<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeAboutMeBasicsRequest;
use App\Models\Employee;
use App\Services\AboutMeEmployeeDirectoryProfileWriter;
use Illuminate\Http\RedirectResponse;

class UpdateEmployeeAboutMeBasicsController extends Controller
{
    public function __invoke(UpdateEmployeeAboutMeBasicsRequest $request, Employee $employee, AboutMeEmployeeDirectoryProfileWriter $writer): RedirectResponse
    {
        $writer->updateBasics($employee, $request->validated());

        return back()->with('success', 'Profile updated.');
    }
}
