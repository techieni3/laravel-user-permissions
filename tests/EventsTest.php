<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Techieni3\LaravelUserPermissions\Events\PermissionAdded;
use Techieni3\LaravelUserPermissions\Events\PermissionRemoved;
use Techieni3\LaravelUserPermissions\Events\PermissionsSynced;
use Techieni3\LaravelUserPermissions\Events\RoleAdded;
use Techieni3\LaravelUserPermissions\Events\RoleRemoved;
use Techieni3\LaravelUserPermissions\Events\RolesSynced;
use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    // Sync roles and permissions before each test
    $this->artisan('sync:roles')->assertExitCode(0);
    $this->artisan('sync:permissions')->assertExitCode(0);
});

it('dispatches RoleAdded event when a role is added to a user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);

    Event::assertDispatched(RoleAdded::class, static fn (RoleAdded $event): bool => $event->model->id === $user->id
            && $event->role->name->value === 'admin');
});

it('dispatches RoleRemoved event when a role is removed from a user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);

    Event::assertDispatched(RoleAdded::class);

    $user->removeRole(Role::Admin);

    Event::assertDispatched(RoleRemoved::class, static fn (RoleRemoved $event): bool => $event->model->id === $user->id
            && $event->role->name->value === 'admin');
});

it('dispatches PermissionAdded event when a permission is added to a user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');

    Event::assertDispatched(PermissionAdded::class, static fn (PermissionAdded $event): bool => $event->model->id === $user->id
            && $event->permission->name === 'admin.create');
});

it('dispatches PermissionRemoved event when a permission is removed from a user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');

    Event::assertDispatched(PermissionAdded::class);

    $user->removePermission('admin.create');

    Event::assertDispatched(PermissionRemoved::class, static fn (PermissionRemoved $event): bool => $event->model->id === $user->id
            && $event->permission->name === 'admin.create');
});

it('does not dispatch events when events are disabled in config', function (): void {
    Event::fake();

    config(['permissions.events_enabled' => false]);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);
    $user->addPermission('admin.create');

    Event::assertNotDispatched(RoleAdded::class);
    Event::assertNotDispatched(PermissionAdded::class);

    $user->removeRole(Role::Admin);
    $user->removePermission('admin.create');

    Event::assertNotDispatched(RoleRemoved::class);
    Event::assertNotDispatched(PermissionRemoved::class);
});

it('dispatches events when events are enabled in config', function (): void {
    Event::fake();

    config(['permissions.events_enabled' => true]);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);

    Event::assertDispatched(RoleAdded::class);
});

it('event contains correct model instance for role added', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->addRole(Role::Admin);

    Event::assertDispatched(RoleAdded::class, static fn (RoleAdded $event): bool => $event->model instanceof User
            && $event->model->id === $user->id
            && $event->model->name === 'Jane Doe');
});

it('event contains correct role instance for role added', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->addRole(Role::Admin);

    Event::assertDispatched(RoleAdded::class, static fn (RoleAdded $event): bool => $event->role->name->value === 'admin'
            && $event->role->id !== null);
});

it('event contains correct model instance for permission added', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->addPermission('user.update');

    Event::assertDispatched(PermissionAdded::class, static fn (PermissionAdded $event): bool => $event->model instanceof User
            && $event->model->id === $user->id
            && $event->model->name === 'Jane Doe');
});

it('event contains correct permission instance for permission added', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->addPermission('user.update');

    Event::assertDispatched(PermissionAdded::class, static fn (PermissionAdded $event): bool => $event->permission->name === 'user.update'
            && $event->permission->id !== null);
});

it('dispatches multiple RoleAdded events for multiple roles', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addRole(Role::Admin);
    $user->addRole(Role::User);

    Event::assertDispatched(RoleAdded::class, 2);
});

it('dispatches multiple PermissionAdded events for multiple permissions', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->addPermission('admin.create');
    $user->addPermission('admin.update');

    Event::assertDispatched(PermissionAdded::class, 2);
});

