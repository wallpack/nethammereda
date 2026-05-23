<?php

namespace App\Filament\Widgets;

use App\Enums\OrderCycleStatus;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use App\Filament\Support\AdminDashboard;
use App\Models\SupplierOrderExport;
use Filament\Widgets\Widget;

class SupplierStatusWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'md' => 6,
        'xl' => 5,
    ];

    protected string $view = 'filament.widgets.supplier-status-widget';

    protected function getViewData(): array
    {
        $cycle = AdminDashboard::currentCycle();
        $export = $cycle
            ? SupplierOrderExport::query()
                ->with('exportedBy')
                ->where('order_cycle_id', $cycle->id)
                ->latest('exported_at')
                ->first()
            : null;

        return [
            'cycle' => $cycle,
            'status' => AdminDashboard::supplierStatus($cycle),
            'sentAt' => AdminDashboard::formatDateTime($cycle?->sent_to_supplier_at),
            'sentBy' => $cycle?->sentToSupplierBy?->name ?? 'Не указано',
            'rowsCount' => $export?->rows_count ?? 0,
            'totalQuantity' => $export?->total_quantity ?? 0,
            'totalPrice' => AdminDashboard::money($export?->total_price),
            'deliveryPending' => $cycle?->status === OrderCycleStatus::SentToSupplier,
            'cycleUrl' => $cycle ? OrderCycleResource::getUrl('edit', ['record' => $cycle]) : OrderCycleResource::getUrl('index'),
            'historyUrl' => SupplierOrderExportResource::getUrl('index'),
            'lastExportUrl' => $export ? SupplierOrderExportResource::getUrl('view', ['record' => $export]) : null,
        ];
    }
}
