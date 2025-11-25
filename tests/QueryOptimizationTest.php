<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
    $this->artisan('sync:permissions');
});

it('optimizes syncPermissions', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Enable query logging
    DB::enableQueryLog();

    // Sync multiple permissions
    $user->syncPermissions([
        'user.create',
        'user.view',
        'user.update',
        'user.delete',
    ]);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Filter to only SELECT queries for permissions table
    $permissionQueries = array_filter(
        $queries,
        static fn (array $query): bool => str_contains(
            (string) $query['query'],
            'select',
        ) &&
            str_contains((string) $query['query'], 'permissions') &&
            str_contains((string) $query['query'], 'where "name" in'),
    );

    expect($queries)->toHaveCount(4); // 4 queries for roles and permissions (see syncRoles test)

    // Should use a single whereIn query instead of 4 separate queries
    expect($permissionQueries)->toHaveCount(1);

    // Verify all permissions were synced
    expect(
        $user->hasAllPermissions([
            'user.create',
            'user.view',
            'user.update',
            'user.delete',
        ]),
    )->toBeTrue();
});

it('optimizes syncRoles with single database query', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Enable query logging
    DB::enableQueryLog();

    // Sync multiple roles
    $user->syncRoles([Role::Admin, Role::User]);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Filter to only SELECT queries for roles table
    $roleQueries = array_filter(
        $queries,
        static fn (array $query): bool => str_contains(
            (string) $query['query'],
            'select',
        ) &&
            str_contains((string) $query['query'], 'roles') &&
            str_contains((string) $query['query'], 'where "name" in'),
    );

    expect($queries)->toHaveCount(3);
    // Should use a single whereIn query instead of 3 separate queries
    expect($roleQueries)->toHaveCount(1);

    // Verify all roles were synced
    expect($user->hasAllRoles([Role::Admin, Role::User]))->toBeTrue();
});

it('does not have N+1 queries when loading users with roles', function (): void {
    // Create 10 users manually
    $users = [];

    for ($i = 0; $i < 10; $i++) {
        $users[] = User::query()->create([
            'name' => "User {$i}",
        ]);
    }

    // Assign an Admin role to each user
    foreach ($users as $user) {
        $user->addRole(Role::Admin);
    }

    DB::enableQueryLog();

    // Load users with roles
    $users = User::with('roles')->get();
    $users->each(static fn ($user) => $user->roles);

    $queries = DB::getQueryLog();

    // Should be exactly 2 queries: 1 for users, 1 for roles
    expect($queries)->toHaveCount(2);
});
