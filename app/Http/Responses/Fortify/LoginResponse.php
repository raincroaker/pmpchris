<?php

namespace App\Http\Responses\Fortify;

use App\Services\BranchContextService;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function toResponse($request): Response
    {
        /** @var Request $request */
        $user = $request->user();

        if ($user?->mustSelectBranch()) {
            $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);

            if ($branchId > 0 && $this->branchContextService->isValidSessionBranchId($branchId)) {
                return redirect()->intended(route('dashboard', absolute: false));
            }

            return redirect()->route('branch.select');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
