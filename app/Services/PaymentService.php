<?php

namespace App\Services;

use App\Enums\AnnualityStatus;
use App\Enums\AuditAction;
use App\Enums\ConceptType;
use App\Enums\MerchantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Annuality;
use App\Models\Concept;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registro de cobros: calcula importes, aplica reglas de negocio, genera el
 * folio consecutivo y emite el recibo en una sola transacción.
 */
class PaymentService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Importe de un renglón: cantidad × precio unitario (aritmética decimal exacta).
     *
     * @param  numeric-string  $unitPrice
     * @return numeric-string
     */
    public static function lineTotal(int $quantity, string $unitPrice): string
    {
        return bcmul((string) $quantity, $unitPrice, 2);
    }

    /**
     * Registra un cobro y devuelve el recibo emitido.
     *
     * Es idempotente por `uuid`: si la operación ya fue registrada (por ejemplo,
     * un reintento de sincronización desde Android) se devuelve el recibo
     * existente sin volver a cobrar. Consulte `$receipt->wasRecentlyCreated`.
     *
     * Recibe datos ya validados (ChargeRequest::chargeRules()): uuid?, merchant_id,
     * payment_method, reference?, notes?, issued_at?, source?, device? e items[]
     * con concept_id, quantity?, fiscal_year?, service_date? y tianguis_id?.
     *
     * @param  array<string, mixed>  $data
     */
    public function charge(array $data, User $user): Receipt
    {
        $uuid = is_string($data['uuid'] ?? null) ? $data['uuid'] : (string) Str::uuid();

        if ($existing = Receipt::query()->where('uuid', $uuid)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->createReceipt($uuid, $data, $user));
        } catch (UniqueConstraintViolationException $e) {
            // Dos peticiones simultáneas con el mismo UUID: gana la primera.
            if ($existing = Receipt::query()->where('uuid', $uuid)->first()) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createReceipt(string $uuid, array $data, User $user): Receipt
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::query()->lockForUpdate()->findOrFail((int) $data['merchant_id']);

        if ($merchant->status !== MerchantStatus::Active) {
            throw new BusinessRuleException("El comerciante {$merchant->folio} no está activo; no se puede registrar el cobro.", 'merchant_inactive', 'merchant_id');
        }

        $method = PaymentMethod::from((string) $data['payment_method']);
        $issuedAt = isset($data['issued_at']) ? Carbon::parse((string) $data['issued_at']) : now();
        /** @var list<array<string, mixed>> $items */
        $items = array_values((array) ($data['items'] ?? []));
        $lines = $this->buildLines($merchant, $items, $issuedAt);

        $total = array_reduce($lines, fn (string $sum, array $l): string => bcadd($sum, $l['total'], 2), '0.00');

        $receipt = Receipt::create([
            'uuid' => $uuid,
            'folio' => $this->folios->next(FolioGenerator::RECEIPT),
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            'tianguis_id' => $lines[0]['tianguis_id'] ?? $merchant->tianguis_id,
            'route_id' => $merchant->route_id,
            'issued_at' => $issuedAt,
            'subtotal' => $total,
            'discount' => '0.00',
            'surcharge' => '0.00',
            'total' => $total,
            'payment_method' => $method,
            'status' => ReceiptStatus::Issued,
            'reprint_count' => 0,
            'source' => $data['source'] ?? 'web',
            'device' => isset($data['device']) ? Str::limit((string) $data['device'], 250, '') : null,
            'notes' => $data['notes'] ?? null,
        ]);

        $receipt->paymentDetails()->create([
            'payment_method' => $method,
            'amount' => $total,
            'reference' => $data['reference'] ?? null,
        ]);

        foreach ($lines as $line) {
            /** @var Concept $concept */
            $concept = $line['concept'];

            $payment = Payment::create([
                'receipt_id' => $receipt->id,
                'merchant_id' => $merchant->id,
                'concept_id' => $concept->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'subtotal' => $line['total'],
                'discount' => '0.00',
                'surcharge' => '0.00',
                'total' => $line['total'],
                'payment_method' => $method,
                'paid_at' => $issuedAt,
                'user_id' => $user->id,
                'tianguis_id' => $line['tianguis_id'],
                'service_date' => $line['service_date'],
                'fiscal_year' => $line['fiscal_year'],
                'status' => PaymentStatus::Applied,
            ]);

            $receipt->items()->create([
                'payment_id' => $payment->id,
                'concept_id' => $concept->id,
                'concept_code' => $concept->code,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['total'],
            ]);

            if ($line['annuality']) {
                $line['annuality']->update([
                    'status' => AnnualityStatus::Paid,
                    'payment_id' => $payment->id,
                    'paid_at' => $issuedAt,
                ]);
            }
        }

        $this->audit->log(AuditAction::Charge, 'Cobranza', $receipt, null, [
            'folio' => $receipt->folio,
            'merchant' => $merchant->folio,
            'total' => $receipt->total,
            'items' => array_map(fn ($l) => ['concepto' => $l['concept']->code, 'cantidad' => $l['quantity'], 'importe' => $l['total']], $lines),
        ], "Cobro {$receipt->folio} por \${$receipt->total}", $user);

        return $receipt;
    }

    /**
     * Valida cada concepto y calcula importes.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{concept: Concept, quantity: int, unit_price: numeric-string, total: numeric-string, description: string, fiscal_year: int|null, service_date: mixed, tianguis_id: mixed, annuality: Annuality|null}>
     */
    private function buildLines(Merchant $merchant, array $items, CarbonInterface $issuedAt): array
    {
        if ($items === []) {
            throw new BusinessRuleException('Debe incluir al menos un concepto.', 'no_items', 'items');
        }

        $lines = [];
        $seenAnnualities = [];

        foreach ($items as $i => $item) {
            $concept = Concept::query()->find((int) $item['concept_id']);

            if (! $concept || ! $concept->is_active) {
                throw new BusinessRuleException('El concepto seleccionado no está disponible.', 'concept_unavailable', "items.$i.concept_id");
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $fiscalYear = null;
            $annuality = null;
            $description = $concept->name;

            if ($concept->type === ConceptType::Annuality) {
                $quantity = 1;
                $fiscalYear = (int) ($item['fiscal_year'] ?? $issuedAt->year);
                $key = $concept->id.'-'.$fiscalYear;

                if (isset($seenAnnualities[$key])) {
                    throw new BusinessRuleException("La anualidad {$fiscalYear} está repetida en el cobro.", 'annuality_duplicated', "items.$i.fiscal_year");
                }
                $seenAnnualities[$key] = true;

                $annuality = $this->lockAnnuality($merchant, $concept, $fiscalYear);
                $unitPrice = $annuality->amount;
                $description = "{$concept->name} {$fiscalYear}";
            } else {
                if ($quantity < 1) {
                    throw new BusinessRuleException('La cantidad debe ser mayor a cero.', 'invalid_quantity', "items.$i.quantity");
                }
                $unitPrice = $concept->amount;
            }

            $lines[] = [
                'concept' => $concept,
                'quantity' => $quantity,
                'unit_price' => bcadd($unitPrice, '0', 2),
                'total' => self::lineTotal($quantity, $unitPrice),
                'description' => $description,
                'fiscal_year' => $fiscalYear,
                'service_date' => $concept->type === ConceptType::Ticket ? ($item['service_date'] ?? $issuedAt->toDateString()) : null,
                'tianguis_id' => $concept->type === ConceptType::Ticket ? ($item['tianguis_id'] ?? $merchant->tianguis_id) : $merchant->tianguis_id,
                'annuality' => $annuality,
            ];
        }

        return $lines;
    }

    /**
     * Obtiene y bloquea la anualidad; impide cobrar dos veces el mismo ejercicio.
     */
    private function lockAnnuality(Merchant $merchant, Concept $concept, int $fiscalYear): Annuality
    {
        Annuality::query()->firstOrCreate(
            ['merchant_id' => $merchant->id, 'concept_id' => $concept->id, 'fiscal_year' => $fiscalYear],
            ['amount' => $concept->amount, 'status' => AnnualityStatus::Pending],
        );

        /** @var Annuality $annuality */
        $annuality = Annuality::query()
            ->where(['merchant_id' => $merchant->id, 'concept_id' => $concept->id, 'fiscal_year' => $fiscalYear])
            ->lockForUpdate()
            ->firstOrFail();

        if ($annuality->status === AnnualityStatus::Paid) {
            throw new BusinessRuleException(
                "La anualidad {$fiscalYear} del comerciante {$merchant->folio} ya fue pagada.",
                'annuality_already_paid',
                'items',
            );
        }

        return $annuality;
    }
}
