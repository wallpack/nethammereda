<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Support\AdminDashboard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WeeklyOrdersStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Заказы недели';

    protected ?string $description = 'Сводка по текущему недельному циклу.';

    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $cycle = AdminDashboard::currentCycle();

        if (! $cycle) {
            return [
                Stat::make('Пользователи', (string) $this->activeUsersCount())
                    ->description('Активные сотрудники')
                    ->color('gray'),
                Stat::make('Заказы', '0')
                    ->description('Создайте недельный цикл')
                    ->color('gray'),
            ];
        }

        $ordersQuery = Order::query()->where('order_cycle_id', $cycle->id);
        $draftOrders = (clone $ordersQuery)->where('status', OrderStatus::Draft->value)->count();
        $submittedOrders = (clone $ordersQuery)->where('status', OrderStatus::Submitted->value)->count();
        $orderedUsers = (clone $ordersQuery)->distinct()->count('user_id');
        $portions = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_cycle_id', $cycle->id)
            ->where('orders.status', OrderStatus::Submitted->value)
            ->sum('order_items.quantity');
        $total = (float) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_cycle_id', $cycle->id)
            ->where('orders.status', OrderStatus::Submitted->value)
            ->sum(DB::raw('order_items.price_snapshot * order_items.quantity'));

        return [
            Stat::make('Пользователи', (string) $this->activeUsersCount())
                ->description('Активные сотрудники')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('info'),
            Stat::make('Сделали заказ', (string) $orderedUsers)
                ->description($cycle->title)
                ->descriptionIcon('heroicon-m-shopping-bag', IconPosition::Before)
                ->color('success'),
            Stat::make('Черновики', (string) $draftOrders)
                ->description('Еще не отправлены')
                ->descriptionIcon('heroicon-m-pencil-square', IconPosition::Before)
                ->color($draftOrders > 0 ? 'warning' : 'gray'),
            Stat::make('Отправлено', (string) $submittedOrders)
                ->description('Отправленные заказы')
                ->descriptionIcon('heroicon-m-check-circle', IconPosition::Before)
                ->color('success'),
            Stat::make('Порции и сумма', $portions.' / '.AdminDashboard::money($total))
                ->description('Позиции в отправленных заказах')
                ->descriptionIcon('heroicon-m-calculator', IconPosition::Before)
                ->color('info'),
        ];
    }

    private function activeUsersCount(): int
    {
        return User::query()
            ->where('role', UserRole::User)
            ->where('is_active', true)
            ->count();
    }
}
