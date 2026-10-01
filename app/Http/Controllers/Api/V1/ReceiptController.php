<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelReceiptRequest;
use App\Http\Requests\Billing\ReprintRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Services\ReceiptService;
use App\Services\TicketPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly TicketPresenter $tickets,
    ) {}

    /**
     * Historial de recibos. Con `mine=1` sólo los emitidos por el usuario
     * (historial del recaudador en la app). Filtros: date_from, date_to,
     * merchant_id, status, search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Receipt::class);

        $filters = $request->validate([
            'mine' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'merchant_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($request->boolean('mine')) {
            $filters['user_id'] = $request->user()?->id;
        }

        return ReceiptResource::collection(
            Receipt::query()->filter($filters)
                ->with(['merchant:id,folio,taxpayer_name', 'items', 'user:id,name'])
                ->latest('issued_at')->latest('id')
                ->paginate((int) ($filters['per_page'] ?? 30)),
        );
    }

    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        return response()->json(['data' => self::payload($receipt, $this->tickets, $request->integer('paper_width', 58))]);
    }

    /**
     * Ticket en PDF para descargar o compartir desde la app.
     * Con ?reprint={id} incluye la leyenda de esa reimpresión.
     */
    public function pdf(Request $request, Receipt $receipt): Response
    {
        $this->authorize('view', $receipt);

        $reprint = $request->filled('reprint') ? $receipt->reprints()->with('user:id,name')->findOrFail($request->integer('reprint')) : null;

        return $this->tickets->pdf($receipt, $reprint)->download("recibo-{$receipt->folio}.pdf");
    }

    /**
     * Impresión original. Solo se permite una vez; después se requiere reimpresión.
     */
    public function print(Request $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('print', $receipt);
        $receipt = $this->receipts->markPrinted($receipt);

        return response()->json(['data' => self::payload($receipt, $this->tickets, $request->integer('paper_width', 58))]);
    }

    /**
     * Reimpresión controlada: registra número, usuario y motivo; no genera cobro.
     */
    public function reprint(ReprintRequest $request, Receipt $receipt): JsonResponse
    {
        $reprint = $this->receipts->reprint($receipt, $request->user(), (string) $request->input('reason'));

        return response()->json([
            'data' => self::payload($receipt->refresh(), $this->tickets, $request->integer('paper_width', 58), $reprint),
            'message' => "Reimpresión No. {$reprint->reprint_number} registrada.",
        ], 201);
    }

    public function cancel(CancelReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        $receipt = $this->receipts->cancel($receipt, $request->user(), (string) $request->input('reason'));

        return response()->json(['data' => new ReceiptResource($receipt->load(['items', 'canceller'])), 'message' => 'Recibo cancelado.']);
    }

    /**
     * Recibo + ticket listo para imprimir en impresora térmica.
     *
     * @return array<string, mixed>
     */
    public static function payload(Receipt $receipt, TicketPresenter $tickets, int $paperWidth = 58, ?ReceiptReprint $reprint = null): array
    {
        $paperWidth = in_array($paperWidth, [58, 80], true) ? $paperWidth : 58;
        $receipt->load(['merchant', 'items', 'user:id,name', 'tianguis:id,name', 'route:id,name', 'reprints.user:id,name']);
        $ticket = $tickets->present($receipt, $reprint);

        return [
            'receipt' => new ReceiptResource($receipt),
            'ticket' => [
                ...$ticket,
                'paper_width' => $paperWidth,
                'lines' => $tickets->lines($ticket, $paperWidth),
                'pdf_url' => route('api.v1.receipts.pdf', $receipt),
            ],
        ];
    }
}
