<?php

namespace App\Services;

use App\Models\FolioSequence;
use Illuminate\Support\Facades\DB;

/**
 * Genera folios consecutivos sin duplicados ni huecos reutilizados.
 *
 * El renglón de la secuencia se bloquea (SELECT ... FOR UPDATE) dentro de la
 * transacción del llamador, de modo que varios recaudadores cobrando al mismo
 * tiempo obtienen números distintos. Si la transacción se revierte, el número
 * se libera; una vez confirmada, el folio queda consumido aunque el recibo se
 * cancele después.
 */
class FolioGenerator
{
    public const string MERCHANT = 'merchant';

    public const string RECEIPT = 'receipt';

    /** @var array<string, array{prefix: string, padding: int}> */
    private const array DEFAULTS = [
        self::MERCHANT => ['prefix' => 'COM-', 'padding' => 6],
        self::RECEIPT => ['prefix' => 'REC-', 'padding' => 8],
    ];

    public function next(string $sequence): string
    {
        return DB::transaction(function () use ($sequence) {
            $row = FolioSequence::query()->where('name', $sequence)->lockForUpdate()->first();

            if (! $row) {
                FolioSequence::query()->insertOrIgnore([
                    'name' => $sequence,
                    ...self::DEFAULTS[$sequence],
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $row = FolioSequence::query()->where('name', $sequence)->lockForUpdate()->firstOrFail();
            }

            $row->last_number++;
            $row->save();

            return $row->prefix.str_pad((string) $row->last_number, $row->padding, '0', STR_PAD_LEFT);
        });
    }
}
