<?php

namespace App\Filament\Resources\FridgeItems\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\FridgeItems\FridgeItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFridgeItem extends EditRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = FridgeItemResource::class;

    protected static ?string $title = 'Редактирование позиции холодильника';

    protected static ?string $breadcrumb = 'Редактирование';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
