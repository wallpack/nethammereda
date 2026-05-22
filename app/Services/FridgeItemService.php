<?php

namespace App\Services;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Illuminate\Support\Facades\DB;

class FridgeItemService
{
    public function eatOne(FridgeItem $fridgeItem): FridgeItem
    {
        return DB::transaction(function () use ($fridgeItem): FridgeItem {
            $fridgeItem->refresh();

            if ($fridgeItem->status !== FridgeItemStatus::InFridge || $fridgeItem->quantity_remaining <= 0) {
                return $fridgeItem;
            }

            $remaining = max(0, $fridgeItem->quantity_remaining - 1);
            $fridgeItem->quantity_remaining = $remaining;

            if ($remaining === 0) {
                $fridgeItem->status = FridgeItemStatus::Eaten;
                $fridgeItem->eaten_at = now();
            }

            $fridgeItem->save();

            return $fridgeItem->fresh();
        });
    }

    public function eatAll(FridgeItem $fridgeItem): FridgeItem
    {
        return DB::transaction(function () use ($fridgeItem): FridgeItem {
            $fridgeItem->refresh();

            if ($fridgeItem->status !== FridgeItemStatus::InFridge) {
                return $fridgeItem;
            }

            $fridgeItem->quantity_remaining = 0;
            $fridgeItem->status = FridgeItemStatus::Eaten;
            $fridgeItem->eaten_at = now();
            $fridgeItem->save();

            return $fridgeItem->fresh();
        });
    }

    public function discard(FridgeItem $fridgeItem): FridgeItem
    {
        return DB::transaction(function () use ($fridgeItem): FridgeItem {
            $fridgeItem->refresh();

            if ($fridgeItem->status !== FridgeItemStatus::InFridge) {
                return $fridgeItem;
            }

            $fridgeItem->quantity_remaining = 0;
            $fridgeItem->status = FridgeItemStatus::Discarded;
            $fridgeItem->discarded_at = now();
            $fridgeItem->save();

            return $fridgeItem->fresh();
        });
    }
}
