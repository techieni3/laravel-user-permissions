<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedPivotRecords extends Command
{
    protected $signature = 'permissions:cleanup-orphans
                            {--dry-run : Display orphaned records without deleting them}';

    protected $description = 'Remove orphaned records from pivot tables (users_roles, users_permissions, roles_permissions)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No records will be deleted.');
            $this->newLine();
        }

        $totalDeleted = 0;

        // Cleanup users_roles
        $this->info('Checking users_roles table...');
        $totalDeleted += $this->cleanupUsersRoles($isDryRun);

        // Cleanup users_permissions
        $this->info('Checking users_permissions table...');
        $totalDeleted += $this->cleanupUsersPermissions($isDryRun);

        // Cleanup roles_permissions
        $this->info('Checking roles_permissions table...');
        $totalDeleted += $this->cleanupRolesPermissions($isDryRun);

        $this->newLine();

        if ($isDryRun) {
            if ($totalDeleted > 0) {
                $this->warn("Found {$totalDeleted} orphaned record(s) that would be deleted.");
                $this->info('Run without --dry-run to actually delete them.');
            } else {
                $this->info('No orphaned records found.');
            }
        } elseif ($totalDeleted > 0) {
            $this->info("Successfully removed {$totalDeleted} orphaned record(s).");
        } else {
            $this->info('Cleanup completed. No orphaned records found.');
        }

        return self::SUCCESS;
    }

    protected function cleanupUsersRoles(bool $isDryRun): int
    {
        $count = 0;

        // Orphaned by user
        if ($isDryRun) {
            $orphanedByUserCount = DB::table('users_roles')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'users_roles.user_id');
                })
                ->count();

            if ($orphanedByUserCount > 0) {
                $this->line("  Found {$orphanedByUserCount} record(s) with non-existent users");
                $count += $orphanedByUserCount;
            }
        } else {
            $deleted = DB::table('users_roles')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'users_roles.user_id');
                })
                ->delete();

            $count += $deleted;
        }

        // Orphaned by role
        if ($isDryRun) {
            $orphanedByRoleCount = DB::table('users_roles')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('roles')
                        ->whereColumn('roles.id', 'users_roles.role_id');
                })
                ->count();

            if ($orphanedByRoleCount > 0) {
                $this->line("  Found {$orphanedByRoleCount} record(s) with non-existent roles");
                $count += $orphanedByRoleCount;
            }
        } else {
            $deleted = DB::table('users_roles')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('roles')
                        ->whereColumn('roles.id', 'users_roles.role_id');
                })
                ->delete();

            $count += $deleted;
        }

        if ($isDryRun && $count === 0) {
            $this->line('  ✓ No orphaned records found');
        }

        return $count;
    }

    protected function cleanupUsersPermissions(bool $isDryRun): int
    {
        $count = 0;

        // Orphaned by user
        if ($isDryRun) {
            $orphanedByUserCount = DB::table('users_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'users_permissions.user_id');
                })
                ->count();

            if ($orphanedByUserCount > 0) {
                $this->line("  Found {$orphanedByUserCount} record(s) with non-existent users");
                $count += $orphanedByUserCount;
            }
        } else {
            $deleted = DB::table('users_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'users_permissions.user_id');
                })
                ->delete();

            $count += $deleted;
        }

        // Orphaned by permission
        if ($isDryRun) {
            $orphanedByPermissionCount = DB::table('users_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('permissions')
                        ->whereColumn('permissions.id', 'users_permissions.permission_id');
                })
                ->count();

            if ($orphanedByPermissionCount > 0) {
                $this->line("  Found {$orphanedByPermissionCount} record(s) with non-existent permissions");
                $count += $orphanedByPermissionCount;
            }
        } else {
            $deleted = DB::table('users_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('permissions')
                        ->whereColumn('permissions.id', 'users_permissions.permission_id');
                })
                ->delete();

            $count += $deleted;
        }

        if ($isDryRun && $count === 0) {
            $this->line('  ✓ No orphaned records found');
        }

        return $count;
    }

    protected function cleanupRolesPermissions(bool $isDryRun): int
    {
        $count = 0;

        // Orphaned by role
        if ($isDryRun) {
            $orphanedByRoleCount = DB::table('roles_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('roles')
                        ->whereColumn('roles.id', 'roles_permissions.role_id');
                })
                ->count();

            if ($orphanedByRoleCount > 0) {
                $this->line("  Found {$orphanedByRoleCount} record(s) with non-existent roles");
                $count += $orphanedByRoleCount;
            }
        } else {
            $deleted = DB::table('roles_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('roles')
                        ->whereColumn('roles.id', 'roles_permissions.role_id');
                })
                ->delete();

            $count += $deleted;
        }

        // Orphaned by permission
        if ($isDryRun) {
            $orphanedByPermissionCount = DB::table('roles_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('permissions')
                        ->whereColumn('permissions.id', 'roles_permissions.permission_id');
                })
                ->count();

            if ($orphanedByPermissionCount > 0) {
                $this->line("  Found {$orphanedByPermissionCount} record(s) with non-existent permissions");
                $count += $orphanedByPermissionCount;
            }
        } else {
            $deleted = DB::table('roles_permissions')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('permissions')
                        ->whereColumn('permissions.id', 'roles_permissions.permission_id');
                })
                ->delete();

            $count += $deleted;
        }

        if ($isDryRun && $count === 0) {
            $this->line('  ✓ No orphaned records found');
        }

        return $count;
    }
}
