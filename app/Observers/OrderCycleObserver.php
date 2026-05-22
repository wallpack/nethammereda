<?php

namespace App\Observers;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use App\Services\DeliveryToFridgeService;

class OrderCycleObserver
{
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
