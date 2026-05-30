<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use Carbon\CarbonInterface;

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
            'deadline_date' => $this->deadlineDate($cycle),
            'deadline_time' => $this->deadlineTime($cycle),
            'deadline_display' => $this->deadlineDisplay($cycle),
            'deadline_display_full' => $this->deadlineDisplayFull($cycle),
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
        $order->load([
            'cycle',
            'items' => fn ($query) => $query
                ->with('menuItem')
                ->orderBy('order_items.created_at')
                ->orderBy('order_items.id'),
        ]);
        $itemsCount = $order->items->count();
        $isOpenForOrdering = $order->cycle?->isOpenForOrdering() === true;
        $payload = $order->toArray();
        unset($payload['cycle']);

        return array_merge($payload, [
            'items_count' => $itemsCount,
            'total_price' => $order->total_price,
            'can_submit' => $order->status === OrderStatus::Draft
                && $isOpenForOrdering
                && $itemsCount > 0,
            'can_reopen_for_editing' => $order->status === OrderStatus::Submitted
                && $isOpenForOrdering,
            'status_label' => $this->orderStatusLabel($order->status),
        ]);
    }

    protected function orderHistoryPayload(Order $order, bool $canRepeat): array
    {
        $order->loadMissing(['cycle', 'items.menuItem']);

        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'status_label' => $this->orderStatusLabel($order->status),
            'submitted_at' => $order->submitted_at,
            'total_price' => $order->total_price,
            'items_count' => $order->items->count(),
            'cycle' => $order->cycle === null ? null : [
                'id' => $order->cycle->id,
                'title' => $order->cycle->title,
                'status' => $order->cycle->status->value,
                'status_label' => $this->orderCycleStatusLabel($order->cycle->status),
            ],
            'items' => $order->items
                ->map(fn (OrderItem $item) => $this->orderHistoryItemPayload($item))
                ->values(),
            'can_repeat' => $canRepeat && $order->status === OrderStatus::Submitted,
        ];
    }

    protected function orderHistoryItemPayload(OrderItem $item): array
    {
        $unitPrice = (float) $item->price_snapshot;
        $quantity = (int) $item->quantity;

        return [
            'id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'title' => $item->title_snapshot,
            'quantity' => $quantity,
            'unit_price' => $item->price_snapshot,
            'total_price' => number_format($unitPrice * $quantity, 2, '.', ''),
        ];
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
        $deadline = $this->deadlineForDisplay($cycle);

        if ($deadline === null) {
            return null;
        }

        $prefix = $cycle->isOpenForOrdering() ? 'Дедлайн' : 'Дедлайн был';

        return $prefix.' '.$deadline->format('Y-m-d H:i');
    }

    protected function deadlineDate(OrderCycle $cycle): ?string
    {
        return $this->deadlineForDisplay($cycle)?->format('d.m');
    }

    protected function deadlineTime(OrderCycle $cycle): ?string
    {
        return $this->deadlineForDisplay($cycle)?->format('H:i');
    }

    protected function deadlineDisplay(OrderCycle $cycle): ?string
    {
        return $this->deadlineForDisplay($cycle)?->format('d.m, H:i');
    }

    protected function deadlineDisplayFull(OrderCycle $cycle): ?string
    {
        return $this->deadlineForDisplay($cycle)?->format('d.m.Y, H:i');
    }

    protected function deadlineForDisplay(OrderCycle $cycle): ?CarbonInterface
    {
        if ($cycle->closes_at === null) {
            return null;
        }

        return $cycle->closes_at->copy()->setTimezone(
            config('lunch.business_timezone', config('app.timezone', 'UTC')),
        );
    }
}
