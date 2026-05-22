<?php

namespace App\Services;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;

class FridgeExpiryService
{
    public function expireDueItems(): int
    {
        return FridgeItem::query()
            ->where('status', FridgeItemStatus::InFridge)
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => FridgeItemStatus::Expired,
                'updated_at' => now(),
            ]);
    }
}

