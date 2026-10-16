<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current tenant + user into the container for each request, so the
 * BelongsToTenant global scope and field-user scoping apply automatically
 * (MSA §2.2, §6.1 — tenant isolation per request).
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            app()->instance('currentUser', $user);
            if ($user->tenant_id) {
                app()->instance('currentTenantId', $user->tenant_id);
            }
        }

        return $next($request);
    }
}
