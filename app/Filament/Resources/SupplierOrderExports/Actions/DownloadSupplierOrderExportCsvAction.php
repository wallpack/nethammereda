<?php

namespace App\Filament\Resources\SupplierOrderExports\Actions;

use App\Models\SupplierOrderExport;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Action;

class DownloadSupplierOrderExportCsvAction
{
    public static function make(string $name = 'downloadCsv'): Action
    {
        return Action::make($name)
            ->label('Скачать CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (SupplierOrderExport $record) {
                $filename = "supplier-order-export-{$record->id}.csv";

                return response()->streamDownload(
                    function () use ($record): void {
                        echo app(SupplierOrderExportService::class)->csvForExport($record);
                    },
                    $filename,
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                );
            });
    }
}
