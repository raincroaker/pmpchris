<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyLeavePolicyRequest;
use App\Models\LeavePolicy;
use Illuminate\Http\RedirectResponse;

class DestroyLeavePolicyController extends Controller
{
    public function __invoke(DestroyLeavePolicyRequest $request, LeavePolicy $leavePolicy): RedirectResponse
    {
        $leavePolicy->delete();

        return redirect()->route('leave.policies');
    }
}
