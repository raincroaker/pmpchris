<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyOvertimePolicyRequest;
use App\Models\OvertimePolicy;
use Illuminate\Http\RedirectResponse;

class DestroyOvertimePolicyController extends Controller
{
    public function __invoke(DestroyOvertimePolicyRequest $request, OvertimePolicy $overtimePolicy): RedirectResponse
    {
        $overtimePolicy->delete();

        return redirect()->route('overtime.policies');
    }
}
