<?php

namespace App\Support;

use App\Enums\CommerceType;
use App\Enums\MerchantStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\Role;
use App\Models\BusinessType;
use App\Models\Concept;
use App\Models\MarketRoute;
use App\Models\Tianguis;
use App\Models\User;

/**
 * Opciones para los selects y filtros del panel web.
 */
class Options
{
    /**
     * @param  list<string>  $only
     * @return array<string, mixed>
     */
    public static function for(array $only): array
    {
        $all = [
            'business_types' => fn () => BusinessType::query()->active()->orderBy('name')->get(['id', 'name']),
            'tianguis' => fn () => Tianguis::query()->active()->orderBy('name')->get(['id', 'name', 'operating_day']),
            'routes' => fn () => MarketRoute::query()->active()->orderBy('name')->get(['id', 'name']),
            'concepts' => fn () => Concept::query()->orderBy('code')->get(['id', 'code', 'name', 'type', 'amount', 'is_active']),
            'users' => fn () => User::query()->orderBy('name')->get(['id', 'name']),
            'commerce_types' => fn () => CommerceType::options(),
            'merchant_statuses' => fn () => MerchantStatus::options(),
            'payment_methods' => fn () => PaymentMethod::options(),
            'receipt_statuses' => fn () => ReceiptStatus::options(),
            'roles' => fn () => Role::options(),
        ];

        return collect($only)->mapWithKeys(fn (string $key) => [$key => $all[$key]()])->all();
    }
}
