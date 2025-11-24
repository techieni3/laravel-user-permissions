<?php

declare(strict_types=1);

use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

it('can check if a user has a role', function (): void {
    // Create a roles
    $this->artisan('sync:roles')
        ->assertExitCode(0);

    $this->assertDatabaseHas('roles', [
        'name' => 'admin',
    ]);

    // Create a user
    $user = User::query()->create([
        'name' => 'John Doe',
    ]);

    $this->assertDatabaseCount('users', 1);

    $user->addRole(Role::Admin);

    $this->assertDatabaseCount('users_roles', 1);

    $user->refresh();

    expect($user->hasRole(Role::Admin))->toBeTrue();
});
