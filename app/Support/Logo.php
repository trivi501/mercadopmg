<?php

namespace App\Support;

/**
 * Logotipos del Ayuntamiento como data URI, para PDFs (dompdf) y tickets
 * que no deben depender de rutas públicas.
 */
class Logo
{
    public const string FULL = 'images/logo-guadalupe.png';

    public const string SHIELD = 'images/escudo-guadalupe.png';

    /** Escudo en negro para impresoras térmicas. */
    public const string SHIELD_BLACK = 'images/escudo-guadalupe-negro.png';

    /** @var array<string, string> */
    private static array $cache = [];

    public static function dataUri(string $image = self::FULL): string
    {
        return self::$cache[$image] ??= 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path($image)));
    }
}
