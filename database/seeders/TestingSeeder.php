<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos mínimos para las pruebas automatizadas.
 */
class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);
    }
}
