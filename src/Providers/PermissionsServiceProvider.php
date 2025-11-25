<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Providers;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Override;
use Techieni3\LaravelUserPermissions\Commands\GeneratePermissionsCommand;
use Techieni3\LaravelUserPermissions\Commands\GenerateRolesCommand;
use Techieni3\LaravelUserPermissions\Commands\InstallPermissionsCommand;
use Techieni3\LaravelUserPermissions\Middlewares\PermissionMiddleware;
use Techieni3\LaravelUserPermissions\Middlewares\RoleMiddleware;
use Techieni3\LaravelUserPermissions\Middlewares\RoleOrPermissionMiddleware;

/**
 * Permissions Service Provider
 */
class PermissionsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services and register commands.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();

        if ($this->app->runningInConsole()) {
            $this->commands(
                commands: [
                    GeneratePermissionsCommand::class,
                    GenerateRolesCommand::class,
                    InstallPermissionsCommand::class,
                ],
            );
        }

        $this->callAfterResolving(Gate::class, $this->setupGateBeforeCallback());
        $this->registerMiddleware();
    }

    /**
     * Merge package configuration.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../../config/permissions.php',
            key: 'permissions'
        );
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if ( ! config('permissions.dashboard_enabled', false)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
    }

    /**
     * Register the package resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(path: __DIR__.'/../../resources/views', namespace: 'permissions');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ( ! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../public' => public_path('vendor/permissions'),
        ], 'permissions-assets');

        $this->publishes([
            __DIR__.'/../../config/permissions.php' => config_path('permissions.php'),
        ], 'permissions-config');
    }

    /**
     * Register the package middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role.or.permission', RoleOrPermissionMiddleware::class);
    }

    /**
     * Register the permission check callback on the Gate.
     *
     * @return Closure Gate before callback for permission checking
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
