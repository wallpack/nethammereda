<?php

namespace App\Filament\Resources\MenuImports\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuImports\Actions\UploadMenuImportAction;
use App\Filament\Resources\MenuImports\MenuImportResource;
use Filament\Resources\Pages\ListRecords;

class ListMenuImports extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuImportResource::class;

    protected static ?string $title = 'Импорт меню';

    protected function getHeaderActions(): array
    {
        return [
            UploadMenuImportAction::make(),
        ];
    }
}
