<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->user = User::query()->create(['name' => 'Test User']);
});

it('allows access when gate is not configured', function (): void {
    config(['permissions.dashboard.gate' => null]);

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertSuccessful();
});

it('allows access when gate is empty string', function (): void {
    config(['permissions.dashboard.gate' => '']);

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertSuccessful();
});

it('denies access when gate is not defined', function (): void {
    config(['permissions.dashboard.gate' => 'undefinedGate']);

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertStatus(500);
});

it('allows access when gate check passes', function (): void {
    config(['permissions.dashboard.gate' => 'viewPermissionsDashboard']);

    Gate::define('viewPermissionsDashboard', static fn ($user): true => true);

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertSuccessful();
});

it('denies access when gate check fails', function (): void {
    config(['permissions.dashboard.gate' => 'viewPermissionsDashboard']);

    Gate::define('viewPermissionsDashboard', static fn ($user): false => false);

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertStatus(403)
        ->assertSee('Unauthorized access to permissions dashboard');
});

it('can use role-based gate authorization', function (): void {
    config(['permissions.dashboard.gate' => 'viewPermissionsDashboard']);

    // Define gate that checks for admin role
    Gate::define('viewPermissionsDashboard', static fn ($user) => $user->hasRole('admin'));

    // User without admin role
    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertStatus(403);

    $this->artisan('sync:roles')->assertExitCode(0);
    // User with admin role
    $this->user->addRole('admin');

    $this->actingAs($this->user)
        ->get(route('permissions.api.roles.index'))
        ->assertStatus(200);
});
