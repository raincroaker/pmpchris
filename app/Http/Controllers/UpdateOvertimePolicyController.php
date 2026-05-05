<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOvertimePolicyRequest;
use App\Models\OvertimePolicy;
use Illuminate\Http\RedirectResponse;

class UpdateOvertimePolicyController extends Controller
{
    public function __invoke(UpdateOvertimePolicyRequest $request, OvertimePolicy $overtimePolicy): RedirectResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->id;

        $overtimePolicy->fill([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'context' => $validated['context'],
            'rate_multiplier' => $validated['rate_multiplier'],
            'daily_threshold_hours' => $validated['daily_threshold_hours'],
            'daily_cap_hours' => $validated['daily_cap_hours'] ?? null,
            'weekly_cap_hours' => $validated['weekly_cap_hours'] ?? null,
            'requires_approval' => $validated['requires_approval'] ?? true,
            'minimum_lead_time_hours' => $validated['minimum_lead_time_hours'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
            'updated_by_user_id' => $userId,
        ]);
        $overtimePolicy->save();

        return redirect()->route('overtime.policies');
    }
}
