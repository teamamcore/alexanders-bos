<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for role-based access (SOW §3.13): field / office / administrator.
 * Usage: ->middleware('role:administrator') or 'role:office,administrator'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, 'Insufficient role.');
        }

        return $next($request);
    }
}
