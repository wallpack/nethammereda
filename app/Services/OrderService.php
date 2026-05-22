<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\User;

class OrderService
{
    public function getOrCreateOrder(User $user, OrderCycle $cycle): Order
    {
        return Order::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'order_cycle_id' => $cycle->id,
            ],
            [
                'status' => OrderStatus::Draft,
                'total_price' => 0,
            ],
        );
    }

    public function markAsDraft(Order $order): void
    {
        $order->status = OrderStatus::Draft;
        $order->submitted_at = null;
        $order->save();
    }

    public function recalculate(Order $order): void
    {
        $order->recalculateTotal();
    }
}
