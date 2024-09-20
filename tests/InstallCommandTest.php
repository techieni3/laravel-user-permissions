<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Orchestra\Testbench\workbench_path;

afterEach(function (): void {
    // Clean the migration folder by deleting migration files after each test
    $migrationsPath = $this->app->basePath('database/migrations');

    foreach (File::allFiles($migrationsPath) as $file) {
        if ($file->getExtension() === 'php') {
            File::delete($file);
        }
    }
});

it('It should install the package', function (): void {
    // Set user model path
    $userModelPath = workbench_path('app/Models/User.php');
    $this->app['config']->set('permissions.user_model_file', $userModelPath);

    // Mock the File facade to avoid actual file operations during testing
    File::shouldReceive('exists')->andReturn(false);
    File::shouldReceive('get')->andReturn(file_get_contents($userModelPath));
    File::shouldReceive('copy');
    File::shouldReceive('isDirectory')->andReturn(false);
    File::shouldReceive('makeDirectory');
    File::shouldReceive('allFiles')->andReturn([]);

    $this->artisan('install:permissions')->assertExitCode(0);
});

it('It publishes config file', function (): void {
    $targetConfigPath = $this->app->basePath('config/permissions.php');
    $targetStubsPath = $this->app->basePath('app/Enums');

    File::deleteDirectory($targetStubsPath);
    File::delete($targetConfigPath);

    $this->artisan('install:permissions')
        ->assertExitCode(0);

    expect($targetConfigPath)->toBeFile();
});

it('It force overwrites config file', function (): void {
    $targetConfigPath = $this->app->basePath('config/permissions.php');
    $targetStubsPath = $this->app->basePath('app/Enums');

    File::deleteDirectory($targetStubsPath);
    expect($targetConfigPath)->toBeFile();

    $this->artisan('install:permissions')
        ->expectsQuestion('The config file already exists. Do you want to overwrite it?', 'yes')
        ->assertExitCode(0);

    expect($targetConfigPath)->toBeFile();
});

it('It publishes Role enum', function (): void {
    $targetConfigPath = $this->app->basePath('config/permissions.php');
    $targetStubsPath = $this->app->basePath('app/Enums');

    File::delete($targetConfigPath);
    File::deleteDirectory($targetStubsPath);

    $this->artisan('install:permissions')
        ->assertExitCode(0);

    expect("{$targetStubsPath}/Role.php")->toBeFile();
});

it('It force publishes Role enum', function (): void {
    $targetConfigPath = $this->app->basePath('config/permissions.php');
    $targetStubsPath = $this->app->basePath('app/Enums');

    File::delete($targetConfigPath);

    $this->artisan('install:permissions')
        ->expectsQuestion('The Role.php file already exists. Do you want to overwrite it?', 'yes')
        ->assertExitCode(0);

    expect("{$targetStubsPath}/Role.php")->toBeFile();
});

it('It publishes migrations', function (): void {
    $targetConfigPath = $this->app->basePath('config/permissions.php');
    $targetStubsPath = $this->app->basePath('app/Enums');
    $migrationsPath = $this->app->basePath('database/migrations');

    File::delete($targetConfigPath);
    File::deleteDirectory($targetStubsPath);
    $files = File::allFiles($migrationsPath);

    // sleep for 1 second to ensure that the file timestamps are different
    sleep(1);

    $this->artisan('install:permissions')
        ->assertExitCode(0);

    $newFiles = File::allFiles($migrationsPath);

    expect(count($newFiles))->toBeGreaterThan(count($files));
});
