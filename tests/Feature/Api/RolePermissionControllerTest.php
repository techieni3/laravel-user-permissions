<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
    $this->artisan('sync:permissions');
});

it('can show permissions for a role', function (): void {
    $role = Role::query()->first();
    $user = User::query()->create([
        'name' => 'Test User',
    ]);

    $this->actingAs($user)
        ->getJson(route('permissions.api.roles.permissions.show', $role))
        ->assertOk()
        ->assertJsonStructure([
            'role' => [
                'id',
                'name',
            ],
            'models',
            'available_permissions',
        ]);
});

it('can update permissions for a role', function (): void {

    $role = Role::query()->first();
    $permissions = Permission::query()->take(2)->pluck('id')->toArray();

    $user = User::query()->create([
        'name' => 'Test User 2',
    ]);

    $this->actingAs($user)
        ->putJson(route('permissions.api.roles.permissions.update', $role), [
            'permissions' => $permissions,
        ])
        ->assertOk();

    expect($role->permissions()->pluck('permissions.id')->toArray())
        ->toEqualCanonicalizing($permissions);
});
