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
    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');

    expect($user->hasPermission('admin.create'))->toBeTrue();

    $this->actingAs($user);

    expect(Gate::allows('admin.create'))->toBeTrue();
    expect(Gate::denies('admin.update'))->toBeTrue();
});

it('Gate works with permissions from roles', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Add permission to Admin role
    $adminRole = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission = Permission::query()->where('name', 'admin.create')->first();
    $adminRole->permissions()->attach($permission->id);

    // Assign role to user
    $user->addRole(RoleEnum::Admin);

    $this->actingAs($user);

    expect(Gate::allows('admin.create'))->toBeTrue();
});

it('handles complex permission hierarchy', function (): void {
    // Setup: Admin role has full permissions, Editor has limited permissions
    $adminRole = Role::query()->where('name', RoleEnum::Admin)->first();
    $editorRole = Role::query()->where('name', RoleEnum::User)->first();

    $createPerm = Permission::query()->where('name', 'admin.create')->first();
    $viewPerm = Permission::query()->where('name', 'admin.view')->first();
    $editPerm = Permission::query()->where('name', 'admin.update')->first();
    $deletePerm = Permission::query()->where('name', 'admin.delete')->first();

    $adminRole
        ->permissions()
        ->attach([
            $createPerm->id,
            $viewPerm->id,
            $editPerm->id,
            $deletePerm->id,
        ]);

    $editorRole->permissions()->attach([$viewPerm->id, $editPerm->id]);

    // Create users with different roles
    $admin = User::query()->create(['name' => 'Admin User']);
    $admin->addRole(RoleEnum::Admin);

    $editor = User::query()->create(['name' => 'Editor User']);
    $editor->addRole(RoleEnum::User);

    // Admin should have all permissions
    expect($admin->hasAllPermissions([
        'admin.create',
        'admin.view',
        'admin.update',
        'admin.delete',
    ]))->toBeTrue();

    // Editor should have limited permissions
    expect($editor->hasAllPermissions(['admin.view', 'admin.update']))
        ->toBeTrue();
    expect($editor->hasPermission('admin.create'))->toBeFalse();
    expect($editor->hasPermission('admin.delete'))->toBeFalse();
});

it('handles user with multiple roles and direct permissions', function (): void {
    $adminRole = Role::query()->where('name', RoleEnum::Admin)->first();
    $editorRole = Role::query()->where('name', RoleEnum::User)->first();

    $createPerm = Permission::query()->where('name', 'admin.create')->first();
    $viewPerm = Permission::query()->where('name', 'admin.view')->first();
    $editPerm = Permission::query()->where('name', 'admin.update')->first();

    // Admin role gets create permission
    $adminRole->permissions()->attach($createPerm->id);

    // Editor role gets view permission
    $editorRole->permissions()->attach($viewPerm->id);

    // User has both roles plus direct edit permission
    $user = User::query()->create(['name' => 'Multi-role User']);
    $user->addRole(RoleEnum::Admin);
    $user->addRole(RoleEnum::User);
    $user->addPermission('admin.update');

    // User should have all three permissions
    expect($user->hasPermission('admin.create'))->toBeTrue(); // from Admin role
    expect($user->hasPermission('admin.view'))->toBeTrue(); // from Editor role
    expect($user->hasPermission('admin.update'))->toBeTrue(); // direct permission

    $allPermissions = $user->getPermissions();
    expect($allPermissions)->toHaveCount(3);
});

it('permissions are not duplicated in getPermissions', function (): void {
    $adminRole = Role::query()->where('name', RoleEnum::Admin->value)->first();
    $createPerm = Permission::query()->where('name', 'admin.create')->first();

    // Add same permission to role
    $adminRole->permissions()->attach($createPerm->id);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);

    // Also add the same permission directly
    expect(static fn () => $user->addPermission('admin.create'))
        ->toThrow(RuntimeException::class);

    // Should not have duplicates
    $allPermissions = $user->getPermissions();
    $createAdminCount = $allPermissions->where('name', 'admin.create')->count();

    expect($createAdminCount)->toBe(1);
});

it('syncing roles removes old role permissions from cache', function (): void {
    $adminRole = Role::query()->where('name', RoleEnum::Admin->value)->first();
    $editorRole = Role::query()->where('name', RoleEnum::User->value)->first();

    $createPerm = Permission::query()->where('name', 'admin.create')->first();
    $viewPerm = Permission::query()->where('name', 'admin.view')->first();

    $adminRole->permissions()->attach($createPerm->id);
    $editorRole->permissions()->attach($viewPerm->id);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);

    expect($user->hasPermission('admin.create'))->toBeTrue();

    // Sync to Editor role only
    $user->syncRoles([RoleEnum::User]);

    expect($user->hasPermission('admin.create'))->toBeFalse();
    expect($user->hasPermission('admin.view'))->toBeTrue();
});

it('can query users with specific role and permission combination', function (): void {
    $adminRole = Role::query()->where('name', RoleEnum::Admin->value)->first();
    $createPerm = Permission::query()->where('name', 'admin.create')->first();
    $adminRole->permissions()->attach($createPerm->id);

    $user1 = User::query()->create(['name' => 'Admin with extra perm']);
    $user1->addRole(RoleEnum::Admin);
    $user1->addPermission('admin.view');

    $user2 = User::query()->create(['name' => 'Just Admin']);
    $user2->addRole(RoleEnum::Admin);

    $user3 = User::query()->create(['name' => 'Editor with view']);
    $user3->addRole(RoleEnum::User);
    $user3->addPermission('admin.view');

    // Find all admins with view_admin permission
    $adminsWithView = User::role(RoleEnum::Admin)
        ->permission('admin.view')
        ->get();

    expect($adminsWithView)->toHaveCount(1);
    expect($adminsWithView->first()->name)->toBe('Admin with extra perm');
});

it('cache is properly isolated between different users', function (): void {
    $user1 = User::query()->create(['name' => 'User 1']);
    $user1->addPermission('admin.create');

    $user2 = User::query()->create(['name' => 'User 2']);
    $user2->addPermission('admin.view');

    // Load permissions for both users
    $user1Permissions = $user1->getPermissions();
    $user2Permissions = $user2->getPermissions();

    // Verify they're different
    expect($user1Permissions->pluck('name')->toArray())
        ->toContain('admin.create');
    expect($user1Permissions->pluck('name')->toArray())
        ->not
        ->toContain('admin.view');

    expect($user2Permissions->pluck('name')->toArray())
        ->toContain('admin.view');
    expect($user2Permissions->pluck('name')->toArray())
        ->not
        ->toContain('admin.create');
});

it('supports mass permission assignment', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    $permissions = [
        'admin.create',
        'admin.view',
        'admin.update',
        'admin.delete',
    ];

    foreach ($permissions as $permission) {
        $user->addPermission($permission);
    }

    expect($user->hasAllPermissions($permissions))->toBeTrue();
    expect($user->getPermissions())->toHaveCount(4);
});

it('works correctly after user model refresh', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');

    expect($user->hasPermission('admin.create'))->toBeTrue();

    // Refresh the model
    $user->refresh();

    // Should still work
    expect($user->hasPermission('admin.create'))->toBeTrue();
});
