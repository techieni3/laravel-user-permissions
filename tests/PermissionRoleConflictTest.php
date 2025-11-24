<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    // Create test permissions
    Permission::query()->create(['name' => 'post.update']);
    Permission::query()->create(['name' => 'post.view']);
    Permission::query()->create(['name' => 'post.delete']);
    Permission::query()->create(['name' => 'post.create']);

    // Create test roles
    $this->adminRole = Role::query()->create([
        'name' => 'admin',
        'display_name' => 'Administrator',
    ]);
    $this->editorRole = Role::query()->create([
        'name' => 'user',
        'display_name' => 'Editor',
    ]);

    // Assign permissions to admin role
    $this->adminRole
        ->permissions()
        ->attach([
            Permission::query()->where('name', 'post.update')->first()->id,
            Permission::query()->where('name', 'post.delete')->first()->id,
        ]);

    // Assign permissions to editor role
    $this->editorRole
        ->permissions()
        ->attach([Permission::query()->where('name', 'post.view')->first()->id]);

    // Create test user
    $this->user = User::query()->create([
        'name' => 'Test User',
    ]);
});

it('user cannot add permission directly if already granted via role', function (): void {
    // Assign an admin role (which has 'post.update' permission)
    $this->user->addRole('admin');

    // Try to add 'post.update' directly - should fail
    expect(fn () => $this->user->addPermission('post.update'))->toThrow(
        RuntimeException::class,
    );
});

it('user can add permission directly if not granted via role', function (): void {
    // Assign an editor role (which has 'view_post' but not 'post.update')
    $this->user->addRole('user');

    // Add 'post.update' directly - should succeed
    $this->user->addPermission('post.update');

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.update')
            ->exists(),
    )->toBeTrue();

    expect($this->user->hasPermission('post.update'))->toBeTrue();
});

it('user cannot remove permission granted via role without removing role', function (): void {
    // Assign an admin role (which has 'post.update' permission)
    $this->user->addRole('admin');

    // Try to remove 'post.update' - should fail
    expect(fn () => $this->user->removePermission('post.update'))
        ->toThrow(RuntimeException::class);

    // User should still have the permission
    expect($this->user->hasPermission('post.update'))->toBeTrue();
});

it('user can remove direct permission without removing role', function (): void {
    // Assign editor role (which has 'view_post')
    $this->user->addRole('user');

    expect($this->user->hasPermission('post.view'))->toBeTrue();

    // Add 'post.update' directly
    $this->user->addPermission('post.update');

    // Remove 'post.update' directly - should succeed
    $this->user->removePermission('post.update');

    expect($this->user->hasPermission('post.update'))->toBeFalse();

    // Should still have 'view_post' from editor role
    expect($this->user->hasPermission('post.view'))->toBeTrue();
});

it('sync permissions that are already not granted via role', function (): void {
    // Assign admin role (which has 'post.update' and 'delete_post')
    $this->user->addRole('admin');

    expect($this->user->directPermissions()->count())->toBe(0);

    $this->user->syncPermissions(['post.update', 'post.create']);

    // 'post.update' - should get added to direct premissions
    expect($this->user->directPermissions()->count())->toBe(1);
});

it('user can sync permissions that are not granted via role', function (): void {
    // Assign editor role (which has 'view_post')
    $this->user->addRole('user');

    // Sync permissions not in role - should succeed
    $this->user->syncPermissions(['post.update', 'post.create']);

    expect($this->user->directPermissions()->count())->toBe(2);

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.update')
            ->exists(),
    )->toBeTrue();

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.create')
            ->exists(),
    )->toBeTrue();

    // Should not have 'view_post' directly (only via role)
    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.view')
            ->exists(),
    )->toBeFalse();
    // But should still have access to it via role
    expect($this->user->hasPermission('post.view'))->toBeTrue();
});

