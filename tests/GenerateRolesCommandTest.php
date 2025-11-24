<?php

declare(strict_types=1);

it('generate roles from Role enum', function (): void {

    $this->artisan('sync:roles')
        ->assertExitCode(0);

    $this->assertDatabaseHas('roles', [
        'name' => 'admin',
    ]);
});

it('fails role generation if Role enum is not defined', function (): void {
    $this->app['config']->set('permissions.role_enum', '');

    $this->artisan('sync:roles')
        ->assertExitCode(1);

    $this->assertDatabaseCount('roles', 0);
});
