<?php

namespace App\Observers;

use App\Enums\OrderCycleStatus;
use App\Exceptions\OrderCycleCannotBeMarkedDeliveredException;
use App\Models\OrderCycle;
use App\Services\DeliveryToFridgeService;

class OrderCycleObserver
{
    public function updating(OrderCycle $orderCycle): void
    {
        if (! $orderCycle->isDirty('status')) {
            return;
        }

        if ($orderCycle->status !== OrderCycleStatus::Delivered) {
            return;
        }

        $originalStatus = OrderCycleStatus::tryFrom((string) $orderCycle->getRawOriginal('status'));

        if ($originalStatus === OrderCycleStatus::SentToSupplier) {
            return;
        }

        throw OrderCycleCannotBeMarkedDeliveredException::forCycleStatus($orderCycle);
    }

    public function created(OrderCycle $orderCycle): void
    {
        if ($orderCycle->status !== OrderCycleStatus::Delivered) {
            return;
        }

        app(DeliveryToFridgeService::class)->syncFromDeliveredCycle($orderCycle);
    }

    public function updated(OrderCycle $orderCycle): void
    {
        if (! $orderCycle->wasChanged('status')) {
            return;
        }

        if ($orderCycle->status !== OrderCycleStatus::Delivered) {
            return;
        }

        app(DeliveryToFridgeService::class)->syncFromDeliveredCycle($orderCycle);
    }
}
