<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
