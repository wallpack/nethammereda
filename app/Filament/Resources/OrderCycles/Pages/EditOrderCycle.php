<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\OrderCycles\Actions\MarkOrderCycleDeliveredAction;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderCycle extends EditRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderCycleResource::class;

    protected static ?string $title = 'Редактирование недельного цикла';

    protected static ?string $breadcrumb = 'Редактирование';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('supplierExports')
                ->label('Отправки поставщику')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->url(fn (): string => SupplierOrderExportResource::getUrl('index', [
                    'filters' => [
                        'order_cycle_id' => [
                            'value' => $this->getRecord()->getKey(),
                        ],
                    ],
                ]))
                ->visible(fn (): bool => $this->getRecord()->supplierOrderExports()->exists()),
            MarkOrderCycleDeliveredAction::make(),
            DeleteAction::make()
                ->label('Удалить'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['closes_at'] = $data['closes_at'] ?? $this->resolveFridayNoon($data['starts_at'] ?? null);

        return $data;
    }

    private function resolveFridayNoon(mixed $startsAt): string
    {
        $start = $startsAt !== null
            ? Carbon::parse($startsAt)
            : now();

        return $start
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->addDays(4)
            ->setTime(12, 0)
            ->toDateTimeString();
    }
}
