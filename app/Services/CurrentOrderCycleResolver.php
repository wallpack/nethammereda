<?php

namespace App\Services;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;

class CurrentOrderCycleResolver
{
    public function resolve(): ?OrderCycle
    {
        return OrderCycle::query()
            ->where('status', OrderCycleStatus::Open)
            ->orderByDesc('starts_at')
            ->first()
            ?? OrderCycle::query()
                ->where('status', '!=', OrderCycleStatus::Archived)
                ->orderByDesc('starts_at')
                ->first();
    }
}
