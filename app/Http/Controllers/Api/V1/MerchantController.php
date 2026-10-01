<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchants\MerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Merchant;
use App\Services\AccountStatementService;
use App\Services\AuditLogger;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class MerchantController extends Controller
{
    public function __construct(
        private readonly MerchantService $merchants,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Búsqueda por folio/QR, nombre, giro, tianguis, ruta o cercanía (lat, lng, radius en km).
     * `updated_since` permite sincronización incremental del padrón.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Merchant::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'folio' => ['nullable', 'string', 'max:20'],
            'business_type_id' => ['nullable', 'integer'],
            'tianguis_id' => ['nullable', 'integer'],
            'route_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius' => ['nullable', 'numeric', 'min:0.01', 'max:20'],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if (isset($validated['lat'], $validated['lng'])) {
            return MerchantResource::collection(
                $this->merchants->nearby((float) $validated['lat'], (float) $validated['lng'], (float) ($validated['radius'] ?? 0.5)),
            );
        }

        $query = Merchant::query()
            ->with(['businessType:id,name', 'tianguis:id,name', 'route:id,name'])
            ->when($validated['folio'] ?? null, fn ($q, $v) => $q->where('folio', $v))
            ->search($validated['search'] ?? null)
            ->when($validated['business_type_id'] ?? null, fn ($q, $v) => $q->where('business_type_id', $v))
            ->when($validated['tianguis_id'] ?? null, fn ($q, $v) => $q->where('tianguis_id', $v))
            ->when($validated['route_id'] ?? null, fn ($q, $v) => $q->where('route_id', $v))
            ->when($validated['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($validated['updated_since'] ?? null, fn ($q, $v) => $q->where('updated_at', '>', Carbon::parse($v)))
            ->orderBy('folio');

        return MerchantResource::collection($query->paginate((int) ($validated['per_page'] ?? 50))->withQueryString());
    }

    public function store(MerchantRequest $request): JsonResponse
    {
        $merchant = $this->merchants->create($request->merchantData(), $request->user());

        return (new MerchantResource($merchant->load(['businessType', 'tianguis', 'route'])))
            ->response()
            ->setStatusCode($merchant->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Merchant $merchant): MerchantResource
    {
        $this->authorize('view', $merchant);
        $this->audit->log(AuditAction::View, 'Padrón', $merchant, null, null, "Consulta del comerciante {$merchant->folio}");

        return new MerchantResource($merchant->load(['businessType', 'tianguis', 'route', 'measurements.user:id,name']));
    }

    /**
     * Búsqueda exacta por el contenido del QR (folio del comerciante).
     */
    public function byFolio(string $folio): MerchantResource
    {
        $merchant = Merchant::query()->where('folio', strtoupper(trim($folio)))->firstOrFail();

        return $this->show($merchant);
    }

    public function update(MerchantRequest $request, Merchant $merchant): MerchantResource
    {
        $merchant = $this->merchants->update($merchant, $request->merchantData(), $request->user(), $request->input('meters_reason'));

        return new MerchantResource($merchant->load(['businessType', 'tianguis', 'route']));
    }

    public function account(Request $request, Merchant $merchant, AccountStatementService $statements): JsonResponse
    {
        $this->authorize('view', $merchant);
        $year = $request->integer('fiscal_year') ?: null;

        return response()->json(['data' => $statements->forMerchant($merchant, $year)]);
    }

    public function payments(Merchant $merchant): AnonymousResourceCollection
    {
        $this->authorize('view', $merchant);

        return PaymentResource::collection(
            $merchant->payments()->with(['concept', 'receipt:id,folio', 'user:id,name'])->latest('paid_at')->paginate(50),
        );
    }

    public function receipts(Merchant $merchant): AnonymousResourceCollection
    {
        $this->authorize('view', $merchant);

        return ReceiptResource::collection(
            $merchant->receipts()->with(['items', 'user:id,name', 'reprints'])->latest('issued_at')->paginate(50),
        );
    }
}
