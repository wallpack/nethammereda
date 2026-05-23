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
        $isOpenStatus = $cycle->status === OrderCycleStatus::Open;
        $deadlinePassed = $cycle->closes_at !== null && $cycle->closes_at->isPast();
        $isOrderable = $cycle->isOpenForOrdering();

        return [
            'id' => $cycle->id,
            'title' => $cycle->title,
            'starts_at' => $cycle->starts_at,
            'closes_at' => $cycle->closes_at,
            'status' => $cycle->status->value,
            'is_open_status' => $isOpenStatus,
            'is_orderable' => $isOrderable,
            'can_order' => $isOrderable,
            'deadline_passed' => $deadlinePassed,
            'is_open_for_ordering' => $isOrderable,
            'is_open' => $isOrderable,
            'is_closed' => $cycle->status === OrderCycleStatus::Closed,
            'is_delivered' => $cycle->status === OrderCycleStatus::Delivered,
            'status_label' => $this->orderCycleStatusLabel($cycle->status),
            'availability_label' => $this->availabilityLabel($cycle, $isOrderable, $deadlinePassed),
            'availability_description' => $this->availabilityDescription($cycle, $isOrderable, $deadlinePassed),
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
        return $status->label();
    }

    protected function orderCycleStatusLabel(OrderCycleStatus $status): string
    {
        return $status->label();
    }

    protected function availabilityLabel(OrderCycle $cycle, bool $isOrderable, bool $deadlinePassed): string
    {
        if ($isOrderable) {
            return 'Заказ открыт';
        }

        if ($cycle->status === OrderCycleStatus::Open && $deadlinePassed) {
            return 'Дедлайн прошел';
        }

        return match ($cycle->status) {
            OrderCycleStatus::Draft => 'Заказ еще не открыт',
            OrderCycleStatus::Open => 'Прием заказов завершен',
            OrderCycleStatus::Closed => 'Заказ закрыт',
            OrderCycleStatus::SentToSupplier => 'Отправлен поставщику',
            OrderCycleStatus::Delivered => 'Доставлен',
            OrderCycleStatus::Archived => 'Архивирован',
        };
    }

    protected function availabilityDescription(OrderCycle $cycle, bool $isOrderable, bool $deadlinePassed): string
    {
        if ($isOrderable) {
            return 'Можно добавлять блюда до дедлайна.';
        }

        if ($cycle->status === OrderCycleStatus::Open && $deadlinePassed) {
            return 'Прием заказов завершен.';
        }

        return match ($cycle->status) {
            OrderCycleStatus::Draft => 'Администратор еще не открыл сбор заказов.',
            OrderCycleStatus::Open => 'Прием заказов завершен.',
            OrderCycleStatus::Closed => 'Администратор закрыл сбор заказов.',
            OrderCycleStatus::SentToSupplier => 'Сводный заказ уже отправлен поставщику.',
            OrderCycleStatus::Delivered => 'Доставка отмечена, блюда попали в холодильники.',
            OrderCycleStatus::Archived => 'Недельный цикл завершен.',
        };
    }

    protected function deadlineLabel(OrderCycle $cycle): ?string
    {
        if ($cycle->closes_at === null) {
            return null;
        }

        $prefix = $cycle->isOpenForOrdering() ? 'Дедлайн' : 'Дедлайн был';

        return $prefix.' '.$cycle->closes_at->format('Y-m-d H:i');
    }
}
