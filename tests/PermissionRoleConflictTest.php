<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    // Create test permissions
    Permission::create(['name' => 'edit_post']);
    Permission::create(['name' => 'view_post']);
    Permission::create(['name' => 'delete_post']);
    Permission::create(['name' => 'create_post']);

    // Create test roles
    $this->adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
    $this->editorRole = Role::create(['name' => 'user', 'display_name' => 'Editor']);

    // Assign permissions to admin role
    $this->adminRole->permissions()->attach([
        Permission::where('name', 'edit_post')->first()->id,
        Permission::where('name', 'delete_post')->first()->id,
    ]);

    // Assign permissions to editor role
    $this->editorRole->permissions()->attach([
        Permission::where('name', 'view_post')->first()->id,
    ]);

    // Create test user
    $this->user = User::create([
        'name' => 'Test User',
    ]);
});

it('user cannot add permission directly if already granted via role', function (): void {
    // Assign admin role (which has 'edit_post' permission)
    $this->user->addRole('admin');

    // Try to add 'edit_post' directly - should fail
    expect(fn () => $this->user->addPermission('edit_post'))
        ->toThrow(RuntimeException::class);
});

it('user can add permission directly if not granted via role', function (): void {
    // Assign editor role (which has 'view_post' but not 'edit_post')
    $this->user->addRole('user');

    // Add 'edit_post' directly - should succeed
    $this->user->addPermission('edit_post');

    expect($this->user->directPermissions()->where('name', 'edit_post')->exists())->toBeTrue();
    expect($this->user->hasPermission('edit_post'))->toBeTrue();
});

it('user cannot remove permission granted via role without removing role', function (): void {
    // Assign admin role (which has 'edit_post' permission)
    $this->user->addRole('admin');

    // Try to remove 'edit_post' - should fail
    expect(fn () => $this->user->removePermission('edit_post'))
        ->toThrow(RuntimeException::class);

    // User should still have the permission
    expect($this->user->hasPermission('edit_post'))->toBeTrue();
});

it('user can remove direct permission without removing role', function (): void {
    // Assign editor role (which has 'view_post')
    $this->user->addRole('user');

    expect($this->user->hasPermission('view_post'))->toBeTrue();

    // Add 'edit_post' directly
    $this->user->addPermission('edit_post');

    // Remove 'edit_post' directly - should succeed
    $this->user->removePermission('edit_post');

    expect($this->user->hasPermission('edit_post'))->toBeFalse();

    // Should still have 'view_post' from editor role
    expect($this->user->hasPermission('view_post'))->toBeTrue();
});

it('sync permissions that are already not granted via role', function (): void {
    // Assign admin role (which has 'edit_post' and 'delete_post')
    $this->user->addRole('admin');

    expect($this->user->directPermissions()->count())->toBe(0);

    $this->user->syncPermissions(['edit_post', 'create_post']);

    // 'edit_post' - should get added to direct premissions
    expect($this->user->directPermissions()->count())->toBe(1);
});

it('user can sync permissions that are not granted via role', function (): void {
    // Assign editor role (which has 'view_post')
    $this->user->addRole('user');

    // Sync permissions not in role - should succeed
    $this->user->syncPermissions(['edit_post', 'create_post']);

    expect($this->user->directPermissions()->count())->toBe(2);
    expect($this->user->directPermissions()->where('name', 'edit_post')->exists())->toBeTrue();
    expect($this->user->directPermissions()->where('name', 'create_post')->exists())->toBeTrue();

    // Should not have 'view_post' directly (only via role)
    expect($this->user->directPermissions()->where('name', 'view_post')->exists())->toBeFalse();
    // But should still have access to it via role
    expect($this->user->hasPermission('view_post'))->toBeTrue();
});

