<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use BackedEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Techieni3\LaravelUserPermissions\Models\Permission;
use Throwable;

/**
 * Generate Permissions Command.
 *
 * This command generates permissions for all models based on the ModelActions enum.
 */
class GeneratePermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate permissions for all models';

    /**
     * Execute the console command.
     * Scans the included directories and models and generates permissions.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        /** @var array<string|class-string> $included */
        $included = Config::array('permissions.models.included', []);

        if ($included === []) {
            $this->fail('No models or directories specified in permissions.models.included config.');
        }

        /** @var class-string<BackedEnum> $modelActionsEnum */
        $modelActionsEnum = Config::string('permissions.classes.model_actions_enum');

        if ( ! class_exists($modelActionsEnum) || ! enum_exists($modelActionsEnum)) {
            $this->fail("ModelActions enum class not found. Please make sure it's defined correctly.");
        }

        /** @var array<string> $actions */
        $actions = array_column($modelActionsEnum::cases(), 'value');

        if ($actions === []) {
            $this->fail("No actions found in the {$modelActionsEnum} enum.");
        }

        /** @var array<class-string> $excludedModels */
        $excludedModels = Config::array('permissions.models.excluded', []);
        $excludedModelsBaseNames = array_map(class_basename(...), array_values($excludedModels));

        $modelNames = $this->discoverModels($included, $excludedModelsBaseNames);

        $permissionsData = [];

        foreach ($modelNames as $modelName) {
            $permissionsData[] = $this->buildModelPermissions($modelName, $actions);
        }

        $permissionsData = array_merge(...$permissionsData);
        $count = 0;

        if ($permissionsData !== []) {
            $count = Permission::query()->upsert($permissionsData, ['name']);
        }

        $this->info("{$count} permissions synchronized.");
    }

    /**
     * Discover model names from the included paths and classes.
     *
     * @param  array<string|class-string>  $included
     * @param  array<string>  $excludedModelsBaseNames
     * @return array<string>
     */
    private function discoverModels(array $included, array $excludedModelsBaseNames): array
    {
        $modelNames = [];

        foreach ($included as $item) {
            // Check if it's a class string (contains backslashes)
            if (str_contains($item, '\\')) {
                // It's a specific model class
                if (class_exists($item)) {
                    $modelName = class_basename($item);

                    // Skip if excluded
                    if (in_array($modelName, $excludedModelsBaseNames, true)) {
                        continue;
                    }

                    $modelNames[] = $modelName;
                }
            } elseif (is_dir($item)) {
                // It's a directory path
                $modelFiles = File::allFiles($item);
                foreach ($modelFiles as $modelFile) {
                    $modelName = $modelFile->getBasename('.php');

                    // Skip if excluded
                    if (in_array($modelName, $excludedModelsBaseNames, true)) {
                        continue;
                    }

                    $modelNames[] = $modelName;
                }
            }
        }

        return array_unique($modelNames);
    }

    /**
     * Build an array of permissions for the given model.
     *
     * @param  array<string>  $actions
     * @return array<int, array{name: string, display_name: string}>
     */
    private function buildModelPermissions(string $modelName, array $actions): array
    {
        $permissions = [];

        foreach ($actions as $action) {
            $permissionName = mb_strtolower("{$modelName}.{$action}");

            $action = implode(' ', explode('_', $action));
            $permissions[] = [
                'name' => $permissionName,
                'display_name' => ucwords("{$modelName} {$action}"),
            ];
        }

        return $permissions;
    }
}
