<?php

namespace App\Services;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Models\FridgeItem;
use App\Models\OrderCycle;
use Illuminate\Support\Facades\DB;

class DeliveryToFridgeService
{
    public function syncFromDeliveredCycle(OrderCycle $cycle): int
    {
        if ($cycle->status !== OrderCycleStatus::Delivered) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($cycle, &$created): void {
            $cycle->loadMissing('orders.items');

            foreach ($cycle->orders as $order) {
                foreach ($order->items as $item) {
                    if ($item->status === OrderItemStatus::Cancelled) {
                        continue;
                    }

                    $fridgeItem = FridgeItem::query()->firstOrCreate(
                        ['order_item_id' => $item->id],
                        [
                            'user_id' => $order->user_id,
                            'menu_item_id' => $item->menu_item_id,
                            'title_snapshot' => $item->title_snapshot,
                            'quantity_total' => $item->quantity,
                            'quantity_remaining' => $item->quantity,
                            'status' => FridgeItemStatus::InFridge,
                            'arrived_at' => now(),
                            'expires_at' => now()->addDays(7),
                        ],
                    );

                    if ($fridgeItem->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        });

        return $created;
    }
}
