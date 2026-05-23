<?php

namespace App\Filament\Widgets;

use App\Enums\OrderCycleStatus;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
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

        return [
            'cycle' => $cycle,
            'period' => AdminDashboard::cyclePeriod($cycle),
            'deadline' => AdminDashboard::formatDateTime($cycle?->closes_at),
            'timeLeft' => AdminDashboard::timeUntilDeadline($cycle),
            'nextStep' => AdminDashboard::nextStep($cycle),
            'statusLabel' => $cycle?->status->label() ?? 'Нет цикла',
            'statusColor' => $this->statusColor($cycle),
            'cycleUrl' => $cycle ? OrderCycleResource::getUrl('edit', ['record' => $cycle]) : OrderCycleResource::getUrl('create'),
            'deliveryPending' => $cycle?->status === OrderCycleStatus::SentToSupplier,
        ];
    }

    private function statusColor(?OrderCycle $cycle): string
    {
        if (! $cycle) {
            return 'gray';
        }

        return $cycle->status->color();
    }
}
