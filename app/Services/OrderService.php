<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderCannotBeSubmittedException;
use App\Exceptions\OrderCannotBeReopenedForEditingException;
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

            $item->fill([
                'title_snapshot' => $menuItem->title,
                'price_snapshot' => $menuItem->price,
                'status' => OrderItemStatus::Ordered,
            ]);

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
}
