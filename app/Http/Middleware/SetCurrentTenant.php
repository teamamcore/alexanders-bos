<?php

namespace App\Http\Middleware;

use App\Models\User;
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

            // SOW §3.13 / MSA §5.1 — a deactivated user is signed out on their next request.
            if ($user instanceof User && $user->is_active === false) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'Your account has been deactivated. Contact your administrator.');
            }

            app()->instance('currentUser', $user);
            if ($user->tenant_id) {
                app()->instance('currentTenantId', $user->tenant_id);
            }
        }

        return $next($request);
    }
}
