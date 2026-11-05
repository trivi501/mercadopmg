<?php

namespace App\Services;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly AnnualityService $annualities,
    ) {}

    /**
     * Registra un comerciante. Si llega un UUID ya registrado (reintento desde
     * la app sin conexión) se devuelve el comerciante existente.
     *
     * `tianguis_ids` (opcional) son todos los tianguis donde opera; el primero
     * se usa como principal si no se indica `tianguis_id`.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Merchant
    {
        if (! empty($data['uuid']) && $existing = Merchant::withTrashed()->where('uuid', $data['uuid'])->first()) {
            return $existing;
        }

        [$data, $tianguisIds] = $this->extractTianguis($data);

        return DB::transaction(function () use ($data, $user, $tianguisIds) {
            $merchant = Merchant::create([
                ...$data,
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'folio' => $this->folios->next(FolioGenerator::MERCHANT),
                'status' => $data['status'] ?? MerchantStatus::Active->value,
                'registered_at' => $data['registered_at'] ?? today(),
                'meters' => $data['meters'] ?? 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $merchant->measurements()->create([
                'meters' => $merchant->meters,
                'start_date' => $merchant->registered_at,
                'user_id' => $user->id,
                'reason' => 'Alta en el padrón',
            ]);

            $this->syncTianguis($merchant, $tianguisIds);

            if ($merchant->status === MerchantStatus::Active) {
                $this->annualities->ensureForMerchant($merchant, (int) now()->year);
            }

            return $merchant;
        });
    }

    /**
     * Actualiza un comerciante; si cambian los metros se cierra la medición
     * vigente y se abre una nueva para conservar el histórico.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Merchant $merchant, array $data, User $user, ?string $metersReason = null): Merchant
    {
        $hasTianguis = array_key_exists('tianguis_ids', $data);
        [$data, $tianguisIds] = $this->extractTianguis($data);

        return DB::transaction(function () use ($merchant, $data, $user, $metersReason, $hasTianguis, $tianguisIds) {
            unset($data['uuid'], $data['folio']);

            $merchant->fill([...$data, 'updated_by' => $user->id]);
            $metersChanged = $merchant->isDirty('meters');
            $merchant->save();

            if ($metersChanged) {
                $merchant->measurements()->whereNull('end_date')->update(['end_date' => today()]);
                $merchant->measurements()->create([
                    'meters' => $merchant->meters,
                    'start_date' => today(),
                    'user_id' => $user->id,
                    'reason' => $metersReason ?: 'Actualización de metros',
                ]);
            }

            if ($hasTianguis || $merchant->wasChanged('tianguis_id')) {
                $this->syncTianguis($merchant, $tianguisIds);
            }

            if ($merchant->wasChanged('status') && $merchant->status === MerchantStatus::Active) {
                $this->annualities->ensureForMerchant($merchant, (int) now()->year);
            }

            return $merchant;
        });
    }

    /**
     * Separa `tianguis_ids` de los datos y define el principal si falta.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<int>|null}
     */
    private function extractTianguis(array $data): array
    {
        $ids = null;

        if (array_key_exists('tianguis_ids', $data)) {
            $ids = array_values(array_unique(array_map('intval', array_filter((array) $data['tianguis_ids']))));
            unset($data['tianguis_ids']);

            if (empty($data['tianguis_id']) && $ids !== []) {
                $data['tianguis_id'] = $ids[0];
            }
        }

        return [$data, $ids];
    }

    /**
     * El tianguis principal siempre forma parte de la lista.
     *
     * @param  list<int>|null  $ids  null = conservar los adicionales actuales
     */
    private function syncTianguis(Merchant $merchant, ?array $ids): void
    {
        $ids ??= $merchant->allTianguis()->pluck('tianguis.id')->map(fn ($id) => (int) $id)->all();

        if ($merchant->tianguis_id) {
            $ids[] = $merchant->tianguis_id;
        }

        $merchant->allTianguis()->sync(array_values(array_unique($ids)));
    }

    /**
     * Comerciantes dentro de un radio en kilómetros, ordenados por distancia.
     *
     * @return Collection<int, Merchant>
     */
    public function nearby(float $lat, float $lng, float $radiusKm, int $limit = 200): Collection
    {
        return Merchant::query()
            ->with(['businessType:id,name', 'tianguis:id,name', 'route:id,name'])
            ->nearby($lat, $lng, $radiusKm)
            ->get()
            ->each(fn (Merchant $m) => $m->setAttribute('distance_km', round(self::haversine($lat, $lng, (float) $m->latitude, (float) $m->longitude), 3)))
            ->filter(fn (Merchant $m) => $m->getAttribute('distance_km') <= $radiusKm)
            ->sortBy('distance_km')
            ->take($limit)
            ->values();
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
