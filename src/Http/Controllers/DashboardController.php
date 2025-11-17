<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /**
     * Display the permissions dashboard.
     */
    public function index(Request $request)
    {
        return view('permissions::dashboard');
    }
}
