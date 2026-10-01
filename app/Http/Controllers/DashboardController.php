<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        abort_unless($request->user()?->can(Permission::DashboardView->value), 403);

        return Inertia::render('dashboard', [
            'indicators' => $dashboard->indicators(),
            'charts' => Inertia::defer(fn () => $dashboard->charts()),
        ]);
    }
}
