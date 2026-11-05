<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CloseCashSessionRequest;
use App\Http\Requests\Billing\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashRegisterSession;
use App\Services\CashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cortes de caja del recaudador en la app: inicio de cobro, corte final y
 * reporte en renglones para la impresora térmica (`lines`, igual que el ticket).
 */
class CashSessionController extends Controller
{
    public function __construct(private readonly CashRegisterService $cash) {}

    /**
     * Corte abierto del usuario (o null) y el último cerrado para reimprimirlo.
     */
    public function current(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CashRegisterSession::class);

        $user = $request->user();
        $current = $this->cash->current($user);
        $last = CashRegisterSession::query()->where('user_id', $user->id)->where('status', 'closed')->latest('closed_at')->first();

        return response()->json(['data' => [
            'current' => $current ? $this->payload($current, $request) : null,
            'last_closed' => $last ? new CashSessionResource($last) : null,
        ]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashRegisterSession::class);

        return CashSessionResource::collection(
            CashRegisterSession::query()->where('user_id', $request->user()->id)->latest('opened_at')->paginate(30),
        );
    }

    public function store(OpenCashSessionRequest $request): JsonResponse
    {
        $session = $this->cash->open($request->user(), [
            ...$request->validated(),
            'source' => 'android',
            'device' => $request->header('X-Device-Name') ?: $request->userAgent(),
        ]);

        return response()->json(['data' => $this->payload($session, $request)], $session->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, CashRegisterSession $cashSession): JsonResponse
    {
        $this->authorize('view', $cashSession);

        return response()->json(['data' => $this->payload($cashSession, $request)]);
    }

    public function close(CloseCashSessionRequest $request, CashRegisterSession $cashSession): JsonResponse
    {
        $session = $this->cash->close($cashSession, $request->user(), (string) $request->input('counted_cash'), $request->input('closing_notes'));

        return response()->json(['data' => $this->payload($session, $request)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CashRegisterSession $session, Request $request): array
    {
        $session->loadMissing(['user:id,name', 'closer:id,name']);

        return [
            'session' => new CashSessionResource($session),
            'summary' => $this->cash->summary($session),
            'lines' => $this->cash->lines($session, $request->integer('paper_width', 58) === 80 ? 80 : 58),
        ];
    }
}
