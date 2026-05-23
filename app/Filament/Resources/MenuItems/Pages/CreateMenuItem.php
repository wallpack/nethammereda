<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuItemResource::class;

    protected static ?string $title = 'Создание блюда';

    protected static ?string $breadcrumb = 'Создание';
}
