<?php

namespace App\Services;

use App\Enums\AnnualityStatus;
use App\Enums\AuditAction;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Annuality;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Impresión, reimpresión controlada y cancelación de recibos.
 * Ninguna de estas operaciones genera un nuevo cobro.
 */
class ReceiptService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Registra la impresión original del recibo. Solo puede hacerse una vez;
     * las siguientes deben pasar por reprint() con un motivo.
     */
    public function markPrinted(Receipt $receipt): Receipt
    {
        return DB::transaction(function () use ($receipt) {
            /** @var Receipt $locked */
            $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

            $this->ensureNotCancelled($locked);

            if ($locked->printed_at !== null) {
                throw new BusinessRuleException(
                    "El recibo {$locked->folio} ya fue impreso. Para imprimirlo de nuevo solicite una reimpresión con motivo.",
                    'already_printed',
                );
            }

            $locked->update(['printed_at' => now()]);
            $this->audit->log(AuditAction::Print, 'Recibos', $locked, null, null, "Impresión del recibo {$locked->folio}");

            return $locked;
        });
    }

    public function reprint(Receipt $receipt, User $user, string $reason): ReceiptReprint
    {
        return DB::transaction(function () use ($receipt, $user, $reason) {
            /** @var Receipt $locked */
            $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

            $this->ensureNotCancelled($locked);

            $number = $locked->reprint_count + 1;
            $now = now();

            $reprint = $locked->reprints()->create([
                'user_id' => $user->id,
                'reason' => $reason,
                'reprint_number' => $number,
                'reprint_date' => $now->toDateString(),
                'reprint_time' => $now->format('H:i:s'),
            ]);

            $locked->update([
                'reprint_count' => $number,
                'status' => ReceiptStatus::Reprinted,
                'printed_at' => $locked->printed_at ?? $now,
            ]);

            $this->audit->log(AuditAction::Reprint, 'Recibos', $locked, null, [
                'reimpresion' => $number,
                'motivo' => $reason,
            ], "Reimpresión No. {$number} del recibo {$locked->folio}", $user);

            return $reprint;
        });
    }

    /**
     * Cancela un recibo sin eliminarlo. Los pagos quedan cancelados y las
     * anualidades vuelven a estar pendientes. El folio no se reutiliza.
     */
    public function cancel(Receipt $receipt, User $user, string $reason): Receipt
    {
        return DB::transaction(function () use ($receipt, $user, $reason) {
            /** @var Receipt $locked */
            $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

            $this->ensureNotCancelled($locked);
            $previous = $locked->status->value;

            $locked->update([
                'status' => ReceiptStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            $paymentIds = $locked->payments()->pluck('id');
            $locked->payments()->update(['status' => PaymentStatus::Cancelled->value]);

            Annuality::query()->whereIn('payment_id', $paymentIds)->update([
                'status' => AnnualityStatus::Pending->value,
                'payment_id' => null,
                'paid_at' => null,
            ]);

            $this->audit->log(AuditAction::Cancel, 'Recibos', $locked, ['status' => $previous], [
                'status' => ReceiptStatus::Cancelled->value,
                'motivo' => $reason,
            ], "Cancelación del recibo {$locked->folio}", $user);

            return $locked;
        });
    }

    private function ensureNotCancelled(Receipt $receipt): void
    {
        if ($receipt->isCancelled()) {
            throw new BusinessRuleException("El recibo {$receipt->folio} está cancelado.", 'receipt_cancelled');
        }
    }
}
