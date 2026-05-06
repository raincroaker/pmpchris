<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeEmploymentDatesRequest;
use App\Models\EmployeeEmployment;
use Illuminate\Http\RedirectResponse;

class UpdateEmployeeEmploymentDatesController extends Controller
{
    public function __invoke(UpdateEmployeeEmploymentDatesRequest $request, EmployeeEmployment $employment): RedirectResponse
    {
        $validated = $request->validated();
        $hireDate = (string) $validated['hire_date'];

        $separationProvided = isset($validated['separation_date'])
            && is_string($validated['separation_date'])
            && $validated['separation_date'] !== '';

        $employment->hire_date = $hireDate;

        if ($separationProvided) {
            $employment->separation_date = (string) $validated['separation_date'];
            $employment->employment_status = (string) $validated['employment_status'];
            $employment->separation_reason = isset($validated['separation_reason']) && is_string($validated['separation_reason']) && trim($validated['separation_reason']) !== ''
                ? trim($validated['separation_reason'])
                : null;
            $employment->notes = isset($validated['notes']) && is_string($validated['notes']) && trim($validated['notes']) !== ''
                ? trim($validated['notes'])
                : null;
            $employment->is_current = false;

            $employment->save();

            return back()->with('success', 'Separation recorded.');
        }

        $employment->separation_date = null;
        $employment->save();

        return back()->with('success', 'Employment dates updated.');
    }
}
