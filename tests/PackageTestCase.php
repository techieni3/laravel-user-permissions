<?php

declare(strict_types=1);

namespace TechieNi3\LaravelUserPermissions\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Orchestra\Testbench\TestCase;
use Techieni3\LaravelUserPermissions\Providers\PermissionsServiceProvider;

use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\workbench_path;

class PackageTestCase extends TestCase
{
    use LazilyRefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            PermissionsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {

        $this->loadMigrationsFrom(package_path('migrations'));
        $this->loadMigrationsFrom(workbench_path('database/migrations'));
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        tap($app['config'], static function (Repository $config): void {
            $config->set('database.default', 'testbench');
            $config->set('database.connections.testbench', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);

            $config->set('permissions.models_directory', workbench_path('app/Models'));
            $config->set('permissions.role_enum_file', workbench_path('app/Enums/Role.php'));
            $config->set('permissions.user_model_file', workbench_path('app/Models/User.php'));
        });
    }
}
