<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Techieni3\LaravelUserPermissions\Enums\ModelActions;
use Techieni3\LaravelUserPermissions\Models\Permission;

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
     */
    public function handle(): void
    {
        $modelsPath = Config::string('permissions.models_path');

        if ( ! $modelsPath || ! File::isDirectory($modelsPath)) {
            $this->error('Models directory not found. Please check your permissions.models_path config.');

            return;
        }

        $modelFiles = File::allFiles($modelsPath);

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

    /**
     * Generate permissions for a specific model.
     * Creates a permission for each action defined in ModelActions enum.
     *
     * @param  string  $modelName  The name of the model to generate permissions for
     */
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
