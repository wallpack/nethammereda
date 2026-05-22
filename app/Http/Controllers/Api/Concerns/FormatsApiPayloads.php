<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCycle;

trait FormatsApiPayloads
{
    protected function cyclePayload(OrderCycle $cycle): array
    {
        $isOpen = $cycle->isOpenForOrdering();

        return [
            'id' => $cycle->id,
            'title' => $cycle->title,
            'starts_at' => $cycle->starts_at,
            'closes_at' => $cycle->closes_at,
            'status' => $cycle->status->value,
            'is_open_for_ordering' => $isOpen,
            'is_open' => $isOpen,
            'is_closed' => ! $isOpen && $cycle->status !== OrderCycleStatus::Delivered,
            'is_delivered' => $cycle->status === OrderCycleStatus::Delivered,
            'status_label' => $this->orderCycleStatusLabel($cycle->status),
            'deadline_label' => $this->deadlineLabel($cycle),
        ];
    }

    protected function orderPayload(Order $order): array
    {
        $order->loadMissing(['cycle', 'items.menuItem']);
        $itemsCount = $order->items->count();
        $payload = $order->toArray();
        unset($payload['cycle']);

        return array_merge($payload, [
            'items_count' => $itemsCount,
            'total_price' => $order->total_price,
            'can_submit' => $order->status === OrderStatus::Draft
                && $order->cycle?->isOpenForOrdering() === true
                && $itemsCount > 0,
            'status_label' => $this->orderStatusLabel($order->status),
        ]);
    }

    protected function orderStatusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft => 'Draft',
            OrderStatus::Submitted => 'Submitted',
            OrderStatus::Cancelled => 'Cancelled',
        };
    }

    protected function orderCycleStatusLabel(OrderCycleStatus $status): string
    {
        return match ($status) {
            OrderCycleStatus::Draft => 'Draft',
            OrderCycleStatus::Open => 'Open',
            OrderCycleStatus::Closed => 'Closed',
            OrderCycleStatus::SentToSupplier => 'Sent to supplier',
            OrderCycleStatus::Delivered => 'Delivered',
            OrderCycleStatus::Archived => 'Archived',
        };
    }

    protected function deadlineLabel(OrderCycle $cycle): ?string
    {
        if ($cycle->closes_at === null) {
            return null;
        }

        $prefix = $cycle->isOpenForOrdering() ? 'Closes at' : 'Closed at';

        return $prefix.' '.$cycle->closes_at->format('Y-m-d H:i');
    }
}
