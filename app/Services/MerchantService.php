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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Merchant
    {
        if (! empty($data['uuid']) && $existing = Merchant::withTrashed()->where('uuid', $data['uuid'])->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $user) {
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
        return DB::transaction(function () use ($merchant, $data, $user, $metersReason) {
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

            if ($merchant->wasChanged('status') && $merchant->status === MerchantStatus::Active) {
                $this->annualities->ensureForMerchant($merchant, (int) now()->year);
            }

            return $merchant;
        });
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
