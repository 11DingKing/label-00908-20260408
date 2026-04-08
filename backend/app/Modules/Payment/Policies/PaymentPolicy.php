<?php

namespace App\Modules\Payment\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin() || $user->hasPermission('payments.view')) return true;
        return $user->id === $payment->user_id;
    }

    public function create(User $user): bool { return true; }

    public function refund(User $user, Payment $payment): bool
    {
        if ($user->isAdmin() || $user->hasPermission('refunds.create')) return true;
        return $user->id === $payment->user_id;
    }
}
