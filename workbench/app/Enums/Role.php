<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
}
