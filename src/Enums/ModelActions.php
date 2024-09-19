<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Enums;

enum ModelActions: string
{
    case ViewAny = 'view_any';
    case View = 'view';
    case Create = 'create';
    case Edit = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'force_delete';

    public static function list(): array
    {
        return array_column(self::cases(), 'value');
    }
}
