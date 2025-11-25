<?php

declare(strict_types=1);

use Techieni3\LaravelUserPermissions\Models\Role;
use Workbench\App\Models\User;

beforeEach(function (): void {
    $this->artisan('sync:roles');
});

it('can list roles', function (): void {
    $user = User::query()->create([
        'name' => 'Test User',
    ]);

    $this->actingAs($user)
        ->getJson(route('permissions.api.roles.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'updated_at',
                    'permissions_count',
                ],
            ],
        ]);
});

it('returns correct roles data', function (): void {
    $user = User::query()->create([
        'name' => 'Test User 2',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('permissions.api.roles.index'))
        ->assertOk();

    $roles = Role::all();

    expect($response->json('data'))->toHaveCount($roles->count());
});
