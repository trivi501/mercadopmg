<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Receipt;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cortes de caja: inicio de cobro (fondo inicial), totales del turno y corte
 * final con arqueo. Cada recaudador tiene a lo más un corte abierto; todos los
 * cobros que registra quedan ligados a él.
 */
class CashRegisterService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly AuditLogger $audit,
    ) {}

    public function current(User $user): ?CashRegisterSession
    {
        return CashRegisterSession::query()->open()->where('user_id', $user->id)->latest('id')->first();
    }

    /**
     * Abre el corte del usuario. Si llega un UUID ya registrado (reintento
     * desde la app) devuelve el mismo corte.
     *
     * @param  array{opening_amount?: numeric-string|float|int|null, opening_notes?: string|null, uuid?: string|null, source?: string, device?: string|null}  $data
     */
    public function open(User $user, array $data): CashRegisterSession
    {
        if (! empty($data['uuid']) && $existing = CashRegisterSession::query()->where('uuid', $data['uuid'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $data) {
            // Serializa aperturas simultáneas del mismo usuario.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($open = $this->current($user)) {
                throw new BusinessRuleException("Ya tiene abierto el corte {$open->folio}. Ciérrelo antes de iniciar otro.", 'cash_session_open');
            }

            $session = CashRegisterSession::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'folio' => $this->folios->next(FolioGenerator::CASH_SESSION),
                'user_id' => $user->id,
                'status' => CashSessionStatus::Open,
                'opened_at' => now(),
                'opening_amount' => self::amount($data['opening_amount'] ?? 0),
                'opening_notes' => $data['opening_notes'] ?? null,
                'source' => $data['source'] ?? 'web',
                'device' => isset($data['device']) ? Str::limit((string) $data['device'], 250, '') : null,
            ]);

            $this->audit->log(AuditAction::CashOpen, 'Cortes de caja', $session, null, [
                'folio' => $session->folio,
                'fondo_inicial' => $session->opening_amount,
            ], "Inicio de cobro {$session->folio} con fondo de \${$session->opening_amount}", $user);

            return $session;
        });
    }

    /**
     * Cierra el corte con el efectivo contado (ya validado como número). Los
     * totales quedan congelados.
     */
    public function close(CashRegisterSession $session, User $closer, string|float|int $countedCash, ?string $notes = null): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $closer, $countedCash, $notes) {
            /** @var CashRegisterSession $session */
            $session = CashRegisterSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new BusinessRuleException("El corte {$session->folio} ya fue cerrado.", 'cash_session_closed');
            }

            $summary = $this->computeSummary($session);
            $counted = self::amount($countedCash);

            $session->update([
                'status' => CashSessionStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $closer->id,
                'total_collected' => $summary['total_collected'],
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => $counted,
                'difference' => bcsub($counted, $summary['expected_cash'], 2),
                'summary' => $summary,
                'closing_notes' => $notes,
            ]);

            $this->audit->log(AuditAction::CashClose, 'Cortes de caja', $session, null, [
                'folio' => $session->folio,
                'cobrado' => $session->total_collected,
                'esperado' => $session->expected_cash,
                'contado' => $session->counted_cash,
                'diferencia' => $session->difference,
            ], "Corte {$session->folio}: cobrado \${$session->total_collected}, diferencia \${$session->difference}", $closer);

            return $session;
        });
    }

    /**
     * Totales del corte: los congelados al cerrar (JSON guardado) o, si sigue
     * abierto, calculados al momento con la misma estructura que computeSummary().
     *
     * @return array<string, mixed>
     */
    public function summary(CashRegisterSession $session): array
    {
        if (! $session->isOpen() && is_array($session->summary)) {
            return $session->summary;
        }

        return $this->computeSummary($session);
    }

    /**
     * Totales calculados a partir de los recibos del corte (sin cancelados).
     *
     * @return array{receipts: int, cancelled: int, cancelled_amount: numeric-string, first_folio: ?string, last_folio: ?string, total_collected: numeric-string, opening_amount: numeric-string, cash_collected: numeric-string, expected_cash: numeric-string, by_method: list<array{method: string, label: string, count: int, amount: numeric-string}>, by_concept: list<array{code: string, name: string, quantity: int, amount: numeric-string}>}
     */
    private function computeSummary(CashRegisterSession $session): array
    {
        $receipts = Receipt::query()->where('cash_register_session_id', $session->id);
        $valid = (clone $receipts)->where('status', '!=', ReceiptStatus::Cancelled);
        $cancelled = (clone $receipts)->where('status', ReceiptStatus::Cancelled);

        $byMethod = PaymentDetail::query()
            ->whereIn('receipt_id', (clone $valid)->select('id'))
            ->selectRaw('payment_method, count(distinct receipt_id) as receipts, sum(amount) as amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn (PaymentDetail $row) => [
                'method' => $row->payment_method->value,
                'label' => $row->payment_method->label(),
                'count' => (int) $row->getAttribute('receipts'),
                'amount' => self::amount($row->getAttribute('amount')),
            ])
            ->sortBy(fn (array $m) => array_search($m['method'], PaymentMethod::values(), true))
            ->values()->all();

        $byConcept = Payment::query()
            ->join('concepts', 'concepts.id', '=', 'payments.concept_id')
            ->whereIn('payments.receipt_id', (clone $valid)->select('id'))
            ->where('payments.status', PaymentStatus::Applied)
            ->selectRaw('concepts.code, concepts.name, sum(payments.quantity) as quantity, sum(payments.total) as amount')
            ->groupBy('concepts.code', 'concepts.name')
            ->orderBy('concepts.code')
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->getAttribute('code'),
                'name' => (string) $row->getAttribute('name'),
                'quantity' => (int) $row->getAttribute('quantity'),
                'amount' => self::amount($row->getAttribute('amount')),
            ])->values()->all();

        $total = self::amount((clone $valid)->sum('total'));
        $cash = self::amount(collect($byMethod)->firstWhere('method', PaymentMethod::Cash->value)['amount'] ?? 0);
        $first = (clone $receipts)->orderBy('id')->value('folio');
        $last = (clone $receipts)->orderByDesc('id')->value('folio');

        return [
            'receipts' => (clone $valid)->count(),
            'cancelled' => (clone $cancelled)->count(),
            'cancelled_amount' => self::amount((clone $cancelled)->sum('total')),
            'first_folio' => is_string($first) ? $first : null,
            'last_folio' => is_string($last) ? $last : null,
            'total_collected' => $total,
            'opening_amount' => self::amount($session->opening_amount),
            'cash_collected' => $cash,
            'expected_cash' => bcadd(self::amount($session->opening_amount), $cash, 2),
            'by_method' => array_values($byMethod),
            'by_concept' => array_values($byConcept),
        ];
    }

    /**
     * Datos del reporte de corte (PDF, vista térmica y app).
     *
     * @return array<string, mixed>
     */
    public function present(CashRegisterSession $session): array
    {
        $session->loadMissing(['user', 'closer']);

        return [
            'municipality' => Str::upper((string) Setting::get('municipality_name', 'MUNICIPIO')),
            'title' => $session->isOpen() ? 'CORTE PARCIAL (ABIERTO)' : 'CORTE DE CAJA',
            'folio' => $session->folio,
            'user' => Str::upper($session->user->name),
            'opened_at' => $session->opened_at->format('d/m/Y H:i'),
            'closed_at' => $session->closed_at?->format('d/m/Y H:i'),
            'closed_by' => $session->closer ? Str::upper($session->closer->name) : null,
            'is_open' => $session->isOpen(),
            'counted_cash' => $session->counted_cash,
            'difference' => $session->difference,
            'opening_notes' => $session->opening_notes,
            'closing_notes' => $session->closing_notes,
            'printed_at' => now()->format('d/m/Y H:i'),
            'summary' => $this->summary($session),
        ];
    }

    /**
     * Reporte en renglones de texto para la impresora térmica (mismo formato que los tickets).
     *
     * @return list<array{text: string, align: string, bold: bool, size: string}>
     */
    public function lines(CashRegisterSession $session, int $paperWidth = 58): array
    {
        $r = $this->present($session);
        $s = $r['summary'];
        $cols = TicketPresenter::COLUMNS[$paperWidth] ?? 32;
        $out = [];
        $push = function (string $text, string $align = 'left', bool $bold = false, string $size = 'normal') use (&$out) {
            $out[] = ['text' => $text, 'align' => $align, 'bold' => $bold, 'size' => $size];
        };
        $sep = fn () => $push(str_repeat('-', $cols));
        $pair = fn (string $label, string $value, bool $bold = false) => $push(self::pair($label, $value, $cols), 'left', $bold);
        $money = fn (string|float|int|null $v) => TicketPresenter::money((string) ($v ?? 0));

        $push($r['municipality'], 'center', true);
        $push('PADRÓN DE COMERCIANTES', 'center', true);
        $push($r['title'], 'center', true, 'double');
        $sep();
        $pair('Corte:', $r['folio']);
        $pair('Recaudador:', $r['user']);
        $pair('Inicio:', $r['opened_at']);
        $pair('Cierre:', $r['closed_at'] ?? 'ABIERTO');
        $pair('Recibos:', (string) $s['receipts']);
        if ($s['first_folio']) {
            $pair('Del folio:', $s['first_folio']);
            $pair('Al folio:', (string) $s['last_folio']);
        }
        $sep();
        $push('COBRADO POR CONCEPTO', 'left', true);
        foreach ($s['by_concept'] as $c) {
            $push(Str::upper($c['name']));
            $pair('  Cantidad: '.$c['quantity'], $money($c['amount']));
        }
        if ($s['by_concept'] === []) {
            $push('  Sin cobros');
        }
        $sep();
        $push('POR FORMA DE PAGO', 'left', true);
        foreach ($s['by_method'] as $m) {
            $pair(Str::upper($m['label']).' ('.$m['count'].')', $money($m['amount']));
        }
        $sep();
        $pair('TOTAL COBRADO:', $money($s['total_collected']), true);
        if ($s['cancelled'] > 0) {
            $pair('Cancelados ('.$s['cancelled'].'):', $money($s['cancelled_amount']));
        }
        $sep();
        $push('ARQUEO DE EFECTIVO', 'left', true);
        $pair('Fondo inicial:', $money($s['opening_amount']));
        $pair('Cobros en efectivo:', $money($s['cash_collected']));
        $pair('Efectivo esperado:', $money($s['expected_cash']), true);
        if (! $r['is_open']) {
            $pair('Efectivo contado:', $money($r['counted_cash']), true);
            $diff = (float) $r['difference'];
            $pair($diff > 0 ? 'SOBRANTE:' : ($diff < 0 ? 'FALTANTE:' : 'DIFERENCIA:'), $money(abs($diff)), true);
        }
        if ($r['closing_notes']) {
            $push('Notas: '.$r['closing_notes']);
        }
        $sep();
        $push('');
        $push('');
        $push(str_repeat('_', min(28, $cols)), 'center');
        $push('FIRMA DEL RECAUDADOR', 'center');
        $push('');
        $push('');
        $push(str_repeat('_', min(28, $cols)), 'center');
        $push('RECIBE (TESORERÍA)', 'center');
        $push('');
        $push('Impreso: '.$r['printed_at'], 'center');

        return array_values(collect($out)->flatMap(function (array $line) use ($cols) {
            if (mb_strlen($line['text']) <= $cols) {
                return [$line];
            }

            return collect(explode("\n", wordwrap($line['text'], $cols, "\n", true)))
                ->map(fn ($chunk) => [...$line, 'text' => $chunk])->all();
        })->all());
    }

    /**
     * Reporte de corte en PDF tamaño carta, con el detalle de recibos.
     */
    public function pdf(CashRegisterSession $session): DomPdf
    {
        $receipts = $session->receipts()->with(['merchant:id,folio,taxpayer_name'])->orderBy('id')->get();

        return Pdf::loadView('pdf.cash-session', ['r' => $this->present($session), 'receipts' => $receipts])
            ->setPaper('letter');
    }

    /**
     * @return numeric-string
     */
    public static function amount(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private static function pair(string $label, string $value, int $cols): string
    {
        $space = $cols - mb_strlen($label) - mb_strlen($value);

        return $space >= 1 ? $label.str_repeat(' ', $space).$value : $label.' '.$value;
    }
}
