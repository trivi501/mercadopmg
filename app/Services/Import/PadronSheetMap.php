<?php

namespace App\Services\Import;

/**
 * Correspondencia entre las hojas del Excel "PADRÓN COMERCIO DE LA VÍA PÚBLICA 2026"
 * y los catálogos del sistema, más los alias con que aparecen en la columna UBICACIÓN.
 */
final class PadronSheetMap
{
    /**
     * Hoja (normalizada) => destino.
     * type: tianguis | route | plaza. Para plaza se indica la ruta a la que pertenece.
     *
     * @var array<string, array{type: string, name: string, route?: string}>
     */
    public const array SHEETS = [
        'T. ZACATECANA' => ['type' => 'tianguis', 'name' => 'La Zacatecana'],
        'AMERICAS' => ['type' => 'tianguis', 'name' => 'Las Américas'],
        'VIALIDAD 1RA' => ['type' => 'tianguis', 'name' => 'Vialidad 1ra Sección'],
        'VIALIDAD 2DA' => ['type' => 'tianguis', 'name' => 'Vialidad 2da Sección'],
        'VIALIDAD 3RA' => ['type' => 'tianguis', 'name' => 'Vialidad 3ra Sección'],
        'VIALIDAD 4TA' => ['type' => 'tianguis', 'name' => 'Vialidad 4ta Sección'],
        'VIALIDAD EXT' => ['type' => 'tianguis', 'name' => 'Vialidad Extensiones'],
        'QUINTAS' => ['type' => 'tianguis', 'name' => 'Las Quintas'],
        'VILLAS DE GUADALUPE' => ['type' => 'tianguis', 'name' => 'Villas de Guadalupe'],
        'GAVILANES' => ['type' => 'tianguis', 'name' => 'Gavilanes'],
        'SAN GABRIEL' => ['type' => 'tianguis', 'name' => 'San Gabriel'],
        'ESTIBADORES' => ['type' => 'tianguis', 'name' => 'Estibadores'],
        'JARDIN JUAREZ' => ['type' => 'plaza', 'name' => 'Jardín Juárez', 'route' => 'Ruta 3'],
        'RUTA 1' => ['type' => 'route', 'name' => 'Ruta 1'],
        'RUTA 2' => ['type' => 'route', 'name' => 'Ruta 2'],
        'RUTA 3 VESPERTINA' => ['type' => 'route', 'name' => 'Ruta 3'],
    ];

    /**
     * Alias en la columna UBICACIÓN => tianguis/plaza del catálogo.
     * Incluye errores de captura frecuentes. "VIALIDAD" sin sección se resuelve aparte.
     *
     * @var array<string, string>
     */
    public const array PLACE_ALIASES = [
        'LA ZACATECANA' => 'La Zacatecana', 'ZACATECANA' => 'La Zacatecana', 'LA ZACATEACANA' => 'La Zacatecana', 'ZACATEACANA' => 'La Zacatecana',
        'AMERICAS' => 'Las Américas', 'AMERICA' => 'Las Américas', 'AMER' => 'Las Américas',
        'VIALIDAD 1RA' => 'Vialidad 1ra Sección', 'VIALIDAD 2DA SEC' => 'Vialidad 2da Sección', 'VIALIDAD 2DA' => 'Vialidad 2da Sección',
        'VIALIDAD 3RA' => 'Vialidad 3ra Sección', 'VIALIDAD 4TA' => 'Vialidad 4ta Sección', 'VIALIDAD EXT' => 'Vialidad Extensiones',
        'QUINTAS' => 'Las Quintas', 'QUINTOS' => 'Las Quintas', 'QUINAS' => 'Las Quintas', 'QUINTAN' => 'Las Quintas',
        'VILLAS DE GUADALUPE' => 'Villas de Guadalupe', 'VILLAS' => 'Villas de Guadalupe',
        'GAVILANES' => 'Gavilanes', 'GAVILAN' => 'Gavilanes',
        'SAN GABRIEL' => 'San Gabriel', 'SN GABRIEL' => 'San Gabriel', 'SN. GABRIEL' => 'San Gabriel',
        'ESTIBADORES' => 'Estibadores', 'ESTIBADORE' => 'Estibadores', 'ESRTIBADORES' => 'Estibadores',
        'JARDIN JUAREZ R3' => 'Jardín Juárez', 'JARDIN JUAREZ' => 'Jardín Juárez',
    ];

    /** Palabras de la columna UBICACIÓN que se refieren a la ruta de la hoja y no a un tianguis. */
    public const array ROUTE_TOKENS = ['RUTA 1', 'RUTA 2', 'RUTA 3', 'R3'];

    /**
     * @return array{type: string, name: string, route?: string}|null
     */
    public static function sheet(string $sheetName): ?array
    {
        return self::SHEETS[PadronText::normalize($sheetName)] ?? null;
    }
}
