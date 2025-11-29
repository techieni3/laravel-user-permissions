<?php

declare(strict_types=1);

use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

it('generate permission for models', function (): void {

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'admin.create',
    ]);
});

it('fails permission generation if modelAction enum is not defined', function (): void {
    $this->app['config']->set('permissions.classes.model_actions_enum', '');

    $this->artisan('sync:permissions')
        ->assertExitCode(1);

    $this->assertDatabaseCount('permissions', 0);
});

it('ignore permission generation for model if it is excluded', function (): void {

    $this->app['config']->set('permissions.models.excluded', [
        User::class,
        Admin::class,
    ]);

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseCount('permissions', 0);
});

it('can generate permissions for specific model classes', function (): void {
    $this->app['config']->set('permissions.models.included', [
        Admin::class,
    ]);

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'admin.create',
    ]);

    $this->assertDatabaseMissing('permissions', [
        'name' => 'user.create',
    ]);
});

it('can generate permissions for multiple directories', function (): void {
    $this->app['config']->set('permissions.models.included', [
        workbench_path('app/Models'),
    ]);

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'admin.create',
    ]);

    $this->assertDatabaseHas('permissions', [
        'name' => 'user.create',
    ]);
});

it('can mix directories and specific model classes', function (): void {
    // Create a temporary directory with a model
    $tempDir = workbench_path('app/TempModels');
    if ( ! is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    file_put_contents($tempDir.'/Product.php', '<?php
namespace Workbench\App\TempModels;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {}
');

    $this->app['config']->set('permissions.models.included', [
        workbench_path('app/Models'),
        'Workbench\App\TempModels\Product',
    ]);

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'admin.create',
    ]);

    $this->assertDatabaseHas('permissions', [
        'name' => 'product.create',
    ]);

    // Cleanup
    unlink($tempDir.'/Product.php');
    rmdir($tempDir);
});
