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
            return 'В холодильнике пока ничего нет.';
        }

        $lines = $items
            ->map(fn (FridgeItem $item) => "- {$item->title_snapshot}: осталось порций {$item->quantity_remaining}; ".
                "Годен до: {$this->expiryLabel($item)}; Статус: {$item->status->label()}.")
            ->all();

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, FridgeItem>  $items
     */
    public function formatHistory(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'Истории пока нет.';
        }

        $lines = $items
            ->map(fn (FridgeItem $item) => "- {$item->title_snapshot}: {$this->historyStatusLabel($item->status)}; ".
                "дата: {$this->actionDateLabel($item)}.")
            ->all();

        return implode("\n", $lines);
    }

    private function expiryLabel(FridgeItem $item): string
    {
        return $item->expires_at?->format('d.m.Y H:i') ?? 'не указан';
    }

    private function actionDateLabel(FridgeItem $item): string
    {
        $date = match ($item->status) {
            FridgeItemStatus::Eaten => $item->eaten_at,
            FridgeItemStatus::Discarded => $item->discarded_at,
            FridgeItemStatus::Expired => $item->expires_at ?? $item->updated_at,
            FridgeItemStatus::InFridge => $item->updated_at,
        };

        return $date?->format('d.m.Y H:i') ?? 'не указана';
    }

    private function historyStatusLabel(FridgeItemStatus $status): string
    {
        return match ($status) {
            FridgeItemStatus::Eaten => 'Съедено',
            FridgeItemStatus::Discarded, FridgeItemStatus::Expired => 'Списано',
            FridgeItemStatus::InFridge => 'В холодильнике',
        };
    }
}
