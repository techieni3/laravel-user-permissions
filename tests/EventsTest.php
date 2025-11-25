<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Techieni3\LaravelUserPermissions\Events\PermissionAdded;
use Techieni3\LaravelUserPermissions\Events\PermissionRemoved;
use Techieni3\LaravelUserPermissions\Events\RoleAdded;
use Techieni3\LaravelUserPermissions\Events\RoleRemoved;
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
