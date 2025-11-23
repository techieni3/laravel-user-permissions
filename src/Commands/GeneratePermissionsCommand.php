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
        $excludedModelsBaseNames = array_map(static function ($model): string {
            $classParts = explode('\\', $model);

            return end($classParts);
        }, array_values($excludedModels));

        $permissions = [];

        foreach ($modelFiles as $modelFile) {
            $modelName = $modelFile->getBasename('.php');

            // check model name is not excluded
            if (in_array($modelName, $excludedModelsBaseNames, true)) {
                continue;
            }

            $permissions = [...$permissions, ...$this->getPermissionsForModel($modelName)];
        }

        if ($permissions !== []) {
            Permission::query()->upsert($permissions, ['name']);
        }

        $this->info('Permissions generated successfully.');
    }

    /**
     * Get a permissions array for a specific model.
     * Returns permission data for each action defined in the ModelActions enum.
     *
     * @param  string  $modelName  The name of the model to generate permissions for
     * @return array<int, array{name: string, display_name: string}>
     */
    protected function getPermissionsForModel(string $modelName): array
    {
        $permissions = [];

        foreach (ModelActions::list() as $action) {
            $permissionName = mb_strtolower("{$action}_{$modelName}");

            $permissions[] = [
                'name' => $permissionName,
                'display_name' => ucwords(str_replace('_', ' ', $permissionName)),
            ];
        }

        return $permissions;
    }
}
