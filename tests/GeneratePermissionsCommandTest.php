<?php

declare(strict_types=1);

use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

it('It generate permission for models', function (): void {

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'create_admin',
    ]);
});

it('It ignore permission generation for model if it is excluded', function (): void {

    $this->app['config']->set('permissions.excluded_models', [
        User::class,
        Admin::class,
    ]);

    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseCount('permissions', 0);
});
