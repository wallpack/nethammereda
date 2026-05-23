<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Создание заказа';

    protected static ?string $breadcrumb = 'Создание';
}
