<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Techieni3\LaravelUserPermissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    protected $guarded = [];
}
