<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderCannotBeSubmittedException;
use App\Exceptions\OrderCannotBeReopenedForEditingException;
use App\Exceptions\SubmittedOrderCannotBeChangedException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function getOrCreateOrder(User $user, OrderCycle $cycle): Order
    {
        if (! $cycle->isOpenForOrdering()) {
            throw OrderCannotBeSubmittedException::forUnavailableCycle();
        }

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

    public function addItemForUser(Order $order, MenuItem $menuItem, int $quantity): Order
    {
        return DB::transaction(function () use ($order, $menuItem, $quantity): Order {
            $order->refresh();
            $order->ensureCanBeChangedByUser();

            $item = OrderItem::query()->firstOrNew([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
            ]);

            if (! $item->exists) {
                $item->fill([
                    'title_snapshot' => $menuItem->title,
                    'supplier_name_snapshot' => $this->supplierNameSnapshotForMenuItem($menuItem),
                    'price_snapshot' => $menuItem->price,
                    'status' => OrderItemStatus::Ordered,
                ]);
            } else {
                $item->status = OrderItemStatus::Ordered;

                if (! filled($item->supplier_name_snapshot)) {
                    $item->supplier_name_snapshot = $this->supplierNameSnapshotForMenuItem($menuItem);
                }
            }

            $item->quantity = $item->exists ? $item->quantity + $quantity : $quantity;
            $item->save();

            $this->markAsDraft($order);
            $this->recalculate($order);

            return $order->fresh(['cycle', 'items.menuItem']);
        });
    }

    public function updateItemQuantityForUser(OrderItem $orderItem, int $quantity): Order
    {
        return DB::transaction(function () use ($orderItem, $quantity): Order {
            $orderItem->load('order');
            $order = $orderItem->order;
            abort_if($order === null, 404);

            $order->ensureCanBeChangedByUser();

            $orderItem->quantity = $quantity;
            $orderItem->save();

            $this->markAsDraft($order);
            $this->recalculate($order);

            return $order->fresh(['cycle', 'items.menuItem']);
        });
    }

    public function deleteItemForUser(OrderItem $orderItem): ?Order
    {
        return DB::transaction(function () use ($orderItem): ?Order {
            $orderItem->load('order');
            $order = $orderItem->order;

            if ($order === null) {
                $orderItem->delete();

                return null;
            }

            $order->ensureCanBeChangedByUser();
            $orderItem->delete();

            $this->markAsDraft($order);
            $this->recalculate($order);

            return $order->fresh(['cycle', 'items.menuItem']);
        });
    }

    public function submit(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order->refresh();

            if ($order->isSubmitted()) {
                return $order->fresh(['cycle', 'items.menuItem']);
            }

            if (! $order->isDraft()) {
                throw OrderCannotBeSubmittedException::forNonDraftOrder();
            }

            $order->loadMissing('cycle');

            if ($order->cycle === null || ! $order->cycle->isOpenForOrdering()) {
                throw OrderCannotBeSubmittedException::forUnavailableCycle();
            }

            $hasItems = $order->items()
                ->where('status', '!=', OrderItemStatus::Cancelled->value)
                ->exists();

            if (! $hasItems) {
                throw OrderCannotBeSubmittedException::forEmptyOrder();
            }

            $this->recalculate($order);
            $order->status = OrderStatus::Submitted;
            $order->submitted_at = now();
            $order->save();

            return $order->fresh(['cycle', 'items.menuItem']);
        });
    }

    public function reopenForUserEditing(Order $order, User $user): Order
    {
        return DB::transaction(function () use ($order, $user): Order {
            $order->refresh();
            $order->loadMissing('cycle');

            if ((int) $order->user_id !== (int) $user->id) {
                throw new AuthorizationException('This order does not belong to the current user.');
            }

            if (! $order->isSubmitted()) {
                throw OrderCannotBeReopenedForEditingException::forNonSubmittedOrder();
            }

            if ($order->cycle === null || ! $order->cycle->isOpenForOrdering()) {
                throw OrderCannotBeReopenedForEditingException::forUnavailableCycle();
            }

            $order->status = OrderStatus::Draft;
            $order->submitted_at = null;
            $order->save();

            return $order->fresh(['cycle', 'items.menuItem']);
        });
    }

    public function markAsDraft(Order $order): void
    {
        $order->ensureCanBeChangedByUser();

        $order->status = OrderStatus::Draft;
        $order->submitted_at = null;
        $order->save();
    }

    public function recalculate(Order $order): void
    {
        $order->recalculateTotal();
    }

    /**
     * @return array{
     *   order: Order,
     *   added_items_count: int,
     *   skipped_items: array<int, string>,
     *   warning: string|null
     * }
     */
    public function repeatSubmittedOrderForUser(
        Order $sourceOrder,
        User $user,
        OrderCycle $cycle,
        string $mode = 'replace',
    ): array {
        return DB::transaction(function () use ($sourceOrder, $user, $cycle, $mode): array {
            $sourceOrder->refresh();
            $sourceOrder->loadMissing('items');

            if ((int) $sourceOrder->user_id !== (int) $user->id) {
                throw new AuthorizationException('This order does not belong to the current user.');
            }

            if (! $sourceOrder->isSubmitted()) {
                throw OrderCannotBeReopenedForEditingException::forNonSubmittedOrder();
            }

            $skippedItems = [];
            $limitedItems = [];
            $resolvedItems = [];

            foreach ($sourceOrder->items as $sourceItem) {
                if ($sourceItem->status === OrderItemStatus::Cancelled) {
                    continue;
                }

                $menuItem = MenuItem::query()
                    ->where('id', $sourceItem->menu_item_id)
                    ->where('is_active', true)
                    ->first();

                if ($menuItem === null) {
                    $skippedItems[] = $sourceItem->title_snapshot;
                    continue;
                }

                $requestedQuantity = max(1, (int) $sourceItem->quantity);
                $quantity = min($requestedQuantity, 20);

                if ($quantity < $requestedQuantity) {
                    $limitedItems[] = $menuItem->title;
                }

                $resolvedItems[] = [
                    'menuItem' => $menuItem,
                    'quantity' => $quantity,
                ];
            }

            if ($resolvedItems === []) {
                return [
                    'order' => Order::query()
                        ->with(['cycle', 'items.menuItem'])
                        ->where('user_id', $user->id)
                        ->where('order_cycle_id', $cycle->id)
                        ->first(),
                    'added_items_count' => 0,
                    'skipped_items' => $skippedItems,
                    'warning' => null,
                ];
            }

            $targetOrder = $this->getOrCreateOrder($user, $cycle);
            $targetOrder->refresh();
            $targetOrder->loadMissing('items');

            if ($targetOrder->isSubmitted()) {
                throw SubmittedOrderCannotBeChangedException::forOrder($targetOrder);
            }

            if ($mode === 'replace') {
                $targetOrder->items()->delete();
            }

            foreach ($resolvedItems as $resolvedItem) {
                /** @var MenuItem $menuItem */
                $menuItem = $resolvedItem['menuItem'];
                $quantity = (int) $resolvedItem['quantity'];

                OrderItem::query()->updateOrCreate(
                    [
                        'order_id' => $targetOrder->id,
                        'menu_item_id' => $menuItem->id,
                    ],
                    [
                        'title_snapshot' => $menuItem->title,
                        'supplier_name_snapshot' => $this->supplierNameSnapshotForMenuItem($menuItem),
                        'price_snapshot' => $menuItem->price,
                        'quantity' => $quantity,
                        'status' => OrderItemStatus::Ordered,
                    ],
                );
            }

            $this->markAsDraft($targetOrder);
            $this->recalculate($targetOrder);

            $warnings = [];
            if ($skippedItems !== []) {
                $warnings[] = 'Некоторые блюда сейчас недоступны.';
            }
            if ($limitedItems !== []) {
                $warnings[] = 'Количество некоторых блюд было ограничено.';
            }

            return [
                'order' => $targetOrder->fresh(['cycle', 'items.menuItem']),
                'added_items_count' => count($resolvedItems),
                'skipped_items' => array_values(array_unique($skippedItems)),
                'warning' => $warnings === [] ? null : implode(' ', $warnings),
            ];
        });
    }

    private function supplierNameSnapshotForMenuItem(MenuItem $menuItem): string
    {
        $supplierName = is_string($menuItem->supplier_name ?? null)
            ? trim($menuItem->supplier_name)
            : '';
        $title = trim((string) $menuItem->title);

        return $supplierName !== '' ? $supplierName : $title;
    }
}
