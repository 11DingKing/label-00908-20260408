<?php

namespace App\Modules\Subscription\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('subscriptions.view');
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->hasPermission('subscriptions.view')) return true;
        return $user->id === $subscription->user_id;
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->hasPermission('subscriptions.manage')) return true;
        return $user->id === $subscription->user_id;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->hasPermission('subscriptions.manage')) return true;
        return $user->id === $subscription->user_id;
    }

    public function upgrade(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->hasPermission('subscriptions.manage')) return true;
        return $user->id === $subscription->user_id;
    }

    public function downgrade(User $user, Subscription $subscription): bool
    {
        if ($user->isAdmin() || $user->hasPermission('subscriptions.manage')) return true;
        return $user->id === $subscription->user_id;
    }
}
