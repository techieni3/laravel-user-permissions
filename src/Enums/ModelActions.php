<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Enums;

/**
 * Model Actions Enum.
 *
 * Defines the standard CRUD and policy actions that can be performed on models.
 * These actions are used to generate permissions for models.
 */
enum ModelActions: string
{
    /** View any records */
    case ViewAny = 'view_any';

    /** View a single record */
    case View = 'view';

    /** Create a new record */
    case Create = 'create';

    /** Update an existing record */
    case Edit = 'update';

    /** Soft delete a record */
    case Delete = 'delete';

    /** Restore a soft-deleted record */
    case Restore = 'restore';

    /** Permanently delete a record */
    case ForceDelete = 'force_delete';

    /**
     * Get a list of all action values.
     *
     * @return array<string> Array of action values
     */
    public static function list(): array
    {
        return array_column(self::cases(), 'value');
    }
}
