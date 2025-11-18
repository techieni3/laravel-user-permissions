<?php

declare(strict_types=1);

it('generate roles from Role enum', function (): void {

    $this->artisan('sync:roles')
        ->assertExitCode(0);

    $this->assertDatabaseHas('roles', [
        'name' => 'admin',
    ]);
});
