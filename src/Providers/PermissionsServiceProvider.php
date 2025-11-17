<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Providers;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Techieni3\LaravelUserPermissions\Commands\GeneratePermissionsCommand;
use Techieni3\LaravelUserPermissions\Commands\GenerateRolesCommand;
use Techieni3\LaravelUserPermissions\Commands\InstallPermissions;
use Techieni3\LaravelUserPermissions\Middlewares\PermissionMiddleware;
use Techieni3\LaravelUserPermissions\Middlewares\RoleMiddleware;
use Techieni3\LaravelUserPermissions\Middlewares\RoleOrPermissionMiddleware;

class PermissionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                commands: [
                    GeneratePermissionsCommand::class,
                    GenerateRolesCommand::class,
                    InstallPermissions::class,
                ],
            );
        }

        $this->callAfterResolving(Gate::class, $this->setupGateBeforeCallback());
        $this->registerMiddleware();
    }

    /**
     * Register the package middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/permissions.php',
            'permissions'
        );
    }

    /**
     * Register the permission check method on the gate.
     */
    private function setupGateBeforeCallback(): Closure
    {
        return static fn (Gate $gate) => $gate->before(static function (Authorizable $user, string $ability): ?bool {
            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($ability) ?: null;
            }

            return null;
        });
    }
}
