<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, OrderItem $orderItem): bool
    {
        return $user->isAdmin() || $orderItem->order?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $order = $orderItem->order;

        return $order !== null
            && $order->user_id === $user->id
            && $order->cycle !== null
            && $order->cycle->isOpenForOrdering();
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return $this->update($user, $orderItem);
    }

    public function restore(User $user, OrderItem $orderItem): bool
    {
        return false;
    }

    public function forceDelete(User $user, OrderItem $orderItem): bool
    {
        return false;
    }

    public function markReceived(User $user, OrderItem $orderItem): bool
    {
        return $user->isAdmin();
    }

    public function markEaten(User $user, OrderItem $orderItem): bool
    {
        return $user->isAdmin();
    }
}
