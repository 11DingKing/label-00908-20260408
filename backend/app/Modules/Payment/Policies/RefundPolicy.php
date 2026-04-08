<?php

namespace App\Modules\Payment\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('refunds.view');
    }

    public function view(User $user, Refund $refund): bool
    {
        return $user->id === $refund->user_id || $user->isAdmin() || $user->hasPermission('refunds.view');
    }

    public function process(User $user, Refund $refund): bool
    {
        return $user->isAdmin() || $user->hasPermission('refunds.process');
    }
}
