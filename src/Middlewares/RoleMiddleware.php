<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role Middleware.
 *
 * This middleware ensures that the authenticated user has at least one of the specified roles.
 * Multiple roles can be specified using a pipe (|) separator.
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Verifies the user is authenticated and has the required role(s).
     *
     * @param  Request  $request  The incoming request
     * @param  Closure(Request): (Response)  $next  The next middleware
     * @param  string  $role  The required role(s), separated by pipe (|) for multiple
     * @param  string|null  $guard  The authentication guard to use
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException When unauthorized
     */
    public function handle(Request $request, Closure $next, string $role, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);

        if ($authGuard->guest()) {
            abort(403, 'Unauthorized. You must be logged in.');
        }

        $user = $authGuard->user();

        // Support multiple roles separated by pipe (|)
        $roles = explode('|', $role);

        if ( ! method_exists($user, 'hasAnyRole')) {
            abort(500, 'User model must use HasRoles trait.');
        }

        if ( ! $user->hasAnyRole($roles)) {
            abort(403, 'Unauthorized. You do not have the required role.');
        }

        return $next($request);
    }
}
