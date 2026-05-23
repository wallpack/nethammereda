<?php

namespace App\Filament\Resources\OrderCycles\Tables;

use App\Models\OrderCycle;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderCyclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Неделя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (mixed $state): string => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'draft' => 'Черновик',
                        'open' => 'Открыт',
                        'closed' => 'Закрыт',
                        'sent_to_supplier' => 'Отправлен поставщику',
                        'delivered' => 'Доставлен',
                        'archived' => 'Архив',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Старт')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closes_at')
                    ->label('Дедлайн')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Заказов'),
                TextColumn::make('supplier_total')
                    ->label('Сумма для поставщика')
                    ->state(fn (OrderCycle $record): string => number_format(
                        app(SupplierOrderExportService::class)->totalForCycle($record),
                        2,
                        '.',
                        ' ',
                    ).' ₽'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'draft' => 'Черновик',
                        'open' => 'Открыт',
                        'closed' => 'Закрыт',
                        'sent_to_supplier' => 'Отправлен поставщику',
                        'delivered' => 'Доставлен',
                        'archived' => 'Архив',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('exportCsv')
                    ->label('Экспорт CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (OrderCycle $record) {
                        $rows = app(SupplierOrderExportService::class)->rowsForCycle($record);

                        $filename = "supplier-order-cycle-{$record->id}.csv";

                        return response()->streamDownload(
                            function () use ($rows): void {
                                $handle = fopen('php://output', 'wb');
                                $handle && fwrite($handle, "\xEF\xBB\xBF");
                                fputcsv($handle, ['Блюдо', 'Количество', 'Сумма'], ';');
                                foreach ($rows as $row) {
                                    fputcsv($handle, [
                                        $row->title_snapshot,
                                        (int) $row->quantity_sum,
                                        number_format((float) $row->total_sum, 2, '.', ''),
                                    ], ';');
                                }
                                fclose($handle);
                            },
                            $filename,
                            ['Content-Type' => 'text/csv; charset=UTF-8'],
                        );
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
