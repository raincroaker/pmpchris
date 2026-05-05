<?php

namespace App\Http\Middleware;

use App\Services\ScheduleAssignmentAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScheduleAssignmentAccess
{
    public function __construct(
        private ScheduleAssignmentAccessService $scheduleAssignmentAccess,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->scheduleAssignmentAccess->allows($request->user(), $request)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