it('user with multiple roles cannot add permission if any role grants it', function (): void {
    // Assign both admin and editor roles
    $this->user->addRole('admin'); // Has 'post.update' and 'delete_post'
    $this->user->addRole('user'); // Has 'view_post'

    // Try to add 'post.update' - should fail (granted by admin role)
    expect(fn () => $this->user->addPermission('post.update'))
        ->toThrow(RuntimeException::class);

    // Try to add 'view_post' - should fail (granted by editor role)
    expect(fn () => $this->user->addPermission('post.view'))
        ->toThrow(RuntimeException::class);

    // Should be able to add 'create_post' (not granted by any role)
    $this->user->addPermission('post.create');

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.create')
            ->exists(),
    )->toBeTrue();
});

it('adding role does not interfere with existing direct permissions', function (): void {
    // Add 'create_post' directly
    $this->user->addPermission('post.create');

    // Add editor role (which has 'view_post')
    $this->user->addRole('user');

    // Should have both permissions
    expect($this->user->hasPermission('post.create'))->toBeTrue();
    expect($this->user->hasPermission('post.view'))->toBeTrue();

    // 'create_post' should still be direct
    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.create')
            ->exists(),
    )->toBeTrue();
});

it('removing role allows adding previously restricted permissions', function (): void {
    // Assign admin role (which has 'post.update')
    $this->user->addRole('admin');

    // Cannot add 'post.update' directly
    expect(fn () => $this->user->addPermission('post.update'))
        ->toThrow(RuntimeException::class);

    // Remove admin role
    $this->user->removeRole('admin');

    // Now should be able to add 'post.update' directly
    $this->user->addPermission('post.update');

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.update')
            ->exists(),
    )->toBeTrue();

    expect($this->user->hasPermission('post.update'))->toBeTrue();
});

it('hasPermission returns true for both direct and role-based permissions', function (): void {
    // Add admin role (has 'post.update' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('post.create');

    // Should have all three
    expect($this->user->hasPermission('post.update'))->toBeTrue(); // via role
    expect($this->user->hasPermission('post.delete'))->toBeTrue(); // via role
    expect($this->user->hasPermission('post.create'))->toBeTrue(); // direct
    expect($this->user->hasPermission('post.view'))->toBeFalse(); // neither
});

it('syncPermissions removes existing direct permissions but keeps role permissions', function (): void {
    // Add admin role (has 'post.update' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('post.create');

    // Sync to only 'view_post' (not in any role)
    $this->user->syncPermissions(['post.view']);

    // Should only have 'view_post' directly
    expect($this->user->directPermissions()->count())->toBe(1);

    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.view')
            ->exists(),
    )->toBeTrue();

    // Should not have 'create_post' anymore
    expect(
        $this->user
            ->directPermissions()
            ->where('name', 'post.create')
            ->exists(),
    )->toBeFalse();

    // But should still have 'post.update' and 'delete_post' via role
    expect($this->user->hasPermission('post.update'))->toBeTrue();
    expect($this->user->hasPermission('post.delete'))->toBeTrue();
});

it('attempting to add already directly assigned permission throws error', function (): void {
    // Add 'post.update' directly
    $this->user->addPermission('post.update');

    // Try to add again - should fail with database constraint error
    expect(fn () => $this->user->addPermission('post.update'))
        ->toThrow(RuntimeException::class);
});

it('attempting to remove non-assigned permission throws error', function (): void {
    // Try to remove permission that user doesn't have
    expect(fn () => $this->user->removePermission('post.update'))
        ->toThrow(RuntimeException::class);
});

it('getAllPermissions returns both direct and role-based permissions', function (): void {
    // Add admin role (has 'post.update' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('post.create');

    // Get all permissions
    $allPermissions = $this->user->getAllPermissions();

    // Should have 3 permissions total
    expect($allPermissions->count())->toBe(3);

    // Extract permission names
    $permissionNames = $allPermissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($permissionNames)->toBe([
        'post.create',
        'post.delete',
        'post.update',
    ]);
});
