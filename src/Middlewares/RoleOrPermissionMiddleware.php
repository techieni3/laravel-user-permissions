<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roleOrPermission, ?string $guard = null): Response
    {
        $authGuard = Auth::guard($guard);

        if ($authGuard->guest()) {
            abort(403, 'Unauthorized. You must be logged in.');
        }

        $user = $authGuard->user();

        // Support multiple roles/permissions separated by pipe (|)
        $rolesOrPermissions = explode('|', $roleOrPermission);

        if ( ! method_exists($user, 'hasAnyRole') || ! method_exists($user, 'hasAnyPermission')) {
            abort(500, 'User model must use HasRoles trait.');
        }

        // Check if user has any of the roles OR any of the permissions
        $hasRole = $user->hasAnyRole($rolesOrPermissions);
        $hasPermission = $user->hasAnyPermission($rolesOrPermissions);

        if ( ! $hasRole && ! $hasPermission) {
            abort(403, 'Unauthorized. You do not have the required role or permission.');
        }

        return $next($request);
    }
}
