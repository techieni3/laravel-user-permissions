<?php

declare(strict_types=1);

namespace TechieNi3\LaravelUserPermissions\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    use RefreshDatabase;
    use WithWorkbench;
}
