<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bind current tenant/user on every web request (tenant isolation).
        // Must run BEFORE route-model binding so {job}, {employee}, … resolve
        // inside the tenant scope (cross-tenant ids → 404).
        $middleware->web(append: [
            SetCurrentTenant::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCurrentTenant::class);
        // Provider webhooks authenticate with a shared token, not a session.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // Role guard alias (SOW §3.13).
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
