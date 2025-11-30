<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    // Sync roles and permissions before each test
    $this->artisan('sync:roles')->assertExitCode(0);
    $this->artisan('sync:permissions')->assertExitCode(0);
});

it('removes orphaned users_roles records when user is deleted', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();

    $user->addRole($role->name);

    // Manually delete user bypassing Eloquent to create orphan
    DB::table('users')->where('id', $user->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('users_roles', [
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('users_roles', [
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);
});

it('removes orphaned users_roles records when role is deleted', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();

    $user->addRole($role->name);

    $roleId = $role->id;

    // Manually delete role bypassing Eloquent to create orphan
    DB::table('roles')->where('id', $role->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('users_roles', [
        'user_id' => $user->id,
        'role_id' => $roleId,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('users_roles', [
        'user_id' => $user->id,
        'role_id' => $roleId,
    ]);
});

it('removes orphaned users_permissions records when user is deleted', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $permission = Permission::query()->first();

    // Manually insert permission to avoid cache issues
    DB::table('users_permissions')->insert([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
    ]);

    // Manually delete user bypassing Eloquent to create orphan
    DB::table('users')->where('id', $user->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('users_permissions', [
        'user_id' => $user->id,
        'permission_id' => $permission->id,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('users_permissions', [
        'user_id' => $user->id,
        'permission_id' => $permission->id,
    ]);
});

it('removes orphaned users_permissions records when permission is deleted', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $permission = Permission::query()->first();

    $permissionId = $permission->id;

    // Manually insert permission to avoid cache issues
    DB::table('users_permissions')->insert([
        'user_id' => $user->id,
        'permission_id' => $permissionId,
    ]);

    // Manually delete permission bypassing Eloquent to create orphan
    DB::table('permissions')->where('id', $permission->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('users_permissions', [
        'user_id' => $user->id,
        'permission_id' => $permissionId,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('users_permissions', [
        'user_id' => $user->id,
        'permission_id' => $permissionId,
    ]);
});

it('removes orphaned roles_permissions records when role is deleted', function (): void {
    $role = Role::query()->first();
    $permission = Permission::query()->first();

    // Manually add permission to role
    DB::table('roles_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    $roleId = $role->id;

    // Manually delete role bypassing Eloquent to create orphan
    DB::table('roles')->where('id', $role->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('roles_permissions', [
        'role_id' => $roleId,
        'permission_id' => $permission->id,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('roles_permissions', [
        'role_id' => $roleId,
        'permission_id' => $permission->id,
    ]);
});

it('removes orphaned roles_permissions records when permission is deleted', function (): void {
    $role = Role::query()->first();
    $permission = Permission::query()->first();

    // Manually add permission to role
    DB::table('roles_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    $permissionId = $permission->id;

    // Manually delete permission bypassing Eloquent to create orphan
    DB::table('permissions')->where('id', $permission->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('roles_permissions', [
        'role_id' => $role->id,
        'permission_id' => $permissionId,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify orphan is removed
    $this->assertDatabaseMissing('roles_permissions', [
        'role_id' => $role->id,
        'permission_id' => $permissionId,
    ]);
});

it('does not remove valid pivot records during cleanup', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();
    $permission = Permission::query()->first();

    $user->addRole($role->name);

    // Manually insert permission to avoid cache issues
    DB::table('users_permissions')->insert([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
    ]);

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify valid records still exist
    $this->assertDatabaseHas('users_roles', [
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    $this->assertDatabaseHas('users_permissions', [
        'user_id' => $user->id,
        'permission_id' => $permission->id,
    ]);
});

it('dry-run option does not delete orphaned records', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();

    $user->addRole($role->name);

    // Manually delete user bypassing Eloquent to create orphan
    DB::table('users')->where('id', $user->id)->delete();

    // Verify orphan exists
    $this->assertDatabaseHas('users_roles', [
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    // Run cleanup with dry-run
    $this->artisan('permissions:cleanup-orphans --dry-run')
        ->expectsOutput('Running in dry-run mode. No records will be deleted.')
        ->assertExitCode(0);

    // Verify orphan still exists
    $this->assertDatabaseHas('users_roles', [
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);
});

it('displays correct count when orphans are found in dry-run mode', function (): void {
    $user1 = User::query()->create(['name' => 'John Doe']);
    $user2 = User::query()->create(['name' => 'Jane Doe']);
    $role = Role::query()->first();

    $user1->addRole($role->name);
    $user2->addRole($role->name);

    // Manually delete users bypassing Eloquent to create orphans
    DB::table('users')->whereIn('id', [$user1->id, $user2->id])->delete();

    // Run cleanup with dry-run
    $this->artisan('permissions:cleanup-orphans --dry-run')
        ->expectsOutputToContain('Found 2 orphaned record(s) that would be deleted.')
        ->assertExitCode(0);
});

it('displays message when no orphans are found', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();

    $user->addRole($role->name);

    // Run cleanup (no orphans to clean)
    $this->artisan('permissions:cleanup-orphans')
        ->expectsOutputToContain('No orphaned records found.')
        ->assertExitCode(0);
});

it('displays message when no orphans are found in dry-run mode', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();

    $user->addRole($role->name);

    // Run cleanup with dry-run (no orphans)
    $this->artisan('permissions:cleanup-orphans --dry-run')
        ->expectsOutputToContain('No orphaned records found.')
        ->assertExitCode(0);
});

it('cleans up multiple types of orphans in a single run', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);
    $role = Role::query()->first();
    $permission = Permission::query()->first();

    $userId = $user->id;
    $roleId = $role->id;
    $permissionId = $permission->id;

    // Manually insert relationships to avoid cache issues
    DB::table('users_roles')->insert([
        'user_id' => $userId,
        'role_id' => $roleId,
    ]);

    DB::table('users_permissions')->insert([
        'user_id' => $userId,
        'permission_id' => $permissionId,
    ]);

    DB::table('roles_permissions')->insert([
        'role_id' => $roleId,
        'permission_id' => $permissionId,
    ]);

    // Manually delete user and role to create multiple orphans
    DB::table('users')->where('id', $user->id)->delete();
    DB::table('roles')->where('id', $role->id)->delete();

    // Run cleanup
    $this->artisan('permissions:cleanup-orphans')
        ->assertExitCode(0);

    // Verify all orphans are removed
    $this->assertDatabaseMissing('users_roles', [
        'user_id' => $userId,
    ]);

    $this->assertDatabaseMissing('users_permissions', [
        'user_id' => $userId,
    ]);

    $this->assertDatabaseMissing('roles_permissions', [
        'role_id' => $roleId,
    ]);
});

it('handles cleanup when tables are empty', function (): void {
    // Run cleanup on fresh database
    $this->artisan('permissions:cleanup-orphans')
        ->expectsOutputToContain('No orphaned records found.')
        ->assertExitCode(0);
});

it('shows detailed output for each table being checked', function (): void {
    $this->artisan('permissions:cleanup-orphans')
        ->expectsOutputToContain('Checking users_roles table...')
        ->expectsOutputToContain('Checking users_permissions table...')
        ->expectsOutputToContain('Checking roles_permissions table...')
        ->assertExitCode(0);
});
