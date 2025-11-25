<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Exceptions\RoleException;
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

it('throws detailed error when bulk role verification fails', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Manually create an invalid role enum for testing
    // In real scenario, this would be a role that exists in enum but not in database
    // We'll test by trying to sync before database is populated
    DB::table('roles')->truncate();

    expect(static fn () => $user->syncRoles([Role::User]))
        ->toThrow(RoleException::class);
});

it('handles empty array gracefully in syncRoles', function (): void {
    $this->artisan('sync:roles');

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);

    // Sync empty array should clear all roles
    $user->syncRoles([]);

    expect($user->getRoles())->toHaveCount(0);
});
