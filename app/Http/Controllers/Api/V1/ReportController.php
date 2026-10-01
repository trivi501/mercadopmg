<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Reports\ReportFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ReportsView->value), 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportFactory::REPORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $report = ReportFactory::make($validated['type'], $request->except(['type', 'per_page', 'page']));
        $page = $report->query()->paginate((int) ($validated['per_page'] ?? 50));

        return response()->json([
            'title' => $report->title(),
            'columns' => $report->columns(),
            'summary' => $report->summary(),
            'data' => collect($page->items())->map(fn (Model $m) => $report->row($m)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
