<?php

namespace App\Filament\Resources\MenuImports\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuImports\MenuImportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMenuImport extends ViewRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuImportResource::class;

    protected static ?string $title = 'Импорт меню';

    protected static ?string $breadcrumb = 'Просмотр';
}
