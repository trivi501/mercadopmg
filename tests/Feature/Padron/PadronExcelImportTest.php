<?php

namespace Tests\Feature\Padron;

use App\Enums\CommerceType;
use App\Enums\Role;
use App\Models\Annuality;
use App\Models\Merchant;
use App\Models\Tianguis;
use App\Services\Import\BusinessTypeClassifier;
use App\Services\Import\PadronExcelImporter;
use App\Services\Import\PadronText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class PadronExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'padron').'.xlsx';
        $this->makeWorkbook([
            'AMERICAS' => [
                ['TIANGUIS LAS AMERICAS'],
                ['N°', 'NOMBRE DE COMERCIANTE ', 'GIRO ', 'UBICACIÓN', 'ESPACIO (MTS)'],
                [1, 'ROBERTO DELGADO ESPARZA', 'FRUTTAS VERDURAS', 'AMERICAS/QUINTAS', '3X3 MTS'],
                [2, 'MARÍA LÓPEZ', 'TACOS DE CANASTA', 'AMERICAS, CONDESA', '7 1/2 MTS.'],
                [3, 'SIN GIRO', '', 'AMERICAS/VIALIDAD', ''],
            ],
            'VIALIDAD 1RA' => [
                ['N°', 'NOMBRE DE COMERCIANTE', 'GIRO', 'UBICACIÓN', 'ESPACIO (MTS)'],
                [1, 'Roberto  Delgado Esparza ', 'CREMERIA', 'VIALIDAD', '6 MTS'],
            ],
            'RUTA 1' => [
                ['N°', 'NOMBRE DE COMERCIANTE', 'GIRO', 'UBICACIÓN DE TIANGUIS'],
                [1, 'ADRIAN FLORES', 'GORDITAS', 'RUTA 1'],
            ],
            'jardin juarez ' => [
                ['N°', 'NOMBRE DE COMERCIANTE', 'GIRO', 'UBICACIÓN', 'ESPACIO (MTS)'],
                [1, 'ROSALBA GÓMEZ', 'ALIMENTOS', 'JARDÍN JUÁREZ R3', '4 MTS'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /**
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    private function makeWorkbook(array $sheets): void
    {
        $writer = new Writer;
        $writer->openToFile($this->file);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();
    }

    private function import(bool $commit): array
    {
        return app(PadronExcelImporter::class)->import($this->file, $this->userWithRole(Role::Admin, ['username' => 'importador'.uniqid()]), $commit);
    }

    public function test_dry_run_does_not_write_anything(): void
    {
        $result = $this->import(commit: false);

        $this->assertSame(6, $result['summary']['Registros leídos']);
        $this->assertSame(5, $result['summary']['Comerciantes (personas únicas)']);
        $this->assertSame(0, Merchant::query()->count());
        $this->assertFalse(Tianguis::query()->where('name', 'Las Américas')->exists());
    }

    public function test_import_merges_people_and_assigns_all_tianguis(): void
    {
        $result = $this->import(commit: true);

        $this->assertSame(5, Merchant::query()->count());
        $this->assertSame(5, $result['summary']['Comerciantes nuevos']);

        $roberto = Merchant::query()->where('taxpayer_name', 'ROBERTO DELGADO ESPARZA')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Las Américas', 'Las Quintas', 'Vialidad 1ra Sección'],
            $roberto->allTianguis->pluck('name')->all(),
        );
        $this->assertSame('Las Américas', $roberto->tianguis->name, 'el principal es el de la primera hoja');
        $this->assertSame('6.00', $roberto->meters, 'se conserva la mayor medida');
        $this->assertSame('Frutas y verduras', $roberto->businessType->name);
        $this->assertSame('FRUTTAS VERDURAS / CREMERIA', $roberto->business_description);
        $this->assertSame('COM-000001', $roberto->folio);

        $maria = Merchant::query()->where('taxpayer_name', 'MARÍA LÓPEZ')->firstOrFail();
        $this->assertSame('7.50', $maria->meters);
        $this->assertSame('Alimentos preparados', $maria->businessType->name);

        $adrian = Merchant::query()->where('taxpayer_name', 'ADRIAN FLORES')->firstOrFail();
        $this->assertSame(CommerceType::Route, $adrian->commerce_type);
        $this->assertSame('Ruta 1', $adrian->route->name);
        $this->assertSame('0.00', $adrian->meters);

        $rosalba = Merchant::query()->where('taxpayer_name', 'ROSALBA GÓMEZ')->firstOrFail();
        $this->assertSame(CommerceType::Plaza, $rosalba->commerce_type);
        $this->assertSame('Jardín Juárez', $rosalba->tianguis->name);
        $this->assertSame('Ruta 3', $rosalba->route->name);

        $this->assertSame('Otros', Merchant::query()->where('taxpayer_name', 'SIN GIRO')->firstOrFail()->businessType->name);

        // Todos quedan con su anualidad del ejercicio pendiente
        $this->assertSame(5, Annuality::query()->where('fiscal_year', now()->year)->count());
    }

    public function test_issues_are_reported(): void
    {
        $issues = collect($this->import(commit: false)['issues'])->pluck('issue');

        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'CONDESA')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'sin sección')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'Medida en área')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'se unió')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'Sin giro')));
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        $this->import(commit: true);
        $second = $this->import(commit: true);

        $this->assertSame(5, Merchant::query()->count());
        $this->assertSame(0, $second['summary']['Comerciantes nuevos']);
        $this->assertSame(5, $second['summary']['Ya existían']);
    }

    public function test_artisan_command_writes_a_report(): void
    {
        $this->userWithRole(Role::Admin, ['username' => 'admin']);
        $report = sys_get_temp_dir().'/reporte-'.uniqid().'.xlsx';

        $this->artisan('padron:importar-excel', ['archivo' => $this->file, '--reporte' => $report])
            ->expectsOutputToContain('Ensayo')
            ->assertSuccessful();

        $this->assertFileExists($report);
        $this->assertSame(0, Merchant::query()->count());
        @unlink($report);
    }

    public function test_text_helpers(): void
    {
        $this->assertSame(3.0, PadronText::meters('3 MTS.')['meters']);
        $this->assertSame(7.5, PadronText::meters('7 1/2 MTS.')['meters']);
        $this->assertSame(1.4, PadronText::meters('1.40 MTS')['meters']);
        $this->assertNull(PadronText::meters('MOVIL')['meters']);
        $this->assertSame(['VILLAS', 'QUINTAS', 'AMERICAS'], PadronText::places('VILLAS, QUINTAS Y AMERICAS'));
        $this->assertSame('Carnes y embutidos', BusinessTypeClassifier::classify('CARNICERIA Y EMBUTIDOS'));
        $this->assertSame('Cremería y lácteos', BusinessTypeClassifier::classify('CREMERIA, CARNICERIA Y ABARROTES'));
        $this->assertSame('Alimentos preparados', BusinessTypeClassifier::classify('CARNITAS'));
        $this->assertSame('Frutas y verduras', BusinessTypeClassifier::classify('NOPAL, AJO'));
        $this->assertSame('Otros', BusinessTypeClassifier::classify('TRABAJOS VARIADOS XYZ'));
    }
}
