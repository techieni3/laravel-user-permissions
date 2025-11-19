<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Providers;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;
use Override;
use Techieni3\LaravelUserPermissions\Commands\GeneratePermissionsCommand;
use Techieni3\LaravelUserPermissions\Commands\GenerateRolesCommand;
use Techieni3\LaravelUserPermissions\Commands\InstallPermissions;

/**
 * Permissions Service Provider.
 *
 * Registers and bootstraps the permissions package, including:
 * - Console commands for managing permissions and roles
 * - Gate integration for permission checking
 * - Package configuration
 */
class PermissionsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     * Registers console commands and sets up the Gate integration.
     */
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
    }

    /**
     * Register any application services.
     * Merges the package configuration with the application configuration.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/permissions.php',
            'permissions'
        );
    }

    /**
     * Register the permission check callback on the Gate.
     * This integrates permission checking into Laravel's authorization system.
     *
     * @return Closure The Gate before callback
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
