<?php

namespace App\Http\Controllers;

use App\Enums\ConceptType;
use App\Http\Requests\Billing\ChargeRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Concept;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\AccountStatementService;
use App\Services\PaymentService;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla de cobro (boletos, anualidad y otros conceptos).
 */
class ChargeController extends Controller
{
    public function create(Request $request, AccountStatementService $statements): Response
    {
        $this->authorize('create', Receipt::class);

        $merchant = $request->filled('merchant')
            ? Merchant::query()->with(['businessType', 'tianguis', 'route'])->find($request->integer('merchant'))
            : null;

        return Inertia::render('charges/create', [
            'merchant' => $merchant ? new MerchantResource($merchant) : null,
            'account' => $merchant ? $statements->forMerchant($merchant) : null,
            'search' => fn () => $request->filled('q')
                ? MerchantResource::collection(
                    Merchant::query()->with(['businessType:id,name', 'tianguis:id,name'])->search($request->string('q')->toString())->limit(10)->get(),
                )
                : [],
            'concepts' => Concept::query()->active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'amount'])
                ->map(fn (Concept $c) => [...$c->only(['id', 'code', 'name', 'amount']), 'type' => $c->type->value]),
            'ticketTypes' => [ConceptType::Ticket->value],
            'options' => Options::for(['payment_methods', 'tianguis']),
            'currentYear' => (int) now()->year,
        ]);
    }

    public function store(ChargeRequest $request, PaymentService $payments): RedirectResponse
    {
        $receipt = $payments->charge($request->chargeData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cobro registrado. Recibo {$receipt->folio} por \${$receipt->total}."]);

        return to_route('receipts.show', $receipt);
    }
}
