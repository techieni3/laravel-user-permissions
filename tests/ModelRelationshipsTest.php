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
    $role = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission1 = Permission::query()->where('name', 'admin.create')->first();
    $permission2 = Permission::query()->where('name', 'admin.view')->first();

    $role->permissions()->attach([$permission1->id, $permission2->id]);

    expect($role->permissions)->toHaveCount(2);

    expect($role->permissions->pluck('name')->toArray())
        ->toContain('admin.create', 'admin.view');
});

it('permission has many-to-many relationship with roles', function (): void {
    $permission = Permission::query()->where('name', 'admin.create')->first();
    $adminRole = Role::query()->where('name', RoleEnum::Admin)->first();
    $editorRole = Role::query()->where('name', RoleEnum::User)->first();

    $permission->roles()->attach([$adminRole->id, $editorRole->id]);

    expect($permission->roles)->toHaveCount(2);

    expect(
        $permission->roles
            ->pluck('name')
            ->map(static fn ($role) => $role->value)
            ->toArray()
    )
        ->toContain(RoleEnum::Admin->value, RoleEnum::User->value);
});

it('user has many-to-many relationship with roles', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(RoleEnum::Admin);
    $user->addRole(RoleEnum::User);

    $roles = $user->roles()->get();

    expect($roles)->toHaveCount(2);

    expect(
        $roles->pluck('name')
            ->map(static fn ($role) => $role->value)
            ->toArray(),
    )
        ->toContain(RoleEnum::Admin->value, RoleEnum::User->value);
});

it('user has many-to-many relationship with direct permissions', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');
    $user->addPermission('admin.view');

    $permissions = $user->directPermissions()->get();

    expect($permissions)->toHaveCount(2);
    expect($permissions->pluck('name')->toArray())
        ->toContain('admin.create', 'admin.view');
});

it('can eager load roles with permissions', function (): void {
    $role = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission = Permission::query()->where('name', 'admin.create')->first();
    $role->permissions()->attach($permission->id);

    $loadedRole = Role::with('permissions')->find($role->id);

    expect($loadedRole->relationLoaded('permissions'))->toBeTrue();
    expect($loadedRole->permissions)->toHaveCount(1);
});

it('can eager load permissions with roles', function (): void {
    $permission = Permission::query()->where('name', 'admin.create')->first();
    $adminRole = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission->roles()->attach($adminRole->id);

    $loadedPermission = Permission::with('roles')->find($permission->id);

    expect($loadedPermission->relationLoaded('roles'))->toBeTrue();
    expect($loadedPermission->roles)->toHaveCount(1);
});

it('can detach permissions from role', function (): void {
    $role = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission = Permission::query()->where('name', 'admin.create')->first();

    $role->permissions()->attach($permission->id);
    expect($role->permissions)->toHaveCount(1);

    $role->permissions()->detach($permission->id);
    $role->refresh();

    expect($role->permissions)->toHaveCount(0);
});

it('can sync permissions on a role', function (): void {
    $role = Role::query()->where('name', RoleEnum::Admin)->first();
    $permission1 = Permission::query()->where('name', 'admin.create')->first();
    $permission2 = Permission::query()->where('name', 'admin.view')->first();
    $permission3 = Permission::query()->where('name', 'admin.update')->first();

    $role->permissions()->attach([$permission1->id, $permission2->id]);
    expect($role->permissions)->toHaveCount(2);

    // Sync to different permissions
    $role->permissions()->sync([$permission2->id, $permission3->id]);
    $role->refresh();

    expect($role->permissions)->toHaveCount(2);

    expect($role->permissions->pluck('name')->toArray())
        ->toContain(
            'admin.view',
            'admin.update',
        );

    expect($role->permissions->pluck('name')->toArray())
        ->not
        ->toContain('admin.create');
});
