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
 * This command generates permissions for all models in the configured models directory.
 * It creates permissions for each model action (view, create, edit, delete, etc.)
 * as defined in the ModelActions enum.
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
     * Scans the models directory and generates permissions for each model.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $modelsPath = Config::string('permissions.models_path');

        if ($modelsPath === '' || ! File::isDirectory($modelsPath)) {
            $this->fail('Models directory not found. Please check your permissions.models_path config.');
        }

        /** @var class-string<BackedEnum> $modelActionsEnum */
        $modelActionsEnum = Config::string('permissions.model_actions_enum');

        if ( ! class_exists($modelActionsEnum) || ! enum_exists($modelActionsEnum)) {
            $this->fail("ModelActions enum class not found. Please make sure it's defined correctly.");
        }

        /** @var array<string> $actions */
        $actions = array_column($modelActionsEnum::cases(), 'value');

        if ($actions === []) {
            $this->fail("No actions found in the {$modelActionsEnum} enum.");
        }

        $modelFiles = File::allFiles($modelsPath);

        /** @var array<string> $excludedModels */
        $excludedModels = Config::array('permissions.excluded_models', []);
        $excludedModelsBaseNames = array_map(class_basename(...), array_values($excludedModels));

        $permissions = [];

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getBasename('.php');

            // check the model name is not excluded
            if (in_array($modelName, $excludedModelsBaseNames, true)) {
                continue;
            }

            $permissions[] = $this->getPermissionsForModel($modelName, $actions);
        }

        $permissions = array_merge(...$permissions);
        $count = 0;

        if ($permissions !== []) {
            $count = Permission::query()->upsert($permissions, ['name']);
        }

        $this->info("{$count} permissions synchronized.");
    }

    /**
     * Get a permissions array for a specific model.
     *
     * @param  array<string>  $actions
     * @return array<int, array{name: string, display_name: string}>
     */
    protected function getPermissionsForModel(string $modelName, array $actions): array
    {
        $permissions = [];

        foreach ($actions as $action) {
            $permissionName = mb_strtolower("{$modelName}.{$action}");

            $permissions[] = [
                'name' => $permissionName,
                'display_name' => ucwords("{$modelName} {$action}"),
            ];
        }

        return $permissions;
    }
}
