<?php

namespace App\Services;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Illuminate\Support\Collection;

class FridgeSummaryFormatter
{
    /**
     * @param  Collection<int, FridgeItem>  $items
     */
    public function formatActive(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'Ваш холодильник пока пуст.';
        }

        $lines = $items
            ->map(fn (FridgeItem $item) => "- {$item->title_snapshot} (остаток: {$item->quantity_remaining}/{$item->quantity_total})")
            ->all();

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, FridgeItem>  $items
     */
    public function formatHistory(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'История пока пустая.';
        }

        $lines = $items
            ->map(fn (FridgeItem $item) => "- {$item->title_snapshot} ({$this->statusLabel($item->status)})")
            ->all();

        return implode("\n", $lines);
    }

    private function statusLabel(FridgeItemStatus $status): string
    {
        return match ($status) {
            FridgeItemStatus::InFridge => 'в холодильнике',
            FridgeItemStatus::Eaten => 'съедено',
            FridgeItemStatus::Discarded => 'выброшено',
            FridgeItemStatus::Expired => 'просрочено',
        };
    }
}

