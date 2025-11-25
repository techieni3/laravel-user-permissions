<?php

declare(strict_types=1);

use Workbench\App\Models\User;

it('can list users', function (): void {
    for ($i = 0; $i < 5; $i++) {
        User::query()->create([
            'name' => "User {$i}",
        ]);
    }

    $admin = User::query()->create([
        'name' => 'Admin User',
    ]);

    $this->actingAs($admin)
        ->getJson(route('permissions.api.users.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'roles',
                    'permissions_count',
                ],
            ],
            'current_page',
            'total',
        ]);
});

it('can search users', function (): void {
    User::query()->create(['name' => 'John Doe']);
    User::query()->create(['name' => 'Jane Smith']);
    $admin = User::query()->create(['name' => 'Admin']);

    $this->actingAs($admin)
        ->getJson(route('permissions.api.users.index', ['search' => 'John']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'John Doe');
});
