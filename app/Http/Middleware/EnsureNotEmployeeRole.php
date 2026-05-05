<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotEmployeeRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->hasAnyRole([
                Role::CODE_SUPER_ADMIN,
                Role::CODE_HR_HEAD,
                Role::CODE_HR_MANAGER,
            ]) ?? false,
            403
        );

        return $next($request);
    }
}
