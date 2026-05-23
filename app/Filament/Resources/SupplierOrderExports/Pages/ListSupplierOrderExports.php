<?php

namespace App\Filament\Resources\SupplierOrderExports\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use Filament\Resources\Pages\ListRecords;

class ListSupplierOrderExports extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = SupplierOrderExportResource::class;

    protected static ?string $title = 'Отправки поставщику';
}
