<?php

namespace App\Http\Middleware;

use App\Services\EmployeeTeamHrPagesAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeTeamHrLeaveOvertimeAccess
{
    public function __construct(
        private EmployeeTeamHrPagesAccess $employeeTeamHrPagesAccess,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->employeeTeamHrPagesAccess->allows($request->user(), $request),
            403
        );

        return $next($request);
    }
}
