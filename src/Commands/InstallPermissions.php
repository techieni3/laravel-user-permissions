<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Throwable;

/**
 * Install Permissions Command.
 */
class InstallPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and publish permissions package files';

    /**
     * The path to the user model file.
     */
    private string $userModelPath;

    /**
     * Execute the console command.
     * Performs all installation steps for the permissions package.
     *
     * @throws Throwable When the user model file is not found
     */
    public function handle(): void
    {
        $this->userModelPath = $this->getClassFilePath(Config::string('permissions.user_model'));

        $this->publishConfig();
        $this->publishMigrations();
        $this->createEnumsFolder();
        $this->copyRoleStub();
        $this->copyModelActionsStub();
        $this->addHasRolesTraitToUserModel();

        $this->info('✨ Permissions package installed successfully!');
    }

    /**
     * Publish the package configuration file to the application's config directory.
     */
    private function publishConfig(): void
    {
        $configPath = __DIR__.'/../../config/permissions.php';

        $destinationPath = config_path('permissions.php');

        if (File::exists($destinationPath) && ! $this->confirm('The config file already exists. Do you want to overwrite it?')) {
            return;
        }

        File::copy($configPath, $destinationPath);

        $this->info('✅ Config file published successfully.');
    }

    /**
     * Publish the package migrations to the application's migrations directory.
     */
    private function publishMigrations(): void
    {
        $migrationPath = __DIR__.'/../../migrations';

        $destinationPath = database_path('migrations');

        File::ensureDirectoryExists($destinationPath);

        $files = File::allFiles($migrationPath);

        $timestamp = date('Y_m_d_His');
        $counter = 0;

        foreach ($files as $file) {
            $fileName = $file->getFilename();

            $newFileName = $timestamp.'_'.mb_str_pad((string) $counter, 2, '0', STR_PAD_LEFT).'_'.$fileName;

            File::copy($file->getPathname(), $destinationPath.'/'.$newFileName);

            $counter++;
        }

        $this->info('✅ Migrations published successfully.');
    }

    /**
     * Create the Enums directory in the application if it doesn't exist.
     */
    private function createEnumsFolder(): void
    {
        $enumsPath = app_path('Enums');

        File::ensureDirectoryExists($enumsPath);
    }

    /**
     * Copy the Role enum stub to the application's Enums directory.
     */
    private function copyRoleStub(): void
    {
        $stubPath = __DIR__.'/../../stubs/Role.php.stub';

        $destinationPath = app_path('Enums/Role.php');

        if (File::exists($destinationPath) && ! $this->confirm('The Role.php file already exists. Do you want to overwrite it?')) {
            return;
        }

        File::copy($stubPath, $destinationPath);

        $this->info('✅ Role stub copied successfully.');
    }

    /**
     * Copy the ModelActions enum stub to the application's Enums directory.
     */
    private function copyModelActionsStub(): void
    {
        $stubPath = __DIR__.'/../../stubs/ModelActions.php.stub';

        $destinationPath = app_path('Enums/ModelActions.php');

        if (File::exists($destinationPath) && ! $this->confirm('The ModelActions.php file already exists. Do you want to overwrite it?')) {
            return;
        }

        File::copy($stubPath, $destinationPath);

        $this->info('✅ ModelActions stub copied successfully.');
    }

    /**
     * Add the HasRoles trait to the User model.
     * Detects the User model structure and adds the trait accordingly.
     *
     * @throws FileNotFoundException
     */
    private function addHasRolesTraitToUserModel(): void
    {
        $userModel = File::get($this->userModelPath);

        if (str_contains($userModel, 'use Techieni3\LaravelUserPermissions\Traits\HasRoles;')) {
            $this->info('User model already has the HasRoles trait.');

            return;
        }

        // check user model extend classes
        if (str_contains($userModel, 'User extends Authenticatable implements MustVerifyEmail')) {
            $this->addHasRolesTraitForMustVerifyEmailImplementedUserModel();
            $this->info('✅ Added HasRoles trait to User model.');

            return;
        }

        // Check if the file contains only 'User extends Authenticatable' (and not 'implements MustVerifyEmail')
        if (str_contains($userModel, 'User extends Authenticatable')) {
            $this->addHasRolesTraitForAuthenticatableExtendedUserModel();
            $this->info('✅ Added HasRoles trait to User model.');

            return;
        }

        $this->warn('Unable to add HasRoles trait to User model. Please add it manually.');
    }

    /**
     * Add the HasRoles trait namespace import to the User model.
     */
    private function addHasRolesTraitsNamespaceInUserModel(): void
    {
        $this->replaceInFile(
            search: 'use Illuminate\Foundation\Auth\User as Authenticatable;',
            replace: <<<'EOT'
use Illuminate\Foundation\Auth\User as Authenticatable;
use Techieni3\LaravelUserPermissions\Traits\HasRoles;
EOT,
            filePath: $this->userModelPath,
        );
    }

    /**
     * Add HasRoles trait to a User model that implements MustVerifyEmail.
     */
    private function addHasRolesTraitForMustVerifyEmailImplementedUserModel(): void
    {
        $this->addHasRolesTraitsNamespaceInUserModel();

        $this->replaceInFile(
            search: <<<'EOT'
class User extends Authenticatable implements MustVerifyEmail
{
EOT,
            replace: <<<'EOT'
class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles;
EOT,
            filePath: $this->userModelPath,
        );
    }

    /**
     * Add HasRoles trait to a User model that extends Authenticatable.
     */
    private function addHasRolesTraitForAuthenticatableExtendedUserModel(): void
    {
        $this->addHasRolesTraitsNamespaceInUserModel();

        $this->replaceInFile(
            search: <<<'EOT'
class User extends Authenticatable
{
EOT,
            replace: <<<'EOT'
class User extends Authenticatable
{
    use HasRoles;
EOT,
            filePath: $this->userModelPath,
        );
    }

    /**
     * Replace content in a file.
     *
     * @param  string|array<string>  $search
     * @param  string|array<string>  $replace
     */
    private function replaceInFile(string|array $search, string|array $replace, string $filePath): void
    {
        file_put_contents(
            $filePath,
            str_replace($search, $replace, (string) file_get_contents($filePath))
        );
    }

    /**
     * Get the file path from a class reference.
     *
     * @throws FileNotFoundException
     */
    private function getClassFilePath(string $className): string
    {
        if ( ! class_exists($className)) {
            throw new FileNotFoundException("Class {$className} not found.");
        }

        $reflection = new ReflectionClass($className);

        $path = $reflection->getFileName();

        if ( ! $path) {
            throw new FileNotFoundException("Class {$className} not found.");
        }

        return $path;
    }
}
