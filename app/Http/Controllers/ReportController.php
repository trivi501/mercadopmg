<?php

namespace App\Http\Controllers;

use App\Enums\AnnualityStatus;
use App\Enums\Permission;
use App\Reports\ReportExporter;
use App\Reports\ReportFactory;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function index(Request $request, string $type = 'revenue'): Response
    {
        abort_unless($request->user()?->can(Permission::ReportsView->value), 403);

        $filters = $request->except(['page']);
        $report = ReportFactory::make($type, $filters);
        $page = $report->query()->paginate(25)->withQueryString();

        return Inertia::render('reports/index', [
            'type' => $type,
            'title' => $report->title(),
            'columns' => $report->columns(),
            'rows' => $page->through(fn (Model $m) => $report->row($m)),
            'summary' => $report->summary(),
            'filters' => $filters,
            'reports' => collect(ReportFactory::REPORTS)->map(fn ($class, $key) => ['key' => $key, 'title' => ReportFactory::make($key)->title()])->values(),
            'options' => [
                ...Options::for(['tianguis', 'routes', 'users', 'concepts', 'merchant_statuses', 'business_types', 'commerce_types']),
                'annuality_statuses' => AnnualityStatus::options(),
            ],
            'canExport' => $request->user()->can(Permission::ReportsExport->value),
        ]);
    }

    public function export(Request $request, string $type, ReportExporter $exporter): HttpResponse
    {
        abort_unless($request->user()?->can(Permission::ReportsExport->value), 403);

        $format = $request->validate(['format' => ['required', Rule::in(ReportExporter::FORMATS)]])['format'];

        return $exporter->export(ReportFactory::make($type, $request->except('format')), $format);
    }
}
