<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuCategory extends EditRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuCategoryResource::class;

    protected static ?string $title = 'Редактирование категории';

    protected static ?string $breadcrumb = 'Редактирование';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
