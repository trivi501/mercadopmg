<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CashSessionStatus;
use App\Http\Requests\Billing\CloseCashSessionRequest;
use App\Http\Requests\Billing\OpenCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Http\Resources\ReceiptResource;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Cortes de caja: inicio de cobro, corte final con arqueo y reporte imprimible.
 */
class CashSessionController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cash,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CashRegisterSession::class);

        $user = $request->user();
        $viewAll = (bool) $user?->can('viewAll', CashRegisterSession::class);
        $filters = $request->only(['user_id', 'status', 'date_from', 'date_to']);
        $current = $user ? $this->cash->current($user) : null;

        $sessions = CashRegisterSession::query()
            ->with(['user:id,name', 'closer:id,name'])
            ->when(! $viewAll, fn ($q) => $q->where('user_id', $user?->id))
            ->when($viewAll && ($filters['user_id'] ?? null), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('opened_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('opened_at', '<=', Carbon::parse($v)->endOfDay()))
            ->latest('opened_at')->latest('id')
            ->paginate(20)->withQueryString();

        return Inertia::render('cash-sessions/index', [
            'sessions' => CashSessionResource::collection($sessions),
            'current' => $current ? [
                'session' => new CashSessionResource($current),
                'summary' => $this->cash->summary($current),
            ] : null,
            'canOpen' => (bool) $user?->can('create', CashRegisterSession::class),
            'viewAll' => $viewAll,
            'filters' => $filters,
            'options' => [
                'users' => $viewAll ? User::query()->orderBy('name')->get(['id', 'name']) : [],
                'statuses' => CashSessionStatus::options(),
            ],
        ]);
    }

    public function store(OpenCashSessionRequest $request): RedirectResponse
    {
        $session = $this->cash->open($request->user(), [...$request->validated(), 'source' => 'web']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cobro iniciado. Corte {$session->folio} abierto."]);

        return back();
    }

    public function show(Request $request, CashRegisterSession $cashSession): Response
    {
        $this->authorize('view', $cashSession);

        $cashSession->load(['user:id,name', 'closer:id,name']);

        return Inertia::render('cash-sessions/show', [
            'session' => new CashSessionResource($cashSession),
            'summary' => $this->cash->summary($cashSession),
            'receipts' => ReceiptResource::collection(
                $cashSession->receipts()->with(['merchant:id,folio,taxpayer_name', 'items'])->latest('id')->get(),
            ),
            // isOpen() explícito: el administrador pasa todas las policies por Gate::before.
            'can' => ['close' => $cashSession->isOpen() && (bool) $request->user()?->can('close', $cashSession)],
        ]);
    }

    public function close(CloseCashSessionRequest $request, CashRegisterSession $cashSession): RedirectResponse
    {
        $session = $this->cash->close($cashSession, $request->user(), (string) $request->input('counted_cash'), $request->input('closing_notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Corte {$session->folio} cerrado."]);

        return to_route('cash-sessions.show', $session);
    }

    /**
     * Reporte para impresora térmica (58/80 mm) en el navegador.
     */
    public function thermal(Request $request, CashRegisterSession $cashSession): View
    {
        $this->authorize('view', $cashSession);
        $width = $request->integer('width', 58) === 80 ? 80 : 58;

        return view('cash-sessions.thermal', [
            'session' => $cashSession,
            'lines' => $this->cash->lines($cashSession, $width),
            'width' => $width,
            'autoprint' => $request->boolean('print'),
        ]);
    }

    public function pdf(CashRegisterSession $cashSession): HttpResponse
    {
        $this->authorize('view', $cashSession);
        $this->audit->log(AuditAction::Export, 'Cortes de caja', $cashSession, null, null, "Reporte PDF del corte {$cashSession->folio}");

        return $this->cash->pdf($cashSession)->stream("corte-{$cashSession->folio}.pdf");
    }
}
