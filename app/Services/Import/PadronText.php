<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Normalización e interpretación de los textos capturados en el Excel.
 */
final class PadronText
{
    /** Mayúsculas, sin acentos y con espacios simples (para comparar). */
    public static function normalize(?string $value): string
    {
        $value = Str::upper(Str::ascii(trim((string) $value)));

        return trim((string) preg_replace('/\s+/', ' ', $value), ' .');
    }

    /** Nombre para guardar: conserva acentos, con espacios simples y en mayúsculas. */
    public static function cleanName(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $value))), ' .');
    }

    /**
     * Interpreta la columna ESPACIO (MTS): "3 MTS.", "7 1/2 MTS.", "2.5 X2.5 MTS", "1 MTO", "MOVIL".
     *
     * @return array{meters: float|null, note: string|null} meters null = no se pudo interpretar
     */
    public static function meters(?string $raw): array
    {
        $text = self::normalize($raw);

        if ($text === '') {
            return ['meters' => null, 'note' => null];
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)(?:\s+(\d+)\s*\/\s*(\d+))?/', $text, $m)) {
            return ['meters' => null, 'note' => "Metros no interpretables: \"{$raw}\""];
        }

        $meters = (float) str_replace(',', '.', $m[1]);
        if (isset($m[2], $m[3]) && (int) $m[3] > 0) {
            $meters += (int) $m[2] / (int) $m[3];
        }

        $note = preg_match('/\d\s*X\s*\d/', $text) ? "Medida en área \"{$raw}\": se registró el frente ({$meters} m)" : null;

        return ['meters' => round($meters, 2), 'note' => $note];
    }

    /**
     * Divide la columna UBICACIÓN en lugares: separa por "/", "," y " Y ".
     *
     * @return list<string>
     */
    public static function places(?string $raw): array
    {
        $parts = preg_split('/\s*(?:\/|,|\bY\b)\s*/', self::normalize($raw)) ?: [];

        return array_values(array_filter(array_map(fn ($p) => trim($p, ' .'), $parts)));
    }
}
