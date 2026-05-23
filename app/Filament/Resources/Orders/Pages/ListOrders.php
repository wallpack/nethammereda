<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Заказы';

    protected static ?string $breadcrumb = 'Заказы';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
