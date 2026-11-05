<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CashRegisterSession;
use App\Models\User;

/**
 * El recaudador opera y consulta sus propios cortes; con `cash.manage`
 * (administrador) se consultan y cierran los de cualquier recaudador.
 */
class CashRegisterSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsCreate->value) || $user->can(Permission::CashManage->value);
    }

    public function view(User $user, CashRegisterSession $session): bool
    {
        return $session->user_id === $user->id || $user->can(Permission::CashManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaymentsCreate->value);
    }

    public function close(User $user, CashRegisterSession $session): bool
    {
        return $session->isOpen()
            && (($session->user_id === $user->id && $user->can(Permission::PaymentsCreate->value)) || $user->can(Permission::CashManage->value));
    }

    public function viewAll(User $user): bool
    {
        return $user->can(Permission::CashManage->value);
    }

    public function delete(User $user, CashRegisterSession $session): bool
    {
        return false;
    }
}
