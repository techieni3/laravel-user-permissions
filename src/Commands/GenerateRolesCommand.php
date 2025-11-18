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
        $roleEnumPath = Config::string('permissions.role_enum');

        if ( ! File::exists($roleEnumPath)) {
            $this->error(
                'Role enum not found in app/Enums folder. Please run "php artisan permissions:install" first.'
            );

            return;
        }

        $createdCount = 0;
        $existingCount = 0;

        $namespace = $this->getPhpNamespace($roleEnumPath);
        $enumClassName = basename($roleEnumPath, '.php');
        $roleEnumClass = $namespace . '\\' . $enumClassName;

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

    private function getPhpNamespace(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            $this->error("Failed to read file: {$filePath}");

            return null;
        }

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches)) {
            return mb_trim($matches[1]);
        }

        return null;
    }
}
