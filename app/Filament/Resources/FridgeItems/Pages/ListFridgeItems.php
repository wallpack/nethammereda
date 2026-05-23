<?php

namespace App\Filament\Resources\FridgeItems\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\FridgeItems\FridgeItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFridgeItems extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = FridgeItemResource::class;

    protected static ?string $title = 'Позиции холодильника';

    protected static ?string $breadcrumb = 'Позиции холодильника';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Добавить запись'),
        ];
    }
}
