<?php

namespace App\Filament\Widgets;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FridgeOverviewStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Холодильник';

    protected ?string $description = 'Что сейчас лежит у пользователей и что требует внимания.';

    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        $activeItemsQuery = FridgeItem::query()
            ->where('status', FridgeItemStatus::InFridge->value)
            ->where('quantity_remaining', '>', 0);

        $activeItems = (clone $activeItemsQuery)->count();
        $remainingPortions = (int) (clone $activeItemsQuery)->sum('quantity_remaining');
        $expiringSoon = (clone $activeItemsQuery)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(2)])
            ->count();
        $overdue = (clone $activeItemsQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        return [
            Stat::make('Активные блюда', (string) $activeItems)
                ->description('Доступны сейчас')
                ->descriptionIcon('heroicon-m-archive-box', IconPosition::Before)
                ->color('success'),
            Stat::make('Активные порции', (string) $remainingPortions)
                ->description('Суммарный остаток')
                ->descriptionIcon('heroicon-m-square-3-stack-3d', IconPosition::Before)
                ->color('info'),
            Stat::make('Скоро истекает срок', (string) $expiringSoon)
                ->description('В ближайшие 48 часов')
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->color($expiringSoon > 0 ? 'warning' : 'gray'),
            Stat::make('Просрочено к списанию', (string) $overdue)
                ->description('Еще числится в холодильнике')
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->color($overdue > 0 ? 'danger' : 'gray'),
        ];
    }
}
