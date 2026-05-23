<?php

namespace App\Filament\Resources\SupplierOrderExports\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\SupplierOrderExports\Actions\DownloadSupplierOrderExportCsvAction;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierOrderExport extends ViewRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = SupplierOrderExportResource::class;

    protected static ?string $title = 'Отправка поставщику';

    protected static ?string $breadcrumb = 'Просмотр';

    protected function getHeaderActions(): array
    {
        return [
            DownloadSupplierOrderExportCsvAction::make(),
        ];
    }
}
