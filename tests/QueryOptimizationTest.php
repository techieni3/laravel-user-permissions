<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles')->assertExitCode(0);
    $this->artisan('sync:permissions')->assertExitCode(0);
});

it('optimizes syncPermissions with single database query', function (): void {
    $user = User::create(['name' => 'John Doe']);

    // Enable query logging
    DB::enableQueryLog();

    // Sync multiple permissions
    $user->syncPermissions(['create_user', 'view_user', 'update_user', 'delete_user']);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Filter to only SELECT queries for permissions table
    $permissionQueries = array_filter($queries, static fn (array $query): bool => str_contains((string) $query['query'], 'select') &&
               str_contains((string) $query['query'], 'permissions') &&
               str_contains((string) $query['query'], 'where "name" in'));

    expect($queries)->toHaveCount(4); // 4 queries for roles and permissions (see syncRoles test)

    // Should use a single whereIn query instead of 4 separate queries
    expect($permissionQueries)->toHaveCount(1);

    // Verify all permissions were synced
    expect($user->hasAllPermissions(['create_user', 'view_user', 'update_user', 'delete_user']))->toBeTrue();
});

it('optimizes syncRoles with single database query', function (): void {
    $user = User::create(['name' => 'John Doe']);

    // Enable query logging
    DB::enableQueryLog();

    // Sync multiple roles
    $user->syncRoles([Role::Admin, Role::User]);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Filter to only SELECT queries for roles table
    $roleQueries = array_filter($queries, static fn (array $query): bool => str_contains((string) $query['query'], 'select') &&
               str_contains((string) $query['query'], 'roles') &&
               str_contains((string) $query['query'], 'where "name" in'));

    // Should use a single whereIn query instead of 3 separate queries
    expect($roleQueries)->toHaveCount(1);

    // Verify all roles were synced
    expect($user->hasAllRoles([Role::Admin, Role::User]))->toBeTrue();
});

it('throws detailed error when bulk permission verification fails', function (): void {
    $user = User::create(['name' => 'John Doe']);

    // Try to sync permissions that don't exist
    $user->syncPermissions(['fake_permission_1', 'create_admin', 'fake_permission_2']);
})->throws(
    RuntimeException::class,
    'Permissions [fake_permission_1, fake_permission_2] are not synced with the database'
);

it('throws detailed error when bulk role verification fails', function (): void {
    $user = User::create(['name' => 'John Doe']);

    // Manually create an invalid role enum for testing
    // In real scenario, this would be a role that exists in enum but not in database
    // We'll test by trying to sync before database is populated
    DB::table('roles')->truncate();

    expect(static fn () => $user->syncRoles([Role::User]))
        ->toThrow(RuntimeException::class);
});

it('handles empty array gracefully in syncPermissions', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addPermission('create_admin');

    // Sync empty array should clear all permissions
    $user->syncPermissions([]);

    expect($user->directPermissions()->get())->toHaveCount(0);
});

it('handles empty array gracefully in syncRoles', function (): void {
    $user = User::create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);

    // Sync empty array should clear all roles
    $user->syncRoles([]);

    expect($user->getAllRoles())->toHaveCount(0);
});
