<?php

namespace App\Filament\Widgets;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FridgeOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Холодильник: обзор';

    protected function getStats(): array
    {
        $activeItemsQuery = FridgeItem::query()
            ->where('status', FridgeItemStatus::InFridge)
            ->where('quantity_remaining', '>', 0);

        $activeItems = (clone $activeItemsQuery)->count();
        $remainingPortions = (int) (clone $activeItemsQuery)->sum('quantity_remaining');
        $overdueInFridge = (clone $activeItemsQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();
        $expiredItems = FridgeItem::query()
            ->where('status', FridgeItemStatus::Expired)
            ->count();

        return [
            Stat::make('Активные позиции', (string) $activeItems)
                ->description('Текущие блюда в холодильниках'),
            Stat::make('Остаток порций', (string) $remainingPortions)
                ->description('Суммарный остаток по всем пользователям'),
            Stat::make('Просрочено (нужно списать)', (string) $overdueInFridge)
                ->description('Еще в холодильнике, но срок годности уже прошел'),
            Stat::make('Уже помечено просроченным', (string) $expiredItems)
                ->description('Исторически списанные просрочки'),
        ];
    }
}
