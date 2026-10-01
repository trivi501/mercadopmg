<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChargeRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\TicketPresenter;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Registra un cobro. Responde 201 si se creó, 200 si el UUID ya existía
     * (reintento) — en ambos casos devuelve el recibo y el ticket.
     */
    public function store(ChargeRequest $request, PaymentService $payments, TicketPresenter $tickets): JsonResponse
    {
        $receipt = $payments->charge($request->chargeData(), $request->user());
        $duplicate = ! $receipt->wasRecentlyCreated;

        return response()->json([
            'data' => ReceiptController::payload($receipt, $tickets, $request->integer('paper_width', 58)),
            'duplicate' => $duplicate,
            'message' => $duplicate ? 'La operación ya había sido registrada; no se generó un nuevo cobro.' : 'Cobro registrado.',
        ], $duplicate ? 200 : 201);
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment->receipt);

        return new PaymentResource($payment->load(['concept', 'receipt', 'user:id,name']));
    }
}
