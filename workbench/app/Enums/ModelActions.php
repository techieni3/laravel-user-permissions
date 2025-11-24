<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

enum ModelActions: string
{
    case ViewAny = 'view_any';
    case View = 'view';
    case Create = 'create';
    case Edit = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ForceDelete = 'force_delete';
}
