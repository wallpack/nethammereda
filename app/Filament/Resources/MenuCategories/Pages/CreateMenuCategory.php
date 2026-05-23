<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuCategory extends CreateRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = MenuCategoryResource::class;

    protected static ?string $title = 'Создание категории';

    protected static ?string $breadcrumb = 'Создание';
}
