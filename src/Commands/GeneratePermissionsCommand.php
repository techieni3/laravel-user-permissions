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
     * Scans the models directory and generates permissions for each model.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $modelsDirectory = Config::string('permissions.models.directory');

        if ($modelsDirectory === '' || ! File::isDirectory($modelsDirectory)) {
            $this->fail('Models directory not found. Please check your permissions.models.directory config.');
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

        $modelFiles = File::allFiles($modelsDirectory);

        /** @var array<string> $excludedModels */
        $excludedModels = Config::array('permissions.models.excluded', []);
        $excludedModelsBaseNames = array_map(class_basename(...), array_values($excludedModels));

        $permissionsData = [];

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getBasename('.php');

            // check the model name is not excluded
            if (in_array($modelName, $excludedModelsBaseNames, true)) {
                continue;
            }

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
     * Build an array of permissions for the given model.
     *
     * @param  array<string>  $actions
     * @return array<int, array{name: string, display_name: string}>
     */
    protected function buildModelPermissions(string $modelName, array $actions): array
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
