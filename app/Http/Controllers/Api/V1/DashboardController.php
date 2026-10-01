<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Indicadores. Con `mine=1` se limitan a los cobros del usuario (recaudador).
     */
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::DashboardView->value), 403);

        return response()->json(['data' => $dashboard->indicators($request->boolean('mine') ? $request->user()->id : null)]);
    }
}
