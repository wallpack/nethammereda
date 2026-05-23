<?php

namespace App\Filament\Resources\SupplierOrderExports\Tables;

use App\Filament\Resources\SupplierOrderExports\Actions\DownloadSupplierOrderExportCsvAction;
use App\Models\SupplierOrderExport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierOrderExportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('exported_at', 'desc')
            ->columns([
                TextColumn::make('orderCycle.title')
                    ->label('Недельный цикл')
                    ->state(fn (SupplierOrderExport $record): string => $record->cycleTitle())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exportedBy.name')
                    ->label('Кто отправил')
                    ->placeholder('Не указано')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exported_at')
                    ->label('Дата отправки')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('rows_count')
                    ->label('Строк')
                    ->state(fn (SupplierOrderExport $record): int => $record->rowsCount())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_quantity')
                    ->label('Порций')
                    ->state(fn (SupplierOrderExport $record): int => $record->totalQuantity())
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Итоговая сумма')
                    ->state(fn (SupplierOrderExport $record): string => number_format($record->totalPrice(), 2, ',', ' ').' ₽')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('format')
                    ->label('Формат')
                    ->formatStateUsing(fn (?string $state): string => strtoupper((string) ($state ?? 'csv')))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('order_cycle_id')
                    ->relationship('orderCycle', 'title')
                    ->label('Недельный цикл'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Открыть'),
                DownloadSupplierOrderExportCsvAction::make(),
            ])
            ->emptyStateHeading('Отправок поставщику пока нет')
            ->emptyStateDescription('История появится здесь после действия «Отправить поставщику» в недельном цикле.');
    }
}
