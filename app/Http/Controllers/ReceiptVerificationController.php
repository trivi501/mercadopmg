<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\View\View;

/**
 * Página pública a la que apunta el QR del ticket. Solo confirma folio,
 * fecha, importe y vigencia; no expone datos personales.
 */
class ReceiptVerificationController extends Controller
{
    public function __invoke(string $uuid): View
    {
        $receipt = Receipt::query()->where('uuid', $uuid)->with('merchant:id,folio')->firstOrFail();

        return view('receipts.verify', ['receipt' => $receipt]);
    }
}
