<?php

namespace App\Modules\Subscription\Contracts;

use App\Models\UsageRecord;
use App\Models\User;

interface UsageServiceInterface
{
    public function recordUsage(User $user, string $dimensionCode, float $quantity, array $metadata = []): UsageRecord;
}
