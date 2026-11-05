<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\PadronExcelImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

#[Signature('padron:importar-excel
    {archivo : Ruta del Excel del padrón}
    {--confirmar : Guarda los cambios. Sin esta opción sólo se hace un ensayo}
    {--usuario=admin : Usuario que queda como responsable de las altas}
    {--reporte= : Ruta del reporte .xlsx (por omisión en storage/app/importaciones)}')]
#[Description('Importa el padrón de comerciantes desde el Excel (una hoja por tianguis, ruta o plaza)')]
class ImportPadronExcel extends Command
{
    public function handle(PadronExcelImporter $importer): int
    {
        $user = User::query()->where('username', $this->option('usuario'))->first();
        if (! $user) {
            $this->error("No existe el usuario \"{$this->option('usuario')}\".");

            return self::FAILURE;
        }

        // Las altas y la auditoría quedan a nombre de este usuario.
        Auth::setUser($user);
        $commit = (bool) $this->option('confirmar');

        $this->info($commit ? 'Importando…' : 'Ensayo: no se guardará nada.');
        $result = $importer->import((string) $this->argument('archivo'), $user, $commit);

        $this->table(['Concepto', 'Valor'], collect($result['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all());

        $report = $this->option('reporte') ?: storage_path('app/importaciones/padron-'.($commit ? 'importacion' : 'ensayo').'-'.now()->format('Ymd-His').'.xlsx');
        $this->writeReport($report, $result);
        $this->line("Reporte: <comment>{$report}</comment>");

        if (! $commit) {
            $this->warn('Revise el reporte y ejecute de nuevo con --confirmar para guardar.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{summary: array<string, int|string>, merchants: list<array<string, mixed>>, issues: list<array<string, mixed>>}  $result
     */
    private function writeReport(string $path, array $result): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $bold = (new Style)->setFontBold();
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Resumen');
        foreach ($result['summary'] as $label => $value) {
            $writer->addRow(Row::fromValues([$label, $value]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Comerciantes');
        $writer->addRow(Row::fromValues(['Folio', 'Estado', 'Nombre', 'Giro', 'Giro original', 'Tipo', 'Tianguis', 'Ruta', 'Metros', 'Hojas (renglón)', 'Ubicación original'], $bold));
        foreach ($result['merchants'] as $m) {
            $writer->addRow(Row::fromValues(array_values($m)));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Revisar');
        $writer->addRow(Row::fromValues(['Hoja', 'Renglón', 'Nombre', 'Observación'], $bold));
        foreach ($result['issues'] as $i) {
            $writer->addRow(Row::fromValues([$i['sheet'], $i['row'], $i['name'], $i['issue']]));
        }

        $writer->close();
    }
}
