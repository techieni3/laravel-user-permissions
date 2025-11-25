<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
    $this->artisan('sync:permissions');
});

it('can show access for a user', function (): void {
    $targetUser = User::query()->create([
        'name' => 'Target User',
    ]);

    $admin = User::query()->create([
        'name' => 'Admin User',
    ]);

    $this->actingAs($admin)
        ->getJson(route('permissions.api.user-access.show', $targetUser))
        ->assertOk()
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
            ],
            'roles' => [
                '*' => [
                    'id',
                    'name',
                    'permission_ids',
                ],
            ],
            'permissions' => [
                '*' => [
                    'id',
                    'name',
                ],
            ],
            'user_role_ids',
            'user_permission_ids',
        ]);
});

it('can update access for a user', function (): void {
    $targetUser = User::query()->create([
        'name' => 'Target User',
    ]);

    $admin = User::query()->create([
        'name' => 'Admin User',
    ]);

    $role = Role::query()->first();
    $permission = Permission::query()->first();

    $this->actingAs($admin)
        ->putJson(route('permissions.api.user-access.update', $targetUser), [
            'roles' => [$role->id],
            'permissions' => [$permission->id],
        ])
        ->assertOk();

    expect($targetUser->roles()->pluck('roles.id')->toArray())
        ->toContain($role->id)
        ->and($targetUser->directPermissions()->pluck('permissions.id')->toArray())
        ->toContain($permission->id);
});
