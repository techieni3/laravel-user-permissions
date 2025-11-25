<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ensures user has required permission(s). Use pipe (|) for multiple.
 */
class PermissionMiddleware
{
    /**
     * @param  string  $permission  Pipe-separated permissions
     *
     * @throws HttpException
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
