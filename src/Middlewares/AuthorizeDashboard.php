<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes access to the permissions dashboard using a configured gate.
 */
class AuthorizeDashboard
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('permissions.dashboard.gate');

        // If no gate is configured, allow access
        if ($gate === null || $gate === '') {
            return $next($request);
        }

        // Check if the gate is defined
        if ( ! Gate::has($gate)) {
            abort(500, "Gate '{$gate}' is not defined. Please define it in your AppServiceProvider.");
        }

        // Check authorization
        if (Gate::denies($gate)) {
            abort(403, 'Unauthorized access to permissions dashboard.');
        }

        return $next($request);
    }
}
