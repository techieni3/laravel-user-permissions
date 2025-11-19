<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Enums\Role as RoleEnum;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
    $this->artisan('sync:permissions');
});

it('integrates with Laravel Gate system', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addPermission('create_admin');

    expect($user->hasPermission('create_admin'))->toBeTrue();

    $this->actingAs($user);

    expect(Gate::allows('create_admin'))->toBeTrue();
    expect(Gate::denies('edit_admin'))->toBeTrue();
});

it('Gate works with permissions from roles', function (): void {
    $user = User::create(['name' => 'John Doe']);

    // Add permission to Admin role
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $permission = Permission::where('name', 'create_admin')->first();
    $adminRole->permissions()->attach($permission->id);

    // Assign role to user
    $user->addRole(RoleEnum::Admin);

    $this->actingAs($user);

    expect(Gate::allows('create_admin'))->toBeTrue();
});

it('handles complex permission hierarchy', function (): void {
    // Setup: Admin role has full permissions, Editor has limited permissions
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $editorRole = Role::where('name', RoleEnum::User->value)->first();

    $createPerm = Permission::where('name', 'create_admin')->first();
    $viewPerm = Permission::where('name', 'view_admin')->first();
    $editPerm = Permission::where('name', 'update_admin')->first();
    $deletePerm = Permission::where('name', 'delete_admin')->first();

    $adminRole->permissions()->attach([$createPerm->id, $viewPerm->id, $editPerm->id, $deletePerm->id]);
    $editorRole->permissions()->attach([$viewPerm->id, $editPerm->id]);

    // Create users with different roles
    $admin = User::create(['name' => 'Admin User']);
    $admin->addRole(RoleEnum::Admin);

    $editor = User::create(['name' => 'Editor User']);
    $editor->addRole(RoleEnum::User);

    // Admin should have all permissions
    expect($admin->hasAllPermissions(['create_admin', 'view_admin', 'update_admin', 'delete_admin']))->toBeTrue();

    // Editor should have limited permissions
    expect($editor->hasAllPermissions(['view_admin', 'update_admin']))->toBeTrue();
    expect($editor->hasPermission('create_admin'))->toBeFalse();
    expect($editor->hasPermission('delete_admin'))->toBeFalse();
});

it('handles user with multiple roles and direct permissions', function (): void {
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $editorRole = Role::where('name', RoleEnum::User->value)->first();

    $createPerm = Permission::where('name', 'create_admin')->first();
    $viewPerm = Permission::where('name', 'view_admin')->first();
    $editPerm = Permission::where('name', 'update_admin')->first();

    // Admin role gets create permission
    $adminRole->permissions()->attach($createPerm->id);

    // Editor role gets view permission
    $editorRole->permissions()->attach($viewPerm->id);

    // User has both roles plus direct edit permission
    $user = User::create(['name' => 'Multi-role User']);
    $user->addRole(RoleEnum::Admin);
    $user->addRole(RoleEnum::User);
    $user->addPermission('update_admin');

    // User should have all three permissions
    expect($user->hasPermission('create_admin'))->toBeTrue(); // from Admin role
    expect($user->hasPermission('view_admin'))->toBeTrue(); // from Editor role
    expect($user->hasPermission('update_admin'))->toBeTrue(); // direct permission

    $allPermissions = $user->getAllPermissions();
    expect($allPermissions)->toHaveCount(3);
});

it('permissions are not duplicated in getAllPermissions', function (): void {
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $createPerm = Permission::where('name', 'create_admin')->first();

    // Add same permission to role
    $adminRole->permissions()->attach($createPerm->id);

    $user = User::create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);

    // Also add same permission directly
    expect(fn () => $user->addPermission('create_admin'))->toThrow(RuntimeException::class);

    // Should not have duplicates
    $allPermissions = $user->getAllPermissions();
    $createAdminCount = $allPermissions->where('name', 'create_admin')->count();

    expect($createAdminCount)->toBe(1);
});

it('syncing roles removes old role permissions from cache', function (): void {
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $editorRole = Role::where('name', RoleEnum::User->value)->first();

    $createPerm = Permission::where('name', 'create_admin')->first();
    $viewPerm = Permission::where('name', 'view_admin')->first();

    $adminRole->permissions()->attach($createPerm->id);
    $editorRole->permissions()->attach($viewPerm->id);

    $user = User::create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);

    expect($user->hasPermission('create_admin'))->toBeTrue();

    // Sync to Editor role only
    $user->syncRoles([RoleEnum::User]);

    expect($user->hasPermission('create_admin'))->toBeFalse();
    expect($user->hasPermission('view_admin'))->toBeTrue();
});

it('can query users with specific role and permission combination', function (): void {
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $createPerm = Permission::where('name', 'create_admin')->first();
    $adminRole->permissions()->attach($createPerm->id);

    $user1 = User::create(['name' => 'Admin with extra perm']);
    $user1->addRole(RoleEnum::Admin);
    $user1->addPermission('view_admin');

    $user2 = User::create(['name' => 'Just Admin']);
    $user2->addRole(RoleEnum::Admin);

    $user3 = User::create(['name' => 'Editor with view']);
    $user3->addRole(RoleEnum::User);
    $user3->addPermission('view_admin');

    // Find all admins with view_admin permission
    $adminsWithView = User::role(RoleEnum::Admin)->permission('view_admin')->get();

    expect($adminsWithView)->toHaveCount(1);
    expect($adminsWithView->first()->name)->toBe('Admin with extra perm');
});

it('cache is properly isolated between different users', function (): void {
    $user1 = User::create(['name' => 'User 1']);
    $user1->addPermission('create_admin');

    $user2 = User::create(['name' => 'User 2']);
    $user2->addPermission('view_admin');

    // Load permissions for both users
    $user1Permissions = $user1->getAllPermissions();
    $user2Permissions = $user2->getAllPermissions();

    // Verify they're different
    expect($user1Permissions->pluck('name')->toArray())->toContain('create_admin');
    expect($user1Permissions->pluck('name')->toArray())->not->toContain('view_admin');

    expect($user2Permissions->pluck('name')->toArray())->toContain('view_admin');
    expect($user2Permissions->pluck('name')->toArray())->not->toContain('create_admin');
});

it('supports mass permission assignment', function (): void {
    $user = User::create(['name' => 'John Doe']);

    $permissions = ['create_admin', 'view_admin', 'update_admin', 'delete_admin'];

    foreach ($permissions as $permission) {
        $user->addPermission($permission);
    }

    expect($user->hasAllPermissions($permissions))->toBeTrue();
    expect($user->getAllPermissions())->toHaveCount(4);
});

it('works correctly after user model refresh', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addPermission('create_admin');

    expect($user->hasPermission('create_admin'))->toBeTrue();

    // Refresh the model
    $user->refresh();

    // Should still work
    expect($user->hasPermission('create_admin'))->toBeTrue();
});
