<?php

namespace App\Console\Commands;

use App\Services\AnnualityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('padron:anualidades {year? : Ejercicio fiscal (por omisión, el año actual)}')]
#[Description('Genera las anualidades pendientes del ejercicio para todos los comerciantes activos')]
class GenerateAnnualities extends Command
{
    public function handle(AnnualityService $annualities): int
    {
        $year = (int) ($this->argument('year') ?: now()->year);

        if ($year < 2000 || $year > now()->year + 1) {
            $this->error("Ejercicio inválido: {$year}");

            return self::FAILURE;
        }

        $created = $annualities->generateForYear($year);
        $this->info("Ejercicio {$year}: {$created} anualidades nuevas generadas.");

        return self::SUCCESS;
    }
}
