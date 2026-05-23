<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CurrentOrderCycleWidget;
use App\Filament\Widgets\FridgeOverviewStats;
use App\Filament\Widgets\RecentAdminActivityWidget;
use App\Filament\Widgets\SupplierStatusWidget;
use App\Filament\Widgets\WeeklyOrdersStats;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Обзор';

    protected static ?string $title = 'Панель управления';

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 6,
            'lg' => 6,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            CurrentOrderCycleWidget::class,
            SupplierStatusWidget::class,
            WeeklyOrdersStats::class,
            FridgeOverviewStats::class,
            RecentAdminActivityWidget::class,
        ];
    }
}
