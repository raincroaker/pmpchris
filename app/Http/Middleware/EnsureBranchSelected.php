<?php

namespace App\Http\Middleware;

use App\Services\BranchContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchSelected
{
    public function __construct(
        private BranchContextService $branchContextService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $this->branchContextService->canSwitchBranchContext($user)) {
            return $next($request);
        }

        $branchId = (int) $request->session()->get(BranchContextService::SESSION_BRANCH_ID, 0);

        if ($branchId <= 0 || ! $this->branchContextService->isValidSessionBranchId($branchId, $user)) {
            $request->session()->forget([
                BranchContextService::SESSION_BRANCH_ID,
                BranchContextService::SESSION_BRANCH_META,
            ]);

            return redirect()->route('branch.select');
        }

        return $next($request);
    }
}
