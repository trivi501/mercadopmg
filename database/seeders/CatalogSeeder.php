<?php

namespace Database\Seeders;

use App\Enums\ConceptType;
use App\Enums\Periodicity;
use App\Models\BusinessType;
use App\Models\Concept;
use App\Models\FolioSequence;
use App\Models\MarketRoute;
use App\Models\Setting;
use App\Models\Tianguis;
use Illuminate\Database\Seeder;

/**
 * Catálogos base del sistema. Es idempotente: puede ejecutarse en producción.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Ropa', 'Calzado', 'Alimentos', 'Frutas y verduras', 'Herramientas', 'Electrónica', 'Juguetes', 'Accesorios', 'Artesanías', 'Cosméticos', 'Otros'] as $name) {
            BusinessType::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        Concept::query()->firstOrCreate(['code' => 'USV-001'], [
            'name' => 'Plaza Uso de Suelo Vía Pública',
            'description' => 'Anualidad por el uso de suelo en plaza o vía pública.',
            'type' => ConceptType::Annuality,
            'amount' => '155.00',
            'periodicity' => Periodicity::Annual,
            'is_active' => true,
        ]);

        Concept::query()->firstOrCreate(['code' => 'BOL-001'], [
            'name' => 'Boleto Tianguista',
            'description' => 'Boleto por día de tianguis.',
            'type' => ConceptType::Ticket,
            'amount' => '40.00',
            'periodicity' => Periodicity::PerEvent,
            'is_active' => true,
        ]);

        Tianguis::query()->firstOrCreate(['name' => 'Tianguis Municipal'], [
            'description' => 'Tianguis dominical en el centro.',
            'operating_day' => 'domingo',
            'schedule' => '07:00 - 16:00',
            'is_active' => true,
        ]);

        MarketRoute::query()->firstOrCreate(['name' => 'Ruta 01'], ['zone' => 'Centro', 'is_active' => true]);

        FolioSequence::query()->firstOrCreate(['name' => 'merchant'], ['prefix' => 'COM-', 'padding' => 6, 'last_number' => 0]);
        FolioSequence::query()->firstOrCreate(['name' => 'receipt'], ['prefix' => 'REC-', 'padding' => 8, 'last_number' => 0]);

        $settings = [
            'municipality_name' => ['Municipio', 'Nombre del municipio que aparece en tickets y reportes'],
            'municipality_state' => ['', 'Estado'],
            'treasury_name' => ['Tesorería Municipal', 'Área responsable de la recaudación'],
            'ticket_footer' => ['Conserve este recibo como comprobante de pago.', 'Leyenda al pie del ticket'],
        ];
        foreach ($settings as $key => [$value, $description]) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value, 'group' => 'general', 'description' => $description]);
        }
    }
}
