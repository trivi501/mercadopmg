<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Merchant;
use App\Models\User;

class MerchantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MerchantsView->value);
    }

    public function view(User $user, Merchant $merchant): bool
    {
        return $user->can(Permission::MerchantsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MerchantsCreate->value);
    }

    public function update(User $user, Merchant $merchant): bool
    {
        return $user->can(Permission::MerchantsUpdate->value);
    }

    public function delete(User $user, Merchant $merchant): bool
    {
        return $user->can(Permission::MerchantsDelete->value);
    }

    /**
     * Registrar cobros a este comerciante.
     */
    public function charge(User $user, Merchant $merchant): bool
    {
        return $user->can(Permission::PaymentsCreate->value);
    }
}
