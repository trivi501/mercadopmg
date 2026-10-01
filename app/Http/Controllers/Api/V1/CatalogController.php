<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommerceType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeResource;
use App\Http\Resources\ConceptResource;
use App\Http\Resources\RouteResource;
use App\Http\Resources\TianguisResource;
use App\Models\BusinessType;
use App\Models\Concept;
use App\Models\MarketRoute;
use App\Models\Setting;
use App\Models\Tianguis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogos de solo lectura para la app (se guardan en Room para uso sin conexión).
 */
class CatalogController extends Controller
{
    public function tianguis(): AnonymousResourceCollection
    {
        return TianguisResource::collection(Tianguis::query()->active()->orderBy('name')->get());
    }

    public function routes(): AnonymousResourceCollection
    {
        return RouteResource::collection(MarketRoute::query()->active()->orderBy('name')->get());
    }

    public function businessTypes(): AnonymousResourceCollection
    {
        return BusinessTypeResource::collection(BusinessType::query()->active()->orderBy('name')->get());
    }

    public function concepts(): AnonymousResourceCollection
    {
        return ConceptResource::collection(Concept::query()->active()->orderBy('code')->get());
    }

    /**
     * Todos los catálogos en una sola llamada para la sincronización inicial.
     */
    public function all(): JsonResponse
    {
        return response()->json(['data' => [
            'business_types' => $this->businessTypes(),
            'tianguis' => $this->tianguis(),
            'routes' => $this->routes(),
            'concepts' => $this->concepts(),
            'commerce_types' => CommerceType::options(),
            'payment_methods' => PaymentMethod::options(),
            'settings' => [
                'municipality_name' => Setting::get('municipality_name', 'MUNICIPIO'),
                'ticket_footer' => Setting::get('ticket_footer'),
            ],
            'server_time' => now()->toIso8601String(),
        ]]);
    }
}
