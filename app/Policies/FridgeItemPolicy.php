<?php

namespace App\Policies;

use App\Models\FridgeItem;
use App\Models\User;

class FridgeItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, FridgeItem $fridgeItem): bool
    {
        return $user->isAdmin() || $fridgeItem->user_id === $user->id;
    }

    public function update(User $user, FridgeItem $fridgeItem): bool
    {
        return $user->isAdmin() || $fridgeItem->user_id === $user->id;
    }
}

