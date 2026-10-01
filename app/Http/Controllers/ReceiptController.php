<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Requests\Billing\CancelReceiptRequest;
use App\Http\Requests\Billing\ReprintRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Services\AuditLogger;
use App\Services\ReceiptService;
use App\Services\TicketPresenter;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Historial de recibos, ticket, PDF, reimpresión controlada y cancelación.
 */
class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly TicketPresenter $tickets,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Receipt::class);

        $filters = $request->only(['date_from', 'date_to', 'merchant_id', 'folio', 'tianguis_id', 'route_id', 'user_id', 'concept_id', 'status', 'search']);
        $base = Receipt::query()->filter($filters);

        return Inertia::render('receipts/index', [
            'receipts' => ReceiptResource::collection(
                (clone $base)->with(['merchant:id,folio,taxpayer_name', 'user:id,name', 'tianguis:id,name', 'route:id,name', 'items'])
                    ->latest('issued_at')->latest('id')
                    ->paginate(25)->withQueryString(),
            ),
            'totals' => [
                'count' => (clone $base)->count(),
                'amount' => number_format((float) (clone $base)->valid()->sum('total'), 2, '.', ''),
            ],
            'filters' => $filters,
            'options' => Options::for(['tianguis', 'routes', 'users', 'concepts', 'receipt_statuses']),
        ]);
    }

    public function show(Request $request, Receipt $receipt): Response
    {
        $this->authorize('view', $receipt);

        $receipt->load(['merchant', 'items', 'user:id,name', 'tianguis:id,name', 'route:id,name', 'reprints.user:id,name', 'canceller:id,name', 'paymentDetails']);
        $user = $request->user();

        return Inertia::render('receipts/show', [
            'receipt' => new ReceiptResource($receipt),
            'ticket' => $this->tickets->present($receipt),
            'qr' => $this->tickets->qrDataUri($receipt),
            'can' => [
                'print' => $user?->can('print', $receipt) && ! $receipt->isCancelled() && $receipt->printed_at === null,
                'reprint' => $user?->can('reprint', $receipt) && ! $receipt->isCancelled(),
                'cancel' => $user?->can('cancel', $receipt) && ! $receipt->isCancelled(),
            ],
        ]);
    }

    /**
     * Vista para impresora térmica (58/80 mm). La primera vez registra la
     * impresión original; con ?reprint={id} muestra la leyenda de reimpresión.
     */
    public function thermal(Request $request, Receipt $receipt): View
    {
        $this->authorize('view', $receipt);

        $reprint = $request->filled('reprint')
            ? $receipt->reprints()->with('user:id,name')->findOrFail($request->integer('reprint'))
            : null;

        $autoprint = false;
        if (! $reprint && $request->boolean('print')) {
            $this->authorize('print', $receipt);
            $receipt = $this->receipts->markPrinted($receipt);
            $autoprint = true;
        }

        return view('receipts.thermal', [
            't' => $this->tickets->present($receipt, $reprint),
            'qr' => $this->tickets->qrDataUri($receipt),
            'width' => $request->integer('width', 58) === 80 ? 80 : 58,
            'autoprint' => $autoprint || ($reprint && $request->boolean('print')),
            'reprintQuery' => $reprint ? '&reprint='.$reprint->id : '',
        ]);
    }

    public function pdf(Request $request, Receipt $receipt): HttpResponse
    {
        $this->authorize('view', $receipt);

        $reprint = $request->filled('reprint') ? $receipt->reprints()->with('user:id,name')->findOrFail($request->integer('reprint')) : null;
        $this->audit->log(AuditAction::View, 'Recibos', $receipt, null, null, "Descarga PDF del recibo {$receipt->folio}");

        return $this->tickets->pdf($receipt, $reprint)->stream("recibo-{$receipt->folio}.pdf");
    }

    public function print(Receipt $receipt): RedirectResponse
    {
        $this->authorize('print', $receipt);
        $this->receipts->markPrinted($receipt);

        return back();
    }

    public function reprint(ReprintRequest $request, Receipt $receipt): RedirectResponse
    {
        $reprint = $this->receipts->reprint($receipt, $request->user(), (string) $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Reimpresión No. {$reprint->reprint_number} registrada."]);
        Inertia::flash('reprint_id', $reprint->id);

        return back();
    }

    public function cancel(CancelReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        $this->receipts->cancel($receipt, $request->user(), (string) $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Recibo {$receipt->folio} cancelado."]);

        return back();
    }

    /**
     * Bitácora de reimpresiones (solo consulta; los registros no se eliminan).
     */
    public function reprints(Request $request): Response
    {
        abort_unless($request->user()?->can(Permission::ReprintsView->value), 403);

        $filters = $request->only(['date_from', 'date_to', 'user_id', 'search']);

        $reprints = ReceiptReprint::query()
            ->with(['receipt:id,folio,merchant_id,total', 'receipt.merchant:id,folio,taxpayer_name', 'user:id,name'])
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('reprint_date', '>=', Carbon::parse($v)->toDateString()))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('reprint_date', '<=', Carbon::parse($v)->toDateString()))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('receipt', fn ($r) => $r->where('folio', 'like', "%{$v}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ReceiptReprint $r) => [
                'id' => $r->id,
                'receipt_id' => $r->receipt_id,
                'receipt_folio' => $r->receipt->folio,
                'merchant' => $r->receipt->merchant->folio.' · '.$r->receipt->merchant->taxpayer_name,
                'total' => $r->receipt->total,
                'number' => $r->reprint_number,
                'date' => $r->reprint_date->format('d/m/Y'),
                'time' => substr($r->reprint_time, 0, 5),
                'user' => $r->user->name,
                'reason' => $r->reason,
            ]);

        return Inertia::render('receipts/reprints', [
            'reprints' => $reprints,
            'filters' => $filters,
            'options' => Options::for(['users']),
        ]);
    }
}
