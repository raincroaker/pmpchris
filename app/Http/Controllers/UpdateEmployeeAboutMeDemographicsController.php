<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeAboutMeDemographicsRequest;
use App\Models\Employee;
use App\Services\AboutMeEmployeeDirectoryProfileWriter;
use Illuminate\Http\RedirectResponse;

class UpdateEmployeeAboutMeDemographicsController extends Controller
{
    public function __invoke(UpdateEmployeeAboutMeDemographicsRequest $request, Employee $employee, AboutMeEmployeeDirectoryProfileWriter $writer): RedirectResponse
    {
        $writer->updateDemographics($employee, $request->validated());

        return back()->with('success', 'Identity updated.');
    }
}
