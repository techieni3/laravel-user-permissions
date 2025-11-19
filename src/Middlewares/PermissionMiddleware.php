<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Permission Middleware.
 *
 * This middleware ensures that the authenticated user has at least one of the specified permissions.
 * Multiple permissions can be specified using a pipe (|) separator.
 */
class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     * Verifies the user is authenticated and has the required permission(s).
     *
     * @param  Request  $request  The incoming request
     * @param  Closure(Request): (Response)  $next  The next middleware
     * @param  string  $permission  The required permission(s), separated by pipe (|) for multiple
     * @param  string|null  $guard  The authentication guard to use
     *
     * @throws HttpException When unauthorized
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);

        if ($authGuard->guest()) {
            abort(403, 'Unauthorized. You must be logged in.');
        }

        $user = $authGuard->user();

        // Support multiple permissions separated by pipe (|)
        $permissions = explode('|', $permission);

        if ( ! method_exists($user, 'hasAnyPermission')) {
            abort(500, 'User model must use HasPermissions trait.');
        }

        if ( ! $user->hasAnyPermission($permissions)) {
            abort(403, 'Unauthorized. You do not have the required permission.');
        }

        return $next($request);
    }
}
