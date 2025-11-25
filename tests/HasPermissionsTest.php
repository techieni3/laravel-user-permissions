<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Exceptions\PermissionException;
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

it('throws detailed error when bulk permission verification fails', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Try to sync permissions that don't exist
    expect(
        static fn () => $user->syncPermissions([
            'fake.permission.1',
            'admin.create',
            'fake.permission.2',
        ]),
    )->toThrow(PermissionException::class);
});

it('handles empty array gracefully in syncPermissions', function (): void {
    $this->artisan('sync:permissions');

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');

    // Sync empty array should clear all permissions
    $user->syncPermissions([]);

    expect($user->directPermissions()->get())->toHaveCount(0);
});
