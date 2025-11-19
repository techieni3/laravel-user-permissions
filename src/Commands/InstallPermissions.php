<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Throwable;

class InstallPermissions extends Command
{
    protected $signature = 'install:permissions';

    protected $description = 'Install and publish permissions package files';

    private ?string $userModelPath = null;

    /**
     * @throws Throwable when the user model file is not found
     */
    public function handle(): void
    {
        $this->userModelPath = $this->getClassFilePath(config('permissions.user_model'));

        $this->checkUserModelExists();
        $this->publishConfig();
        $this->publishMigrations();
        $this->copyRoleStub();
        $this->addHasRolesTraitToUserModel();

        $this->info('Permissions package installed successfully!');
    }

    private function publishConfig(): void
    {
        $configPath = __DIR__ . '/../../config/permissions.php';

        $destinationPath = config_path('permissions.php');

        if (File::exists($destinationPath)) {
            if ( ! $this->confirm('The config file already exists. Do you want to overwrite it?')) {
                return;
            }
        }

        File::copy($configPath, $destinationPath);
        $this->info('Config file published successfully.');
    }

    private function publishMigrations(): void
    {
        $migrationPath = __DIR__ . '/../../migrations';

        $destinationPath = database_path('migrations');

        if ( ! File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $files = File::allFiles($migrationPath);

        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $newFileName = date('Y_m_d_His_') . $fileName;
            File::copy($file->getPathname(), $destinationPath . '/' . $newFileName);
        }

        $this->info('Migrations published successfully.');
    }

    private function createEnumsFolder(): void
    {
        $enumsPath = app_path('Enums');

        if ( ! File::isDirectory($enumsPath)) {
            File::makeDirectory($enumsPath, 0755, true);
        }
    }

    private function copyRoleStub(): void
    {
        $this->createEnumsFolder();

        $stubPath = __DIR__ . '/../../stubs/Role.php.stub';

        $destinationPath = app_path('Enums/Role.php');

        if (File::exists($destinationPath)) {
            if ( ! $this->confirm('The Role.php file already exists. Do you want to overwrite it?')) {
                return;
            }
        }

        File::copy($stubPath, $destinationPath);
        $this->info('Role stub copied successfully.');
    }

    /**
     * @throws Throwable
     */
    private function checkUserModelExists(): void
    {
        if ($this->userModelPath === null) {
            $this->fail('User model file not found. Please update the config file.');
        }

        if ( ! file_exists($this->userModelPath)) {
            $this->fail('User model file not found. Please update the config file.');
        }
    }

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
            $this->info('Added HasRoles trait to User model.');

            return;
        }

        // Check if the file contains only 'User extends Authenticatable' (and not 'implements MustVerifyEmail')
        if (str_contains($userModel, 'User extends Authenticatable')) {
            $this->addHasRolesTraitForAuthenticatableExtendedUserModel();
            $this->info('Added HasRoles trait to User model.');

            return;
        }

        $this->warn('Unable to add HasRoles trait to User model. Please add it manually.');
    }

    private function addHasRolesTraitsNamespaceInUserModel(): void
    {
        $this->replaceInFile(
            search: 'use Illuminate\Foundation\Auth\User as Authenticatable;',
            replace: <<<'EOT'
use Illuminate\Foundation\Auth\User as Authenticatable;
use Techieni3\LaravelUserPermissions\Traits\HasRoles;
EOT,
            file: config('permissions.user_model')
        );
    }

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
            file: config('permissions.user_model')
        );
    }

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
            file: config('permissions.user_model')
        );
    }

    private function replaceInFile(string|array $search, string|array $replace, string $file): void
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, (string) file_get_contents($file))
        );
    }

    /**
     * Get the file path from a class reference.
     *
     * @param  string  $className  Fully qualified class name
     * @return string|null File path or null if class doesn't exist
     */
    private function getClassFilePath(string $className): ?string
    {
        if ( ! class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            return $reflection->getFileName() ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
