<?php

namespace App\Modules\Billing\Policies;

use App\Models\Bill;
use App\Models\User;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('bills.view');
    }

    public function view(User $user, Bill $bill): bool
    {
        if ($user->isAdmin() || $user->hasPermission('bills.view')) return true;
        return $user->id === $bill->user_id;
    }

    public function download(User $user, Bill $bill): bool
    {
        if ($user->isAdmin() || $user->hasPermission('bills.view')) return true;
        return $user->id === $bill->user_id;
    }
}
