<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchContextRequest;
use App\Rules\InternalAppPath;
use App\Services\BranchContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchContextController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function create(Request $request): Response
    {
        abort_unless($this->branchContextService->canSwitchBranchContext($request->user()), 403);

        $organization = $this->branchContextService->defaultOrganization();

        return Inertia::render('Branch/Select', [
            'organization' => $organization === null ? null : [
                'name' => $organization->name,
                'code' => $organization->code,
            ],
            'branches' => $this->branchContextService->branchesForPicker($request->user())->values()->all(),
        ]);
    }

    public function store(StoreBranchContextRequest $request): RedirectResponse
    {
        abort_unless($this->branchContextService->canSwitchBranchContext($request->user()), 403);

        $branchId = (int) $request->validated('branch_id');

        $unit = $this->branchContextService->findSelectableBranchRoot($branchId, user: $request->user());

        if ($unit === null) {
            throw ValidationException::withMessages([
                'branch_id' => __('The selected branch is not available.'),
            ]);
        }

        $request->session()->put(BranchContextService::SESSION_BRANCH_ID, $unit->id);
        $request->session()->put(BranchContextService::SESSION_BRANCH_META, [
            'code' => $unit->code,
            'name' => $unit->name,
        ]);

        $rawReturnTo = $request->input('return_to');

        if (is_string($rawReturnTo) && InternalAppPath::isValid($rawReturnTo)) {
            return redirect()->to(trim($rawReturnTo));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
