<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\MerchantStatus;
use App\Enums\Permission;
use App\Http\Requests\Merchants\MerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Merchant;
use App\Models\ReceiptReprint;
use App\Reports\MerchantsReport;
use App\Reports\ReportExporter;
use App\Services\AccountStatementService;
use App\Services\AuditLogger;
use App\Services\MerchantService;
use App\Services\QrCodeService;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MerchantController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchants,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Merchant::class);

        $filters = $request->only(['search', 'status', 'business_type_id', 'tianguis_id', 'route_id', 'commerce_type']);
        $sort = in_array($request->input('sort'), ['folio', 'taxpayer_name', 'registered_at', 'meters'], true) ? $request->input('sort') : 'folio';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $merchants = (new MerchantsReport($filters))->query()
            ->reorder($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('merchants/index', [
            'merchants' => MerchantResource::collection($merchants),
            'filters' => [...$filters, 'sort' => $sort, 'direction' => $direction],
            'options' => Options::for(['business_types', 'tianguis', 'routes', 'commerce_types', 'merchant_statuses']),
        ]);
    }

    public function export(Request $request, ReportExporter $exporter): HttpResponse
    {
        abort_unless($request->user()?->can(Permission::ReportsExport->value), 403);
        $format = $request->validate(['format' => ['required', Rule::in(ReportExporter::FORMATS)]])['format'];

        return $exporter->export(new MerchantsReport($request->except('format')), $format);
    }

    public function create(): Response
    {
        $this->authorize('create', Merchant::class);

        return Inertia::render('merchants/form', [
            'merchant' => null,
            'options' => Options::for(['business_types', 'tianguis', 'routes', 'commerce_types', 'merchant_statuses']),
        ]);
    }

    public function store(MerchantRequest $request): RedirectResponse
    {
        $merchant = $this->merchants->create($request->merchantData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Comerciante registrado con folio {$merchant->folio}."]);

        return to_route('merchants.show', $merchant);
    }

    public function show(Request $request, Merchant $merchant, AccountStatementService $statements, QrCodeService $qr): Response
    {
        $this->authorize('view', $merchant);
        $this->audit->log(AuditAction::View, 'Padrón', $merchant, null, null, "Consulta del comerciante {$merchant->folio}");

        $merchant->load(['businessType', 'tianguis', 'route', 'measurements.user:id,name', 'creator:id,name']);
        $user = $request->user();

        return Inertia::render('merchants/show', [
            'merchant' => new MerchantResource($merchant),
            'account' => $statements->forMerchant($merchant, $request->integer('year') ?: null),
            'qr' => $qr->svg($merchant->qrPayload(), 160),
            'receipts' => fn () => ReceiptResource::collection(
                $merchant->receipts()->with(['items', 'user:id,name'])->latest('issued_at')->limit(50)->get(),
            ),
            'reprints' => fn () => ReceiptReprint::query()
                ->whereHas('receipt', fn ($q) => $q->where('merchant_id', $merchant->id))
                ->with(['receipt:id,folio', 'user:id,name'])->latest('id')->limit(50)->get()
                ->map(fn (ReceiptReprint $r) => [
                    'id' => $r->id, 'receipt_id' => $r->receipt_id, 'receipt_folio' => $r->receipt->folio, 'number' => $r->reprint_number,
                    'date' => $r->reprint_date->format('d/m/Y'), 'time' => substr($r->reprint_time, 0, 5),
                    'user' => $r->user->name, 'reason' => $r->reason,
                ]),
            'can' => [
                'update' => $user?->can('update', $merchant),
                'delete' => $user?->can('delete', $merchant),
                'charge' => $user?->can('charge', $merchant) && $merchant->status === MerchantStatus::Active,
            ],
        ]);
    }

    public function edit(Merchant $merchant): Response
    {
        $this->authorize('update', $merchant);

        return Inertia::render('merchants/form', [
            'merchant' => new MerchantResource($merchant),
            'options' => Options::for(['business_types', 'tianguis', 'routes', 'commerce_types', 'merchant_statuses']),
        ]);
    }

    public function update(MerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $this->merchants->update($merchant, $request->merchantData(), $request->user(), $request->input('meters_reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comerciante actualizado.']);

        return to_route('merchants.show', $merchant);
    }

    /**
     * Baja lógica del padrón (soft delete). Los pagos y recibos se conservan.
     */
    public function destroy(Merchant $merchant): RedirectResponse
    {
        $this->authorize('delete', $merchant);

        $merchant->update(['status' => MerchantStatus::Inactive]);
        $merchant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Comerciante {$merchant->folio} dado de baja."]);

        return to_route('merchants.index');
    }

    public function qr(Merchant $merchant, QrCodeService $qr): HttpResponse
    {
        $this->authorize('view', $merchant);

        return response($qr->svg($merchant->qrPayload(), 400), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-'.$merchant->folio.'.svg"',
        ]);
    }

    /**
     * Mapa: comerciantes con coordenadas; con lat/lng/radius responde "¿quién está en esta zona?".
     */
    public function map(Request $request): Response
    {
        $this->authorize('viewAny', Merchant::class);

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0.05', 'max:20'],
            'tianguis_id' => ['nullable', 'integer'],
            'route_id' => ['nullable', 'integer'],
        ]);

        $zone = isset($validated['lat'], $validated['lng']);
        $merchants = $zone
            ? $this->merchants->nearby((float) $validated['lat'], (float) $validated['lng'], (float) ($validated['radius'] ?? 0.5), 1000)
            : Merchant::query()
                ->whereNotNull('latitude')
                ->when($validated['tianguis_id'] ?? null, fn ($q, $v) => $q->where('tianguis_id', $v))
                ->when($validated['route_id'] ?? null, fn ($q, $v) => $q->where('route_id', $v))
                ->with(['businessType:id,name', 'tianguis:id,name', 'route:id,name'])
                ->limit(2000)
                ->get();

        return Inertia::render('merchants/map', [
            'merchants' => $merchants->map(fn (Merchant $m) => [
                'id' => $m->id,
                'folio' => $m->folio,
                'taxpayer_name' => $m->taxpayer_name,
                'business_type' => $m->businessType->name,
                'tianguis' => $m->tianguis?->name,
                'route' => $m->route?->name,
                'status' => $m->status->value,
                'latitude' => (float) $m->latitude,
                'longitude' => (float) $m->longitude,
                'distance_km' => $m->getAttribute('distance_km'),
            ])->values(),
            'zone' => $zone ? ['lat' => (float) $validated['lat'], 'lng' => (float) $validated['lng'], 'radius' => (float) ($validated['radius'] ?? 0.5)] : null,
            'filters' => $request->only(['tianguis_id', 'route_id']),
            'options' => Options::for(['tianguis', 'routes']),
        ]);
    }
}
