<?php

namespace App\Http\Middleware;

use App\Services\AdminUserActionAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrationAccess
{
    public function __construct(
        private AdminUserActionAccessService $adminUserActionAccessService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->adminUserActionAccessService->canAccessAdministration($request->user(), $request)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
