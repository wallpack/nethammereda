<?php

namespace App\Services;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;

class OrderCycleAutoCloser
{
    public function closeIfExpired(?OrderCycle $cycle): bool
    {
        if ($cycle === null) {
            return false;
        }

        $cycle->refresh();
        $now = now();

        if (
            $cycle->status !== OrderCycleStatus::Open
            || $cycle->closes_at === null
            || $cycle->closes_at->gt($now)
        ) {
            return false;
        }

        $closed = OrderCycle::query()
            ->whereKey($cycle->getKey())
            ->where('status', OrderCycleStatus::Open->value)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', $now)
            ->update([
                'status' => OrderCycleStatus::Closed->value,
            ]);

        $cycle->refresh();

        return $closed > 0;
    }

    public function closeExpiredOpenCycles(): int
    {
        $now = now();

        return OrderCycle::query()
            ->where('status', OrderCycleStatus::Open->value)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', $now)
            ->update([
                'status' => OrderCycleStatus::Closed->value,
            ]);
    }
}