it('dispatches RolesSynced event when roles are synced', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncRoles([Role::Admin, Role::User]);

    Event::assertDispatched(RolesSynced::class, static fn (RolesSynced $event): bool => $event->model->id === $user->id
            && $event->synced->count() === 2
            && $event->synced->pluck('name.value')->contains('admin')
            && $event->synced->pluck('name.value')->contains('user'));
});

it('RolesSynced event contains correct attached and detached roles', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Initially add admin role
    $user->addRole(Role::Admin);

    // Now fake events for the sync operation
    Event::fake();

    // Sync to user role (should detach admin, attach user)
    $user->syncRoles([Role::User]);

    Event::assertDispatched(RolesSynced::class, static fn (RolesSynced $event): bool => $event->attached->count() === 1
        && $event->attached->first()->name->value === 'user'
        && $event->detached->count() === 1
        && $event->detached->first()->name->value === 'admin');
});

it('RolesSynced event shows empty previous roles for new user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncRoles([Role::Admin]);

    Event::assertDispatched(RolesSynced::class, static fn (RolesSynced $event): bool => $event->previous->count() === 0
            && $event->attached->count() === 1
            && $event->detached->count() === 0);
});

it('dispatches PermissionsSynced event when permissions are synced', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncPermissions(['admin.create', 'admin.update']);

    Event::assertDispatched(PermissionsSynced::class, static fn (PermissionsSynced $event): bool => $event->model->id === $user->id
            && $event->synced->count() === 2
            && $event->synced->pluck('name')->contains('admin.create')
            && $event->synced->pluck('name')->contains('admin.update'));
});

it('PermissionsSynced event contains correct attached and detached permissions', function (): void {
    $user = User::query()->create(['name' => 'John Doe']);

    // Initially add permissions
    $user->addPermission('admin.create');
    $user->addPermission('admin.update');

    // Now fake events for the sync operation
    Event::fake();

    // Sync to different permissions (should detach previous, attach new)
    $user->syncPermissions(['user.create', 'user.update']);

    Event::assertDispatched(PermissionsSynced::class, static fn (PermissionsSynced $event): bool => $event->attached->count() === 2
        && $event->attached->pluck('name')->contains('user.create')
        && $event->attached->pluck('name')->contains('user.update')
        && $event->detached->count() === 2
        && $event->detached->pluck('name')->contains('admin.create')
        && $event->detached->pluck('name')->contains('admin.update'));
});

it('PermissionsSynced event shows empty previous permissions for new user', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncPermissions(['admin.create']);

    Event::assertDispatched(PermissionsSynced::class, static fn (PermissionsSynced $event): bool => $event->previous->count() === 0
            && $event->attached->count() === 1
            && $event->detached->count() === 0);
});

it('does not dispatch RolesSynced event when events are disabled', function (): void {
    Event::fake();

    config(['permissions.events_enabled' => false]);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncRoles([Role::Admin]);

    Event::assertNotDispatched(RolesSynced::class);
});

it('does not dispatch PermissionsSynced event when events are disabled', function (): void {
    Event::fake();

    config(['permissions.events_enabled' => false]);

    $user = User::query()->create(['name' => 'John Doe']);
    $user->syncPermissions(['admin.create']);

    Event::assertNotDispatched(PermissionsSynced::class);
});

it('RolesSynced event contains correct model instance', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->syncRoles([Role::Admin]);

    Event::assertDispatched(RolesSynced::class, static fn (RolesSynced $event): bool => $event->model instanceof User
            && $event->model->id === $user->id
            && $event->model->name === 'Jane Doe');
});

it('PermissionsSynced event contains correct model instance', function (): void {
    Event::fake();

    $user = User::query()->create(['name' => 'Jane Doe']);
    $user->syncPermissions(['user.update']);

    Event::assertDispatched(PermissionsSynced::class, static fn (PermissionsSynced $event): bool => $event->model instanceof User
            && $event->model->id === $user->id
            && $event->model->name === 'Jane Doe');
});
