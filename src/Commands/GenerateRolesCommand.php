<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use BackedEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Techieni3\LaravelUserPermissions\Models\Role;
use Throwable;

/**
 * Generate Roles Command.
 *
 * This command generates role records from the configured Role enum.
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
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        /** @var class-string<BackedEnum> $roleEnum */
        $roleEnum = Config::string('permissions.role_enum');

        if ( ! class_exists($roleEnum) || ! enum_exists($roleEnum)) {
            $this->fail("Role enum class not found. Please make sure it's defined correctly.");
        }

        /** @var array<BackedEnum> $rolesFromEnum */
        $rolesFromEnum = $roleEnum::cases();

        if ($rolesFromEnum === []) {
            $this->error("No roles found in the {$roleEnum} enum.");

            return;
        }

        $rolesData = [];
        foreach ($rolesFromEnum as $roleCase) {
            $rolesData[] = [
                'name' => mb_strtolower((string) $roleCase->value),
                'display_name' => $roleCase->name,
            ];
        }

        $count = Role::query()->upsert($rolesData, ['name']);

        $this->info("{$count} roles synchronized.");
    }
}
