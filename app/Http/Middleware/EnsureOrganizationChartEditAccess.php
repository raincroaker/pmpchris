<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationChartEditAccess
{
    /**
     * Restrict Edit Structure / organization chart structure management to privileged roles only.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->canEditOrganizationStructure() ?? false, 403);

        return $next($request);
    }
}