it('user with multiple roles cannot add permission if any role grants it', function (): void {
    // Assign both admin and editor roles
    $this->user->addRole('admin');  // Has 'edit_post' and 'delete_post'
    $this->user->addRole('user'); // Has 'view_post'

    // Try to add 'edit_post' - should fail (granted by admin role)
    expect(fn () => $this->user->addPermission('edit_post'))
        ->toThrow(RuntimeException::class);

    // Try to add 'view_post' - should fail (granted by editor role)
    expect(fn () => $this->user->addPermission('view_post'))
        ->toThrow(RuntimeException::class);

    // Should be able to add 'create_post' (not granted by any role)
    $this->user->addPermission('create_post');
    expect($this->user->directPermissions()->where('name', 'create_post')->exists())->toBeTrue();
});

it('adding role does not interfere with existing direct permissions', function (): void {
    // Add 'create_post' directly
    $this->user->addPermission('create_post');

    // Add editor role (which has 'view_post')
    $this->user->addRole('user');

    // Should have both permissions
    expect($this->user->hasPermission('create_post'))->toBeTrue();
    expect($this->user->hasPermission('view_post'))->toBeTrue();

    // 'create_post' should still be direct
    expect($this->user->directPermissions()->where('name', 'create_post')->exists())->toBeTrue();
});

it('removing role allows adding previously restricted permissions', function (): void {
    // Assign admin role (which has 'edit_post')
    $this->user->addRole('admin');

    // Cannot add 'edit_post' directly
    expect(fn () => $this->user->addPermission('edit_post'))
        ->toThrow(RuntimeException::class);

    // Remove admin role
    $this->user->removeRole('admin');

    // Now should be able to add 'edit_post' directly
    $this->user->addPermission('edit_post');

    expect($this->user->directPermissions()->where('name', 'edit_post')->exists())->toBeTrue();
    expect($this->user->hasPermission('edit_post'))->toBeTrue();
});

it('hasPermission returns true for both direct and role-based permissions', function (): void {
    // Add admin role (has 'edit_post' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('create_post');

    // Should have all three
    expect($this->user->hasPermission('edit_post'))->toBeTrue();    // via role
    expect($this->user->hasPermission('delete_post'))->toBeTrue();  // via role
    expect($this->user->hasPermission('create_post'))->toBeTrue();  // direct
    expect($this->user->hasPermission('view_post'))->toBeFalse();   // neither
});

it('syncPermissions removes existing direct permissions but keeps role permissions', function (): void {
    // Add admin role (has 'edit_post' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('create_post');

    // Sync to only 'view_post' (not in any role)
    $this->user->syncPermissions(['view_post']);

    // Should only have 'view_post' directly
    expect($this->user->directPermissions()->count())->toBe(1);
    expect($this->user->directPermissions()->where('name', 'view_post')->exists())->toBeTrue();

    // Should not have 'create_post' anymore
    expect($this->user->directPermissions()->where('name', 'create_post')->exists())->toBeFalse();

    // But should still have 'edit_post' and 'delete_post' via role
    expect($this->user->hasPermission('edit_post'))->toBeTrue();
    expect($this->user->hasPermission('delete_post'))->toBeTrue();
});

it('attempting to add already directly assigned permission throws error', function (): void {
    // Add 'edit_post' directly
    $this->user->addPermission('edit_post');

    // Try to add again - should fail with database constraint error
    expect(fn () => $this->user->addPermission('edit_post'))
        ->toThrow(RuntimeException::class);
});

it('attempting to remove non-assigned permission throws error', function (): void {
    // Try to remove permission that user doesn't have
    expect(fn () => $this->user->removePermission('edit_post'))
        ->toThrow(RuntimeException::class);
});

it('getAllPermissions returns both direct and role-based permissions', function (): void {
    // Add admin role (has 'edit_post' and 'delete_post')
    $this->user->addRole('admin');

    // Add 'create_post' directly
    $this->user->addPermission('create_post');

    // Get all permissions
    $allPermissions = $this->user->getAllPermissions();

    // Should have 3 permissions total
    expect($allPermissions->count())->toBe(3);

    // Extract permission names
    $permissionNames = $allPermissions->pluck('name')->sort()->values()->all();

    expect($permissionNames)->toBe(['create_post', 'delete_post', 'edit_post']);
});
