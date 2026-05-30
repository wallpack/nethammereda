<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderCycles\OrderCycleResource;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use App\Filament\Support\AdminDashboard;
use App\Models\OrderCycle;
use App\Models\SupplierOrderExport;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class RecentAdminActivityWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-admin-activity-widget';

    protected function getViewData(): array
    {
        return [
            'activities' => $this->activities(),
        ];
    }

    private function activities(): Collection
    {
        $exports = SupplierOrderExport::query()
            ->with(['orderCycle', 'exportedBy'])
            ->latest('exported_at')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (SupplierOrderExport $export): array => [
                'kind' => 'Отправка',
                'tone' => 'info',
                'title' => $export->orderCycle?->title ?? 'Цикл удален',
                'description' => "{$export->rows_count} строк, {$export->total_quantity} порций, ".AdminDashboard::money($export->total_price),
                'actor' => $export->exportedBy?->name ?? 'Администратор не указан',
                'happened_at' => $export->exported_at,
                'url' => SupplierOrderExportResource::getUrl('view', ['record' => $export]),
            ]);

        $deliveries = OrderCycle::query()
            ->with('deliveredBy')
            ->whereNotNull('delivered_at')
            ->latest('delivered_at')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (OrderCycle $cycle): array => [
                'kind' => 'Доставка',
                'tone' => 'success',
                'title' => $cycle->title,
                'description' => 'Блюда синхронизированы с холодильниками',
                'actor' => $cycle->deliveredBy?->name ?? 'Администратор не указан',
                'happened_at' => $cycle->delivered_at,
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
            ]);

        $cycles = OrderCycle::query()
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->toBase()
            ->map(fn (OrderCycle $cycle): array => [
                'kind' => 'Цикл',
                'tone' => $cycle->status->color(),
                'title' => $cycle->title,
                'description' => 'Статус: '.$cycle->status->label(),
                'actor' => 'Система',
                'happened_at' => $cycle->updated_at,
                'url' => OrderCycleResource::getUrl('edit', ['record' => $cycle]),
            ]);

        return $exports
            ->merge($deliveries)
            ->merge($cycles)
            ->filter(fn (array $activity): bool => $activity['happened_at'] !== null)
            ->sortByDesc('happened_at')
            ->take(8)
            ->values();
    }
}
