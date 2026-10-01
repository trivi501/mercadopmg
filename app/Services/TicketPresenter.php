<?php

namespace App\Services;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\ReceiptReprint;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

/**
 * Arma el contenido del ticket de un recibo. La misma estructura alimenta el
 * PDF, la vista para impresora térmica del navegador y la API que consume la
 * app Android (que imprime por Bluetooth con ESC/POS usando `lines`).
 */
class TicketPresenter
{
    /** Columnas por ancho de papel con fuente estándar ESC/POS. */
    public const array COLUMNS = [58 => 32, 80 => 48];

    public function __construct(private readonly QrCodeService $qr) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Receipt $receipt, ?ReceiptReprint $reprint = null): array
    {
        $receipt->loadMissing(['merchant.businessType', 'tianguis', 'route', 'user', 'items']);
        $merchant = $receipt->merchant;

        return [
            'header' => [
                'municipality' => Str::upper((string) Setting::get('municipality_name', 'MUNICIPIO')),
                'system' => 'PADRÓN DE COMERCIANTES',
                'title' => 'RECIBO DE PAGO',
            ],
            'folio' => $receipt->folio,
            'uuid' => $receipt->uuid,
            'date' => $receipt->issued_at->format('d/m/Y'),
            'time' => $receipt->issued_at->format('H:i'),
            'taxpayer' => Str::upper($merchant->taxpayer_name),
            'merchant_folio' => $merchant->folio,
            'business_type' => Str::upper($merchant->business_description ?: $merchant->businessType->name),
            'tianguis' => $receipt->tianguis ? Str::upper($receipt->tianguis->name) : null,
            'route' => $receipt->route ? Str::upper($receipt->route->name) : null,
            'items' => $receipt->items->map(fn ($item) => [
                'concept' => Str::upper($item->description),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount,
            ])->all(),
            'total' => $receipt->total,
            'currency' => 'MXN',
            'payment_method' => Str::upper($receipt->payment_method->label()),
            'user' => Str::upper($receipt->user->name),
            'status' => $receipt->status->value,
            'is_cancelled' => $receipt->status === ReceiptStatus::Cancelled,
            'cancellation_reason' => $receipt->cancellation_reason,
            'reprint' => $reprint ? [
                'number' => $reprint->reprint_number,
                'reason' => $reprint->reason,
                'date' => $reprint->reprint_date->format('d/m/Y'),
                'time' => substr($reprint->reprint_time, 0, 5),
                'user' => Str::upper($reprint->user->name),
            ] : null,
            'qr_content' => $this->qrContent($receipt),
            'footer' => (string) Setting::get('ticket_footer', 'Conserve este recibo como comprobante de pago.'),
        ];
    }

    /**
     * Ticket en PDF de 80 mm de ancho (alto ajustado al contenido).
     */
    public function pdf(Receipt $receipt, ?ReceiptReprint $reprint = null): DomPdf
    {
        $ticket = $this->present($receipt, $reprint);
        $height = 360 + count($ticket['items']) * 45 + ($reprint ? 70 : 0);

        return Pdf::loadView('pdf.receipt', ['t' => $ticket, 'qr' => $this->qrDataUri($receipt)])
            ->setPaper([0, 0, 226.77, $height]);
    }

    public function qrContent(Receipt $receipt): string
    {
        return route('receipts.verify', $receipt->uuid);
    }

    public function qrDataUri(Receipt $receipt, int $size = 180): string
    {
        return $this->qr->pngDataUri($this->qrContent($receipt), $size);
    }

    /**
     * Ticket en texto plano con el ancho de columnas de la impresora térmica.
     *
     * @param  array<string, mixed>  $ticket
     * @return list<array{text: string, align: string, bold: bool, size: string}>
     */
    public function lines(array $ticket, int $paperWidth = 58): array
    {
        $cols = self::COLUMNS[$paperWidth] ?? 32;
        $out = [];
        $push = function (string $text, string $align = 'left', bool $bold = false, string $size = 'normal') use (&$out) {
            $out[] = ['text' => $text, 'align' => $align, 'bold' => $bold, 'size' => $size];
        };
        $center = fn (string $t, bool $bold = false, string $size = 'normal') => $push($t, 'center', $bold, $size);
        $left = fn (string $t, bool $bold = false) => $push($t, 'left', $bold);
        $sep = fn () => $push(str_repeat('-', $cols));
        $pair = fn (string $label, string $value) => $push($this->pair($label, $value, $cols));

        $center($ticket['header']['municipality'], true);
        $center($ticket['header']['system'], true);
        $center($ticket['header']['title'], true);

        if ($ticket['reprint']) {
            $center('*** REIMPRESIÓN ***', true, 'double');
            $center('No. '.$ticket['reprint']['number'], true);
        }
        if ($ticket['is_cancelled']) {
            $center('*** CANCELADO ***', true, 'double');
        }

        $sep();
        $pair('Folio:', $ticket['folio']);
        $pair('Fecha:', $ticket['date']);
        $pair('Hora:', $ticket['time']);
        $sep();
        $left('Contribuyente:');
        $left($ticket['taxpayer'], true);
        $pair('Folio contribuyente:', $ticket['merchant_folio']);
        $left('Giro: '.$ticket['business_type']);
        if ($ticket['tianguis']) {
            $left('Tianguis: '.$ticket['tianguis']);
        }
        if ($ticket['route']) {
            $left('Ruta: '.$ticket['route']);
        }
        $sep();

        foreach ($ticket['items'] as $item) {
            $left('Concepto: '.$item['concept']);
            $pair('Cantidad:', (string) $item['quantity']);
            $pair('Precio unitario:', self::money($item['unit_price']));
            $pair('Importe:', self::money($item['amount']));
            $push('');
        }

        $sep();
        $push($this->pair('TOTAL:', self::money($ticket['total']).' '.$ticket['currency'], $cols), 'left', true);
        $pair('Forma de pago:', $ticket['payment_method']);
        $pair('Usuario:', $ticket['user']);

        if ($ticket['reprint']) {
            $sep();
            $left('REIMPRESIÓN No. '.$ticket['reprint']['number'], true);
            $left('Recibo original: '.$ticket['folio']);
            $left('Motivo: '.$ticket['reprint']['reason']);
            $left('Reimpreso: '.$ticket['reprint']['date'].' '.$ticket['reprint']['time']);
            $left('Por: '.$ticket['reprint']['user']);
        }

        $sep();
        $center($ticket['footer']);

        // Ajusta renglones largos al ancho del papel.
        return array_values(collect($out)->flatMap(function (array $line) use ($cols) {
            if (mb_strlen($line['text']) <= $cols) {
                return [$line];
            }

            return collect(explode("\n", wordwrap($line['text'], $cols, "\n", true)))
                ->map(fn ($chunk) => [...$line, 'text' => $chunk])->all();
        })->all());
    }

    public static function money(string|float|int $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    private function pair(string $label, string $value, int $cols): string
    {
        $space = $cols - mb_strlen($label) - mb_strlen($value);

        return $space >= 1 ? $label.str_repeat(' ', $space).$value : $label.' '.$value;
    }
}
