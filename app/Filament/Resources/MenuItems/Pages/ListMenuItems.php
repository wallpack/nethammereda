<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuItemResource::class;

    protected static ?string $title = 'Блюда';

    protected static ?string $breadcrumb = 'Блюда';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Добавить блюдо'),
        ];
    }
}
