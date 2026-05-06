<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncEmployeeAboutMeAddressesRequest;
use App\Models\Employee;
use App\Services\AboutMeEmployeeDirectoryProfileWriter;
use Illuminate\Http\RedirectResponse;

class SyncEmployeeAboutMeAddressesController extends Controller
{
    public function __invoke(SyncEmployeeAboutMeAddressesRequest $request, Employee $employee, AboutMeEmployeeDirectoryProfileWriter $writer): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array<string, mixed>|null $current */
        $current = isset($validated['current']) && is_array($validated['current']) ? $validated['current'] : null;

        /** @var array<string, mixed>|null $permanent */
        $permanent = isset($validated['permanent']) && is_array($validated['permanent']) ? $validated['permanent'] : null;

        $writer->syncAddresses($employee, $current, $permanent);

        return back()->with('success', 'Addresses updated.');
    }
}
