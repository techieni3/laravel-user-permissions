<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController
{
    /**
     * Display the permissions dashboard.
     */
    public function index(Request $request): View
    {
        return view('permissions::dashboard');
    }
}
