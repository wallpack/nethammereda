<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class SupplierOrderExportService
{
    public function rowsForCycle(OrderCycle $cycle): Collection
    {
        return OrderItem::query()
            ->selectRaw(
                'order_items.title_snapshot,
                SUM(order_items.quantity) as quantity_sum,
                SUM(order_items.quantity * order_items.price_snapshot) as total_sum'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_cycle_id', $cycle->id)
            ->where('orders.status', OrderStatus::Submitted->value)
            ->where('order_items.status', '!=', OrderItemStatus::Cancelled->value)
            ->groupBy('order_items.title_snapshot')
            ->orderBy('order_items.title_snapshot')
            ->get();
    }

    public function totalForCycle(OrderCycle $cycle): float
    {
        return (float) $this->rowsForCycle($cycle)
            ->sum(fn (OrderItem $row): float => (float) $row->total_sum);
    }
}
