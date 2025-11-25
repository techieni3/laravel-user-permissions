<?php

declare(strict_types=1);

use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

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
