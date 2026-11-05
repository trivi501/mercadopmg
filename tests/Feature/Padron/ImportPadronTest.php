<?php

namespace Tests\Feature\Padron;

use App\Console\Commands\ImportPadron;
use App\Enums\CommerceType;
use App\Enums\Role;
use App\Models\Annuality;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportPadronTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userWithRole(Role::Admin, ['username' => 'admin']);
        $this->file = tempnam(sys_get_temp_dir(), 'padron').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($this->file);
        $this->sheet($writer, 'AMERICAS', 'TIANGUIS LAS AMERICAS', [
            [1, 'LIZ BRISELDA MACIAS CERVANTES ', 'TORTILLAS', 'AMERICAS/VIALIDAD', '7 1/2 MTS.'],
            [2, 'CARLOS GALLEGOS  DOVITA', 'HERRAMIENTA', 'AMERICAS', '3X3 MTS'],
            [3, 'CARLOS GALLEGOS DOVITA', 'CARNITAS', 'AMERICAS', '6'],
            ['', '', '', '', ''],
        ]);
        $writer->addNewSheetAndMakeItCurrent();
        $this->sheet($writer, 'RUTA 1', 'RUTA 1', [[1, 'ADRIAN FLORES', 'GORDITAS', 'RUTA 1']]);
        $writer->addNewSheetAndMakeItCurrent();
        $this->sheet($writer, 'jardin juarez ', ' JARDIN JUAREZ (RUTA 1)', [[1, 'MANUEL RAMÍREZ BARRERA', 'ALIMENTOS ', 'JARDÍN JUÁREZ R3', 'MOVIL']]);
        $writer->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    /**
     * @param  list<list<int|string>>  $rows
     */
    private function sheet(Writer $writer, string $name, string $title, array $rows): void
    {
        $writer->getCurrentSheet()->setName($name);
        $writer->addRow(Row::fromValues(['PADRÓN DE COMERCIANTES DE TIANGUIS 2026']));
        $writer->addRow(Row::fromValues(['UNIDAD DE PLAZAS, MERCADO Y COMERCIO EN LA VÍA PÚBLICA']));
        $writer->addRow(Row::fromValues([$title]));
        $writer->addRow(Row::fromValues(['N°', 'NOMBRE DE COMERCIANTE ', 'GIRO ', 'UBICACIÓN', 'ESPACIO (MTS)']));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
    }

    public function test_it_imports_tianguis_routes_and_plazas(): void
    {
        $this->artisan('padron:importar', ['archivo' => $this->file])->assertSuccessful();

        $this->assertSame(5, Merchant::query()->count());

        $liz = Merchant::query()->where('taxpayer_name', 'LIZ BRISELDA MACIAS CERVANTES')->firstOrFail();
        $this->assertSame(CommerceType::Tianguis, $liz->commerce_type);
        $this->assertSame('Las Americas', $liz->tianguis?->name);
        $this->assertSame('TORTILLAS', $liz->businessType->name);
        $this->assertSame('7.50', $liz->meters);
        $this->assertSame('AMERICAS/VIALIDAD', $liz->location);
        $this->assertSame(1, $liz->measurements()->count());
        $this->assertTrue(Annuality::query()->where('merchant_id', $liz->id)->where('fiscal_year', now()->year)->exists());

        $carlos = Merchant::query()->where('taxpayer_name', 'CARLOS GALLEGOS DOVITA')->where('business_description', 'HERRAMIENTA')->firstOrFail();
        $this->assertSame('3.00', $carlos->meters);
        $this->assertStringContainsString('3X3 MTS', (string) $carlos->notes);

        $adrian = Merchant::query()->where('taxpayer_name', 'ADRIAN FLORES')->firstOrFail();
        $this->assertSame(CommerceType::Route, $adrian->commerce_type);
        $this->assertSame('Ruta 1', $adrian->route?->name);

        $manuel = Merchant::query()->where('taxpayer_name', 'MANUEL RAMÍREZ BARRERA')->firstOrFail();
        $this->assertSame(CommerceType::Plaza, $manuel->commerce_type);
        $this->assertSame('Ruta 1', $manuel->route?->name);
        $this->assertSame('Jardin Juarez', $manuel->location_reference);
        $this->assertSame('0.00', $manuel->meters);
    }

    public function test_running_it_twice_does_not_duplicate_merchants(): void
    {
        $this->artisan('padron:importar', ['archivo' => $this->file])->assertSuccessful();
        $this->artisan('padron:importar', ['archivo' => $this->file])->assertSuccessful();

        $this->assertSame(5, Merchant::query()->count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->artisan('padron:importar', ['archivo' => $this->file, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Merchant::query()->count());
    }

    public function test_it_parses_space_formats(): void
    {
        $this->assertSame([3.0, ''], ImportPadron::meters('3 MTS.'));
        $this->assertSame([1.4, ''], ImportPadron::meters('1.40 MTS'));
        $this->assertSame([4.5, ''], ImportPadron::meters('4 1/2 MTS.'));
        $this->assertSame(2.5, ImportPadron::meters('2.5 X2.5 MTS')[0]);
        $this->assertSame([null, 'Sin metros en el padrón.'], ImportPadron::meters(''));
    }
}
