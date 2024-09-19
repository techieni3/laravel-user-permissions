<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Techieni3\LaravelUserPermissions\Enums\ModelActions;
use Techieni3\LaravelUserPermissions\Models\Permission;

class GeneratePermissionsCommand extends Command
{
    protected $signature = 'sync:permissions';

    protected $description = 'Generate permissions for all models';

    public function handle(): void
    {
        $modelFiles = File::allFiles(Config::string('permissions.models_directory'));

        $excludedModels = Config::array('permissions.excluded_models', []);
        $excludedModelsBaseNames = array_map(static function ($model) {
            $classParts = explode('\\', $model);

            return end($classParts);
        }, array_values($excludedModels));

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getBasename('.php');

            // check model name is not excluded
            if (in_array($modelName, $excludedModelsBaseNames, true)) {
                continue;
            }

            $this->generatePermissionsForModel($modelName);
        }

        $this->info('Permissions generated successfully.');
    }

    protected function generatePermissionsForModel(string $modelName): void
    {
        foreach (ModelActions::list() as $action) {
            $permissionName = mb_strtolower("{$action}_{$modelName}");

            Permission::query()->createOrFirst([
                'name' => $permissionName,
            ]);
        }
    }
}
