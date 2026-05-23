<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuCategories extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuCategoryResource::class;

    protected static ?string $title = 'Категории';

    protected static ?string $breadcrumb = 'Категории';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Создать категорию'),
        ];
    }
}
