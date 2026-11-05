<?php

namespace App\Services\Import;

use App\Enums\AuditAction;
use App\Enums\CommerceType;
use App\Models\BusinessType;
use App\Models\MarketRoute;
use App\Models\Merchant;
use App\Models\Tianguis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MerchantService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Importa el Excel del padrón (una hoja por tianguis, ruta o plaza).
 *
 * - Une a la misma persona que aparece en varias hojas en un solo comerciante
 *   (comparando el nombre normalizado) y le asigna todos sus tianguis.
 * - Agrupa los giros en el catálogo y conserva el texto original.
 * - Es reejecutable: el UUID de cada comerciante se deriva de su nombre.
 * - En modo ensayo todo ocurre dentro de una transacción que se revierte.
 */
class PadronExcelImporter
{
    /** Espacio de nombres para los UUID deterministas de esta importación. */
    private const string UUID_NAMESPACE = '6f1a8a52-2c3e-4f8e-9d55-7a1c0b2e4d10';

    /** @var array<string, array<string, mixed>> */
    private array $people = [];

    /** @var list<array{sheet: string, row: int, name: string, issue: string}> */
    private array $issues = [];

    /** @var array<string, int> */
    private array $sheetCounts = [];

    public function __construct(
        private readonly MerchantService $merchants,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{summary: array<string, int|string>, merchants: list<array<string, mixed>>, issues: list<array<string, mixed>>}
     */
    public function import(string $path, User $user, bool $commit): array
    {
        $this->people = [];
        $this->issues = [];
        $this->sheetCounts = [];

        $this->read($path);

        DB::beginTransaction();

        try {
            [$tianguisIds, $routeIds, $typeIds] = $this->ensureCatalogs();
            $rows = [];
            $created = 0;

            foreach ($this->people as $person) {
                $merchant = $this->persist($person, $tianguisIds, $routeIds, $typeIds, $user);
                $created += (int) $merchant->wasRecentlyCreated;

                $rows[] = [
                    'folio' => $merchant->folio,
                    'estado' => $merchant->wasRecentlyCreated ? 'NUEVO' : 'YA EXISTÍA',
                    'nombre' => $person['name'],
                    'giro' => $person['business_type'] ?? BusinessTypeClassifier::FALLBACK,
                    'giro_original' => implode(' / ', $person['raw_giros']),
                    'tipo' => CommerceType::from($person['commerce_type'])->label(),
                    'tianguis' => implode(', ', $person['tianguis']),
                    'ruta' => $person['route'] ?? '',
                    'metros' => $person['meters'],
                    'hojas' => implode(', ', $person['sheets']),
                    'ubicacion_original' => implode(' | ', array_unique($person['locations'])),
                ];
            }

            $summary = [
                'Archivo' => basename($path),
                'Modo' => $commit ? 'IMPORTACIÓN' : 'ENSAYO (sin guardar)',
                'Registros leídos' => array_sum($this->sheetCounts),
                'Comerciantes (personas únicas)' => count($this->people),
                'Comerciantes nuevos' => $created,
                'Ya existían' => count($this->people) - $created,
                'Unidos por aparecer más de una vez' => count(array_filter($this->people, fn ($p) => $p['occurrences'] > 1)),
                'En más de un tianguis' => count(array_filter($this->people, fn ($p) => count($p['tianguis']) > 1)),
                'Observaciones para revisar' => count($this->issues),
            ];
            foreach ($this->sheetCounts as $sheet => $count) {
                $summary["Hoja: {$sheet}"] = $count;
            }

            if ($commit) {
                $this->audit->log(AuditAction::Create, 'Importación', null, null, [
                    'archivo' => basename($path),
                    'comerciantes_nuevos' => $created,
                ], "Importación del padrón desde Excel: {$created} comerciantes", $user);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['summary' => $summary, 'merchants' => $rows, 'issues' => $this->issues];
    }

    // ------------------------------------------------------------------ lectura

    private function read(string $path): void
    {
        if (! is_file($path)) {
            throw new RuntimeException("No existe el archivo: {$path}");
        }

        $reader = new Reader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = trim($sheet->getName());
            $target = PadronSheetMap::sheet($sheetName);

            if ($target === null) {
                $this->issue($sheetName, 0, '', 'Hoja no reconocida: no se importó');

                continue;
            }

            $columns = null;
            $rowNumber = 0;
            $this->sheetCounts[$sheetName] = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $cells = array_values(array_map(fn (Cell $c) => self::cellText($c), $row->getCells()));

                if ($columns === null) {
                    $columns = $this->headerColumns($cells);

                    continue;
                }

                $name = PadronText::cleanName($cells[$columns['name']] ?? '');
                if ($name === '' || ! is_numeric($cells[0] ?? '')) {
                    if (array_filter($cells, fn ($c) => $c !== '')) {
                        $this->issue($sheetName, $rowNumber, $name, 'Renglón sin número o sin nombre: no se importó');
                    }

                    continue;
                }

                $this->sheetCounts[$sheetName]++;
                $this->addOccurrence($sheetName, $rowNumber, $target, [
                    'name' => $name,
                    'giro' => trim($cells[$columns['giro']] ?? ''),
                    'location' => trim($cells[$columns['location']] ?? ''),
                    'meters' => $columns['meters'] !== null ? trim($cells[$columns['meters']] ?? '') : null,
                ]);
            }

            if ($columns === null) {
                $this->issue($sheetName, 0, '', 'No se encontró el encabezado "NOMBRE DE COMERCIANTE"');
            }
        }

        $reader->close();
    }

    /**
     * Localiza las columnas a partir del renglón de encabezado (N° | NOMBRE | GIRO | UBICACIÓN | ESPACIO).
     *
     * @param  array<int, string>  $cells
     * @return array{name: int, giro: int, location: int, meters: int|null}|null
     */
    private function headerColumns(array $cells): ?array
    {
        $find = function (string $needle) use ($cells): ?int {
            foreach ($cells as $i => $cell) {
                if (str_contains(PadronText::normalize($cell), $needle)) {
                    return $i;
                }
            }

            return null;
        };

        $name = $find('NOMBRE');
        if ($name === null) {
            return null;
        }

        return [
            'name' => $name,
            'giro' => $find('GIRO') ?? $name + 1,
            'location' => $find('UBICACION') ?? $name + 2,
            'meters' => $find('ESPACIO') ?? $find('MTS'),
        ];
    }

    /**
     * @param  array{type: string, name: string, route?: string}  $target
     * @param  array{name: string, giro: string, location: string, meters: string|null}  $data
     */
    private function addOccurrence(string $sheet, int $row, array $target, array $data): void
    {
        $key = PadronText::normalize($data['name']);
        $person = $this->people[$key] ?? [
            'key' => $key,
            'name' => $data['name'],
            'commerce_type' => match ($target['type']) {
                'route' => CommerceType::Route->value,
                'plaza' => CommerceType::Plaza->value,
                default => CommerceType::Tianguis->value,
            },
            'route' => null,
            'tianguis' => [],
            'raw_giros' => [],
            'business_type' => null,
            'meters' => null,
            'sheets' => [],
            'locations' => [],
            'occurrences' => 0,
            'first_sheet' => $sheet,
            'first_row' => $row,
        ];

        $person['occurrences']++;
        $person['sheets'][] = "{$sheet} #{$row}";

        if ($person['occurrences'] > 1) {
            $this->issue($sheet, $row, $data['name'], 'Aparece más de una vez; se unió en un solo comerciante ('.implode(', ', $person['sheets']).')');
        }

        // Lugar de la propia hoja
        if ($target['type'] === 'route') {
            $person['route'] ??= $target['name'];
        } else {
            $person['tianguis'][] = $target['name'];
            if (isset($target['route'])) {
                $person['route'] ??= $target['route'];
            }
        }

        // Otros lugares indicados en UBICACIÓN
        if ($data['location'] !== '') {
            $person['locations'][] = $data['location'];
            $person['tianguis'] = [...$person['tianguis'], ...$this->resolvePlaces($data['location'], $target, $sheet, $row, $data['name'])];
        }
        $person['tianguis'] = array_values(array_unique($person['tianguis']));

        // Giro
        if ($data['giro'] !== '' && ! in_array(PadronText::cleanName($data['giro']), $person['raw_giros'], true)) {
            $person['raw_giros'][] = PadronText::cleanName($data['giro']);
        }
        if ($person['business_type'] === null && $data['giro'] !== '') {
            $person['business_type'] = BusinessTypeClassifier::classify($data['giro']);
        }

        // Metros: se conserva la mayor medida registrada
        if ($data['meters'] !== null) {
            $parsed = PadronText::meters($data['meters']);
            if ($parsed['note'] !== null) {
                $this->issue($sheet, $row, $data['name'], $parsed['note']);
            }
            if ($parsed['meters'] === null && $parsed['note'] === null) {
                $this->issue($sheet, $row, $data['name'], 'Sin metros: se registró 0');
            }
            if ($parsed['meters'] !== null) {
                $person['meters'] = max($person['meters'] ?? 0, $parsed['meters']);
            }
        }

        $this->people[$key] = $person;
    }

    /**
     * @param  array{type: string, name: string, route?: string}  $target
     * @return list<string>
     */
    private function resolvePlaces(string $raw, array $target, string $sheet, int $row, string $name): array
    {
        $found = [];
        $aliases = PadronSheetMap::PLACE_ALIASES;
        uksort($aliases, fn ($a, $b) => strlen($b) <=> strlen($a)); // el alias más largo primero
        $isVialidadSheet = str_starts_with($target['name'], 'Vialidad');

        foreach (PadronText::places($raw) as $token) {
            if (in_array($token, PadronSheetMap::ROUTE_TOKENS, true)) {
                continue;
            }

            $rest = " {$token} ";
            foreach ($aliases as $alias => $place) {
                if (str_contains($rest, " {$alias} ")) {
                    $found[] = $place;
                    $rest = str_replace(" {$alias} ", ' ', $rest);
                }
            }

            // "VIALIDAD" sin sección: en una hoja de Vialidad es esa misma sección.
            if (preg_match('/\bVIALIDAD\b/', $rest)) {
                if ($isVialidadSheet) {
                    $found[] = $target['name'];
                } else {
                    $this->issue($sheet, $row, $name, 'Ubicación "VIALIDAD" sin sección: no se pudo asignar a una de las 5 Vialidades');
                }
                $rest = (string) preg_replace('/\bVIALIDAD\b/', ' ', $rest);
            }

            $rest = trim((string) preg_replace('/\s+/', ' ', $rest));
            if ($rest !== '' && ! in_array($rest, PadronSheetMap::ROUTE_TOKENS, true)) {
                $this->issue($sheet, $row, $name, "Lugar no reconocido en UBICACIÓN: \"{$rest}\" (no está en el catálogo)");
            }
        }

        return $found;
    }

    // ------------------------------------------------------------ catálogos y altas

    /**
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array<string, int>}
     */
    private function ensureCatalogs(): array
    {
        $tianguis = [];
        $routes = [];

        foreach (PadronSheetMap::SHEETS as $target) {
            if ($target['type'] === 'route') {
                $routes[$target['name']] ??= MarketRoute::query()->firstOrCreate(['name' => $target['name']], ['is_active' => true])->id;
            } else {
                $tianguis[$target['name']] ??= Tianguis::query()->firstOrCreate(['name' => $target['name']], [
                    'is_active' => true,
                    'description' => $target['type'] === 'plaza' ? 'Plaza' : null,
                ])->id;
            }
            if (isset($target['route'])) {
                $routes[$target['route']] ??= MarketRoute::query()->firstOrCreate(['name' => $target['route']], ['is_active' => true])->id;
            }
        }

        $types = [];
        foreach (BusinessTypeClassifier::catalog() as $name) {
            $types[$name] = BusinessType::query()->firstOrCreate(['name' => $name], ['is_active' => true])->id;
        }

        return [$tianguis, $routes, $types];
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, int>  $tianguisIds
     * @param  array<string, int>  $routeIds
     * @param  array<string, int>  $typeIds
     */
    private function persist(array $person, array $tianguisIds, array $routeIds, array $typeIds, User $user): Merchant
    {
        $businessType = $person['business_type'] ?? BusinessTypeClassifier::FALLBACK;
        if ($person['business_type'] === null) {
            $this->issue($person['first_sheet'], $person['first_row'], $person['name'], 'Sin giro: se registró como "Otros"');
        }

        return $this->merchants->create([
            'uuid' => Uuid::uuid5(self::UUID_NAMESPACE, 'padron-excel:'.$person['key'])->toString(),
            'taxpayer_name' => $person['name'],
            'business_type_id' => $typeIds[$businessType],
            'business_description' => mb_substr(implode(' / ', $person['raw_giros']), 0, 1000) ?: null,
            'commerce_type' => $person['commerce_type'],
            'tianguis_ids' => array_map(fn ($t) => $tianguisIds[$t], $person['tianguis']),
            'route_id' => $person['route'] ? $routeIds[$person['route']] : null,
            'location' => mb_substr(implode(' | ', array_unique($person['locations'])), 0, 255) ?: null,
            'meters' => $person['meters'] ?? 0,
            'notes' => 'Importado del padrón 2026 (Excel): '.implode(', ', $person['sheets']),
        ], $user);
    }

    /** Texto de una celda (fechas y números incluidos). */
    private static function cellText(Cell $cell): string
    {
        $value = $cell->getValue();

        return trim(match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => '',
        });
    }

    private function issue(string $sheet, int $row, string $name, string $issue): void
    {
        $this->issues[] = ['sheet' => $sheet, 'row' => $row, 'name' => $name, 'issue' => $issue];
    }
}
