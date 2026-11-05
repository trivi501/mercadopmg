<?php

namespace App\Console\Commands;

use App\Enums\CommerceType;
use App\Models\BusinessType;
use App\Models\MarketRoute;
use App\Models\Merchant;
use App\Models\Tianguis;
use App\Models\User;
use App\Services\MerchantService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/**
 * Importa el padrón municipal en Excel: una hoja por tianguis, ruta o plaza,
 * con encabezado "N° | NOMBRE DE COMERCIANTE | GIRO | UBICACIÓN | ESPACIO (MTS)".
 * El título de la hoja (p. ej. "TIANGUIS LAS AMERICAS", "RUTA 1",
 * "JARDIN JUAREZ (RUTA 3)") determina el tipo de comercio.
 */
#[Signature('padron:importar {archivo : Ruta del archivo .xlsx} {--usuario=admin : Usuario que figura como capturista} {--dry-run : Solo analiza el archivo, no guarda nada}')]
#[Description('Importa comerciantes desde el Excel del padrón (tianguis, rutas y plazas)')]
class ImportPadron extends Command
{
    /** @var array<string, int> */
    private array $businessTypes = [];

    public function handle(MerchantService $merchants): int
    {
        $file = (string) $this->argument('archivo');

        if (! is_file($file)) {
            $this->error("No existe el archivo: {$file}");

            return self::FAILURE;
        }

        $user = User::query()->where('username', $this->option('usuario'))->first();

        if (! $user) {
            $this->error("No existe el usuario: {$this->option('usuario')}");

            return self::FAILURE;
        }

        Auth::setUser($user);
        $sheets = $this->read($file);
        $dryRun = (bool) $this->option('dry-run');

        $this->table(['Hoja', 'Tipo', 'Destino', 'Renglones'], array_map(fn (array $s) => [
            $s['sheet'], $s['type']->label(), $s['place'].($s['route'] && $s['type'] !== CommerceType::Route ? " · {$s['route']}" : ''), count($s['rows']),
        ], $sheets));

        if ($dryRun) {
            $this->info('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        $created = $skipped = 0;

        DB::transaction(function () use ($sheets, $merchants, $user, &$created, &$skipped) {
            foreach ($sheets as $sheet) {
                $tianguisId = $sheet['type'] === CommerceType::Tianguis
                    ? Tianguis::query()->firstOrCreate(['name' => $sheet['place']], ['is_active' => true])->id
                    : null;
                $routeId = $sheet['route'] !== null
                    ? MarketRoute::query()->firstOrCreate(['name' => $sheet['route']], ['is_active' => true])->id
                    : null;

                foreach ($sheet['rows'] as $row) {
                    $exists = Merchant::query()
                        ->where('taxpayer_name', $row['name'])
                        ->where('commerce_type', $sheet['type'])
                        ->where('tianguis_id', $tianguisId)
                        ->where('route_id', $routeId)
                        ->where('business_description', $row['giro'])
                        ->where('meters', $row['meters'] ?? 0)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $merchants->create([
                        'taxpayer_name' => $row['name'],
                        'business_type_id' => $this->businessType($row['giro']),
                        'business_description' => $row['giro'],
                        'location' => $row['location'],
                        'location_reference' => $sheet['type'] === CommerceType::Plaza ? $sheet['place'] : null,
                        'commerce_type' => $sheet['type'],
                        'tianguis_id' => $tianguisId,
                        'route_id' => $routeId,
                        'meters' => $row['meters'] ?? 0,
                        'notes' => trim("Importado del padrón 2026, hoja \"{$sheet['sheet']}\", N° {$row['number']}. {$row['meters_note']}"),
                    ], $user);
                    $created++;
                }
            }
        });

        $this->info("Comerciantes importados: {$created}. Omitidos por ya existir: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{sheet: string, type: CommerceType, place: string, route: ?string, rows: list<array{number: string, name: string, giro: string, location: ?string, meters: ?float, meters_note: string}>}>
     */
    private function read(string $file): array
    {
        $reader = new Reader;
        $reader->open($file);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $title = null;
            $columns = null;
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($v) => self::clean($v), $row->toArray());

                if ($columns === null) {
                    if (in_array('NOMBRE DE COMERCIANTE', $cells, true)) {
                        $columns = self::columns($cells);
                    } elseif ($title === null && ($text = current(array_filter($cells))) && ! preg_match('/^(PADR[ÓO]N|UNIDAD)\b/u', $text)) {
                        $title = $text;
                    }

                    continue;
                }

                $name = $cells[$columns['name']] ?? '';

                if ($name === '') {
                    continue;
                }

                [$meters, $note] = self::meters($cells[$columns['space'] ?? -1] ?? '');

                $rows[] = [
                    'number' => $cells[0] ?? '',
                    'name' => mb_substr($name, 0, 200),
                    'giro' => mb_substr(rtrim($cells[$columns['giro']] ?? '', ' ,.') ?: 'SIN GIRO', 0, 100),
                    'location' => mb_substr($cells[$columns['location'] ?? -1] ?? '', 0, 255) ?: null,
                    'meters' => $meters,
                    'meters_note' => $note,
                ];
            }

            if ($columns === null || $title === null) {
                $this->warn("Hoja \"{$sheet->getName()}\" omitida: no se encontró título o encabezado.");

                continue;
            }

            $sheets[] = ['sheet' => trim($sheet->getName()), ...self::destination($title), 'rows' => $rows];
        }

        $reader->close();

        return $sheets;
    }

    /**
     * @return array{type: CommerceType, place: string, route: ?string}
     */
    private static function destination(string $title): array
    {
        preg_match('/\(?\bRUTA\s*(\d+)\)?/u', $title, $route);
        $routeName = isset($route[1]) ? 'Ruta '.$route[1] : null;

        if (str_starts_with($title, 'RUTA')) {
            return ['type' => CommerceType::Route, 'place' => (string) $routeName, 'route' => $routeName];
        }

        if (str_starts_with($title, 'TIANGUIS')) {
            return ['type' => CommerceType::Tianguis, 'place' => self::title(trim(substr($title, strlen('TIANGUIS')))), 'route' => null];
        }

        $place = self::title(trim(str_replace($route[0] ?? '', '', $title)));

        return ['type' => CommerceType::Plaza, 'place' => $place, 'route' => $routeName];
    }

    /**
     * @param  list<string>  $header
     * @return array{name: int, giro: int, location: ?int, space: ?int}
     */
    private static function columns(array $header): array
    {
        $find = function (string $needle) use ($header): ?int {
            foreach ($header as $i => $label) {
                if (str_contains($label, $needle)) {
                    return $i;
                }
            }

            return null;
        };

        $name = $find('NOMBRE');
        $giro = $find('GIRO');

        if ($name === null || $giro === null) {
            throw new RuntimeException('Encabezado sin columnas NOMBRE y GIRO.');
        }

        return ['name' => $name, 'giro' => $giro, 'location' => $find('UBICACI'), 'space' => $find('ESPACIO')];
    }

    /**
     * Convierte "3 MTS.", "7 1/2 MTS", "1.40 MTS" o "3X3 MTS" en metros lineales.
     *
     * @return array{0: ?float, 1: string}
     */
    public static function meters(string $value): array
    {
        $value = str_replace(',', '.', $value);

        if (preg_match('/^(\d+(?:\.\d+)?)\s+(\d+)\/(\d+)/', $value, $m) && (int) $m[3] > 0) {
            return [(float) $m[1] + (int) $m[2] / (int) $m[3], ''];
        }

        if (! preg_match('/\d+(?:\.\d+)?/', $value, $m)) {
            return [null, $value === '' ? 'Sin metros en el padrón.' : "Espacio en el padrón: {$value}."];
        }

        $note = preg_match('/\d\s*X\s*\d/', $value) ? "Espacio en el padrón: {$value}." : '';

        return [(float) $m[0], $note];
    }

    private function businessType(string $name): int
    {
        // La intercalación de MySQL ignora mayúsculas y acentos: CREMERIA = CREMERÍA.
        $key = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name);

        return $this->businessTypes[$key] ??= BusinessType::query()->firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    private static function clean(mixed $value): string
    {
        $text = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;

        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    private static function title(string $text): string
    {
        $words = explode(' ', mb_convert_case($text, MB_CASE_TITLE));

        foreach ($words as $i => $word) {
            if ($i > 0 && in_array($word, ['De', 'Del', 'La', 'Las', 'Los', 'Y'], true)) {
                $words[$i] = mb_strtolower($word);
            }
        }

        return (string) preg_replace_callback('/(\d)(\p{Lu}+)/u', fn ($m) => $m[1].mb_strtolower($m[2]), implode(' ', $words));
    }
}
