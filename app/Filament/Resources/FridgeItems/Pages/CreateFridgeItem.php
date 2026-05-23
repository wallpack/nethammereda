<?php

namespace App\Filament\Resources\FridgeItems\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\FridgeItems\FridgeItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFridgeItem extends CreateRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = FridgeItemResource::class;

    protected static ?string $title = 'Создание позиции холодильника';

    protected static ?string $breadcrumb = 'Создание';
}
