<?php

namespace App\Filament\Widgets;

use App\Enums\OrderCycleStatus;
use App\Filament\Resources\FridgeItems\FridgeItemResource;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use App\Filament\Support\AdminDashboard;
use App\Models\OrderCycle;
use Filament\Widgets\Widget;

class CurrentOrderCycleWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = [
        'md' => 6,
        'xl' => 7,
    ];

    protected string $view = 'filament.widgets.current-order-cycle-widget';

    protected function getViewData(): array
    {
        $cycle = AdminDashboard::currentCycle();

        $primaryAction = $this->primaryAction($cycle);

        return [
            'cycle' => $cycle,
            'period' => AdminDashboard::cyclePeriod($cycle),
            'deadline' => AdminDashboard::formatDateTime($cycle?->closes_at),
            'timeLeft' => AdminDashboard::timeUntilDeadline($cycle),
            'nextStep' => AdminDashboard::nextStep($cycle),
            'statusLabel' => $cycle?->status->label() ?? 'Нет цикла',
            'statusColor' => $this->statusColor($cycle),
            'cycleUrl' => $cycle ? OrderCycleResource::getUrl('edit', ['record' => $cycle]) : OrderCycleResource::getUrl('create'),
            'cyclesUrl' => OrderCycleResource::getUrl('index'),
            'ordersUrl' => $cycle
                ? OrderResource::getUrl('index', ['filters' => ['order_cycle_id' => ['value' => $cycle->getKey()]]])
                : OrderResource::getUrl('index'),
            'supplierExportsUrl' => SupplierOrderExportResource::getUrl('index'),
            'primaryActionLabel' => $primaryAction['label'],
            'primaryActionUrl' => $primaryAction['url'],
            'primaryActionIcon' => $primaryAction['icon'],
            'primaryActionColor' => $primaryAction['color'],
            'deliveryPending' => $cycle?->status === OrderCycleStatus::SentToSupplier,
        ];
    }

    /**
     * @return array{label: string, url: string, icon: string, color: string}
     */
    private function primaryAction(?OrderCycle $cycle): array
    {
        if (! $cycle) {
            return [
                'label' => 'Создать цикл',
                'url' => OrderCycleResource::getUrl('create'),
                'icon' => 'heroicon-m-plus',
                'color' => 'primary',
            ];
        }

        return match ($cycle->status) {
            OrderCycleStatus::Draft => [
                'label' => 'Открыть цикл',
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
                'icon' => 'heroicon-m-lock-open',
                'color' => 'primary',
            ],
            OrderCycleStatus::Open => [
                'label' => 'Закрыть заказ',
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
                'icon' => 'heroicon-m-lock-closed',
                'color' => 'warning',
            ],
            OrderCycleStatus::Closed => [
                'label' => 'Отправить поставщику',
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
                'icon' => 'heroicon-m-paper-airplane',
                'color' => 'success',
            ],
            OrderCycleStatus::SentToSupplier => [
                'label' => 'Отметить доставку',
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
                'icon' => 'heroicon-m-check-circle',
                'color' => 'success',
            ],
            OrderCycleStatus::Delivered => [
                'label' => 'Открыть холодильник',
                'url' => FridgeItemResource::getUrl('index'),
                'icon' => 'heroicon-m-archive-box',
                'color' => 'info',
            ],
            OrderCycleStatus::Archived => [
                'label' => 'История циклов',
                'url' => OrderCycleResource::getUrl('index'),
                'icon' => 'heroicon-m-calendar-days',
                'color' => 'gray',
            ],
        };
    }

    private function statusColor(?OrderCycle $cycle): string
    {
        if (! $cycle) {
            return 'gray';
        }

        return $cycle->status->color();
    }
}
