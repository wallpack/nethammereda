<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Редактирование заказа';

    protected static ?string $breadcrumb = 'Редактирование';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Удалить'),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotal();
    }
}
