<?php

namespace App\Services;

use App\Enums\AnnualityStatus;
use App\Enums\ConceptType;
use App\Enums\MerchantStatus;
use App\Models\Annuality;
use App\Models\Concept;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Genera las obligaciones anuales (uso de suelo) por ejercicio fiscal.
 */
class AnnualityService
{
    /**
     * Crea (si no existen) las anualidades pendientes de un comerciante para un ejercicio.
     */
    public function ensureForMerchant(Merchant $merchant, int $fiscalYear): void
    {
        foreach ($this->annualityConcepts() as $concept) {
            Annuality::query()->firstOrCreate(
                ['merchant_id' => $merchant->id, 'concept_id' => $concept->id, 'fiscal_year' => $fiscalYear],
                ['amount' => $concept->amount, 'status' => AnnualityStatus::Pending],
            );
        }
    }

    /**
     * Genera las anualidades del ejercicio para todos los comerciantes activos.
     *
     * @return int Número de obligaciones nuevas.
     */
    public function generateForYear(int $fiscalYear): int
    {
        $created = 0;
        $concepts = $this->annualityConcepts();

        Merchant::query()->where('status', MerchantStatus::Active->value)->select('id')
            ->chunkById(500, function ($merchants) use ($concepts, $fiscalYear, &$created) {
                foreach ($merchants as $merchant) {
                    foreach ($concepts as $concept) {
                        $annuality = Annuality::query()->firstOrCreate(
                            ['merchant_id' => $merchant->id, 'concept_id' => $concept->id, 'fiscal_year' => $fiscalYear],
                            ['amount' => $concept->amount, 'status' => AnnualityStatus::Pending],
                        );
                        $created += (int) $annuality->wasRecentlyCreated;
                    }
                }
            });

        return $created;
    }

    /**
     * @return Collection<int, Concept>
     */
    private function annualityConcepts()
    {
        return Concept::query()->active()->ofType(ConceptType::Annuality)->get();
    }
}
