<?php

declare(strict_types=1);

use Workbench\App\Models\User;

it('can check if a user has a permission', function (): void {
    // Create a permissions
    $this->artisan('sync:permissions')
        ->assertExitCode(0);

    $this->assertDatabaseHas('permissions', [
        'name' => 'admin.create',
    ]);

    // Create a user
    $user = User::query()->create([
        'name' => 'John Doe',
    ]);

    $this->assertDatabaseCount('users', 1);

    $user->addPermission('admin.create');

    $this->assertDatabaseCount('users_permissions', 1);

    $user->refresh();

    expect($user->hasPermission('admin.create'))->toBeTrue();
});
