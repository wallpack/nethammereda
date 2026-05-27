<?php

namespace App\Filament\Resources\SupplierOrderExports\Actions;

use App\Models\SupplierOrderExport;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Action;

class DownloadSupplierOrderExportXlsxAction
{
    public static function make(string $name = 'downloadXlsx'): Action
    {
        return Action::make($name)
            ->label('Скачать XLSX')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (SupplierOrderExport $record) {
                $filename = "supplier-order-export-{$record->id}.xlsx";

                return response()->streamDownload(
                    function () use ($record): void {
                        echo app(SupplierOrderExportService::class)->xlsxForExport($record);
                    },
                    $filename,
                    ['Content-Type' => SupplierOrderExportService::xlsxMimeType()],
                );
            });
    }
}
