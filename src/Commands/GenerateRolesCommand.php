<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Techieni3\LaravelUserPermissions\Models\Role;

class GenerateRolesCommand extends Command
{
    protected $signature = 'sync:roles';

    protected $description = 'Generate roles from Role enum';

    public function handle(): void
    {
        $roleEnumPath = Config::string('permissions.role_enum_file');

        if ( ! File::exists($roleEnumPath)) {
            $this->error(
                'Role enum not found in app/Enums folder. Please run "php artisan permissions:install" first.'
            );

            return;
        }

        $createdCount = 0;
        $existingCount = 0;

        require_once $roleEnumPath;

        $enumClassName = basename($roleEnumPath, '.php');
        $roleEnumClass = 'App\\Enums\\' . $enumClassName;

        if ( ! class_exists($roleEnumClass)) {
            $this->error('Role enum class not found. Please make sure it\'s defined correctly.');

            return;
        }

        $reflection = new ReflectionClass($roleEnumClass);
        $cases = $reflection->getConstants();

        foreach ($cases as $role) {
            $role = Role::query()->createOrFirst(
                ['name' => mb_strtolower($role->value)],
                ['display_name' => $role->name]
            );

            if ($role->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $existingCount++;
            }
        }

        $this->info('Roles generation completed.');
        $this->info("Created: {$createdCount}");
        $this->info("Already existing: {$existingCount}");
    }
}
