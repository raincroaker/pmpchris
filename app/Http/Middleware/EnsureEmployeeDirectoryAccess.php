<?php

namespace App\Http\Middleware;

use App\Services\EmployeeTeamHrPagesAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeDirectoryAccess
{
    public function __construct(
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->employeeTeamHrPagesAccess->allows($request->user(), $request)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
