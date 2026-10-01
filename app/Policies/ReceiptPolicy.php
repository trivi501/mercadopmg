<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ReceiptsView->value);
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $user->can(Permission::ReceiptsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaymentsCreate->value);
    }

    public function print(User $user, Receipt $receipt): bool
    {
        return $user->can(Permission::ReceiptsPrint->value);
    }

    public function reprint(User $user, Receipt $receipt): bool
    {
        return $user->can(Permission::ReceiptsReprint->value);
    }

    public function cancel(User $user, Receipt $receipt): bool
    {
        return $user->can(Permission::ReceiptsCancel->value);
    }

    /**
     * Los recibos nunca se eliminan.
     */
    public function delete(User $user, Receipt $receipt): bool
    {
        return false;
    }
}
