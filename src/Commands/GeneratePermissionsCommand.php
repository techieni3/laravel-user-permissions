<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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
     * Cached PSR-4 autoload mappings.
     *
     * @var array<string, string>
     */
    protected array $psr4Mappings = [];

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

        // Load PSR-4 mappings once
        $this->loadPsr4Mappings();

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

            // Get fully qualified class name and verify it's a Model
            $className = $this->getClassNameFromFile($modelFile->getPathname());

            if ($className === null || ! $this->isModelClass($className)) {
                $this->warn("Skipping {$modelName}: not a valid Eloquent Model class.");

                continue;
            }

            $permissions = [...$permissions, ...$this->getPermissionsForModel($modelName)];
        }

        if ($permissions !== []) {
            Permission::upsert($permissions, ['name'], ['display_name']);
        }

        $this->info('Permissions generated successfully.');
    }

    /**
     * Get permissions array for a specific model.
     * Returns permission data for each action defined in ModelActions enum.
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

    /**
     * Load PSR-4 autoload mappings from composer.json.
     */
    protected function loadPsr4Mappings(): void
    {
        $composerPath = base_path('composer.json');

        if ( ! File::exists($composerPath)) {
            return;
        }

        $composer = json_decode(File::get($composerPath), true);
        $this->psr4Mappings = $composer['autoload']['psr-4'] ?? [];
    }

    /**
     * Extract the fully qualified class name from a PHP file using PSR-4 autoload mappings.
     *
     * @param  string  $filePath  The path to the PHP file
     * @return string|null The fully qualified class name, or null if not found
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        foreach ($this->psr4Mappings as $namespace => $path) {
            $absolutePath = base_path(rtrim($path, '/'));

            if (str_starts_with($filePath, $absolutePath)) {
                $relativePath = substr($filePath, strlen($absolutePath) + 1);
                $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);

                return rtrim($namespace, '\\') . '\\' . $className;
            }
        }

        return null;
    }

    /**
     * Check if a class is a valid Eloquent Model.
     *
     * @param  string  $className  The fully qualified class name
     * @return bool True if the class is an Eloquent Model
     */
    protected function isModelClass(string $className): bool
    {
        if ( ! class_exists($className)) {
            return false;
        }

        return is_subclass_of($className, Model::class);
    }
}
