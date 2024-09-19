<?php

declare(strict_types=1);

use Workbench\App\Models\User;

it('can check if a user has a permission', function (): void {
    // Create a permissions
    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'create_admin',
    ]);

    // Create a user
    $user = User::create([
        'name' => 'John Doe',
    ]);

    $this->assertDatabaseCount('users', 1);

    $user->addPermission('create_admin');

    $this->assertDatabaseCount('users_permissions', 1);

    $user->refresh();

    expect($user->hasPermission('create_admin'))->toBeTrue();
});
