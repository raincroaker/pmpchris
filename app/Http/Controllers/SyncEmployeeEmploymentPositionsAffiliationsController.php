<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncEmployeeEmploymentPositionsAffiliationsRequest;
use App\Models\EmployeeEmployment;
use App\Services\SyncEmployeeEmploymentPositionsAffiliationsService;
use Illuminate\Http\RedirectResponse;

class SyncEmployeeEmploymentPositionsAffiliationsController extends Controller
{
    public function __invoke(SyncEmployeeEmploymentPositionsAffiliationsRequest $request, EmployeeEmployment $employment): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $positions = [];
        /** @phpstan-ignore-next-line */
        if (isset($validated['positions']) && is_array($validated['positions'])) {
            /** @var list<array<string, mixed>> $positions */
            $positions = $validated['positions'];
        }

        $affiliations = [];
        /** @phpstan-ignore-next-line */
        if (isset($validated['affiliations']) && is_array($validated['affiliations'])) {
            /** @var list<array<string, mixed>> $affiliations */
            $affiliations = $validated['affiliations'];
        }

        /** @phpstan-ignore-next-line */
        app(SyncEmployeeEmploymentPositionsAffiliationsService::class)->sync($employment, $positions, $affiliations);

        return redirect()->back()->with('success', 'Positions and affiliations updated.');
    }
}
