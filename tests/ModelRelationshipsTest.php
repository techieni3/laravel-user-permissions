<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Enums\Role as RoleEnum;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
    $this->artisan('sync:permissions');
});

it('role has many-to-many relationship with permissions', function (): void {
    $role = Role::where('name', RoleEnum::Admin->value)->first();
    $permission1 = Permission::where('name', 'create_admin')->first();
    $permission2 = Permission::where('name', 'view_admin')->first();

    $role->permissions()->attach([$permission1->id, $permission2->id]);

    expect($role->permissions)->toHaveCount(2);
    expect($role->permissions->pluck('name')->toArray())->toContain('create_admin', 'view_admin');
});

it('permission has many-to-many relationship with roles', function (): void {
    $permission = Permission::where('name', 'create_admin')->first();
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $editorRole = Role::where('name', RoleEnum::User->value)->first();

    $permission->roles()->attach([$adminRole->id, $editorRole->id]);

    expect($permission->roles)->toHaveCount(2);

    expect($permission->roles->pluck('name')->map(fn ($role) => $role->value)->toArray())->toContain(RoleEnum::Admin->value, RoleEnum::User->value);
});

it('user has many-to-many relationship with roles', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);
    $user->addRole(RoleEnum::User);

    $roles = $user->roles()->get();

    expect($roles)->toHaveCount(2);
    expect($roles->pluck('name')->map(fn ($role) => $role->value)->toArray())->toContain(RoleEnum::Admin->value, RoleEnum::User->value);
});

it('user has many-to-many relationship with direct permissions', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addPermission('create_admin');
    $user->addPermission('view_admin');

    $permissions = $user->directPermissions()->get();

    expect($permissions)->toHaveCount(2);
    expect($permissions->pluck('name')->toArray())->toContain('create_admin', 'view_admin');
});

it('can eager load roles with permissions', function (): void {
    $role = Role::where('name', RoleEnum::Admin->value)->first();
    $permission = Permission::where('name', 'create_admin')->first();
    $role->permissions()->attach($permission->id);

    $loadedRole = Role::with('permissions')->find($role->id);

    expect($loadedRole->relationLoaded('permissions'))->toBeTrue();
    expect($loadedRole->permissions)->toHaveCount(1);
});

it('can eager load permissions with roles', function (): void {
    $permission = Permission::where('name', 'create_admin')->first();
    $adminRole = Role::where('name', RoleEnum::Admin->value)->first();
    $permission->roles()->attach($adminRole->id);

    $loadedPermission = Permission::with('roles')->find($permission->id);

    expect($loadedPermission->relationLoaded('roles'))->toBeTrue();
    expect($loadedPermission->roles)->toHaveCount(1);
});

it('can detach permissions from role', function (): void {
    $role = Role::where('name', RoleEnum::Admin->value)->first();
    $permission = Permission::where('name', 'create_admin')->first();

    $role->permissions()->attach($permission->id);
    expect($role->permissions)->toHaveCount(1);

    $role->permissions()->detach($permission->id);
    $role->refresh();

    expect($role->permissions)->toHaveCount(0);
});

it('can sync permissions on a role', function (): void {
    $role = Role::where('name', RoleEnum::Admin->value)->first();
    $permission1 = Permission::where('name', 'create_admin')->first();
    $permission2 = Permission::where('name', 'view_admin')->first();
    $permission3 = Permission::where('name', 'update_admin')->first();

    $role->permissions()->attach([$permission1->id, $permission2->id]);
    expect($role->permissions)->toHaveCount(2);

    // Sync to different permissions
    $role->permissions()->sync([$permission2->id, $permission3->id]);
    $role->refresh();

    expect($role->permissions)->toHaveCount(2);
    expect($role->permissions->pluck('name')->toArray())->toContain('view_admin', 'update_admin');
    expect($role->permissions->pluck('name')->toArray())->not->toContain('create_admin');
});

it('role model uses correct table name', function (): void {
    $role = new Role;
    expect($role->getTable())->toBe('roles');
});

it('permission model uses correct table name', function (): void {
    $permission = new Permission;
    expect($permission->getTable())->toBe('permissions');
});

it('role model guards id field', function (): void {
    $role = new Role;
    $guarded = $role->getGuarded();

    expect($guarded)->toContain('id');
});

it('permission model guards id field', function (): void {
    $permission = new Permission;
    $guarded = $permission->getGuarded();

    expect($guarded)->toContain('id');
});
