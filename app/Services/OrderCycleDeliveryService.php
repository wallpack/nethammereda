<?php

namespace App\Services;

use App\Enums\OrderCycleStatus;
use App\Exceptions\OrderCycleCannotBeMarkedDeliveredException;
use App\Models\OrderCycle;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderCycleDeliveryService
{
    public function markDelivered(OrderCycle $cycle, ?User $deliveredBy = null): OrderCycle
    {
        return DB::transaction(function () use ($cycle, $deliveredBy): OrderCycle {
            $cycle = OrderCycle::query()
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            if ($cycle->status !== OrderCycleStatus::SentToSupplier) {
                throw OrderCycleCannotBeMarkedDeliveredException::forCycleStatus($cycle);
            }

            $cycle->transitionTo(OrderCycleStatus::Delivered, [
                'delivered_at' => $cycle->delivered_at ?? now(),
                'delivered_by' => $cycle->delivered_by ?? $deliveredBy?->id,
            ]);

            return $cycle->fresh(['deliveredBy']);
        });
    }
}
