<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Techieni3\LaravelUserPermissions\Models\Role;

/**
 * Generate Roles Command.
 *
 * This command generates role records in the database from the configured Role enum.
 * It reads all cases from the Role enum and creates corresponding database entries.
 */
class GenerateRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate roles from Role enum';

    /**
     * Execute the console command.
     * Reads the Role enum and creates database entries for each role.
     */
    public function handle(): void
    {
        $roleEnum = Config::string('permissions.role_enum');

        if ( ! class_exists($roleEnum) || ! enum_exists($roleEnum)) {
            $this->error("Role enum class not found. Please make sure it's defined correctly.");

            return;
        }

        $createdCount = 0;
        $existingCount = 0;

        $reflection = new ReflectionClass($roleEnum);
        $cases = $reflection->getConstants();

        foreach ($cases as $role) {
            $role = Role::query()->createOrFirst(
                ['name' => mb_strtolower((string) $role->value)],
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
}
