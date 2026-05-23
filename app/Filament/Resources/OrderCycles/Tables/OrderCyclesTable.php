<?php

namespace App\Filament\Resources\OrderCycles\Tables;

use App\Enums\OrderCycleStatus;
use App\Exceptions\SupplierOrderCannotBeSentException;
use App\Filament\Resources\OrderCycles\Actions\MarkOrderCycleDeliveredAction;
use App\Models\OrderCycle;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                    ->formatStateUsing(fn (OrderCycleStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (OrderCycleStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Старт')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closes_at')
                    ->label('Дедлайн')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_to_supplier_at')
                    ->label('Дата отправки')
                    ->dateTime()
                    ->placeholder('Не отправлен')
                    ->sortable(),
                TextColumn::make('sentToSupplierBy.name')
                    ->label('Кто отправил')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label('Дата доставки')
                    ->dateTime()
                    ->placeholder('Не отмечена')
                    ->sortable(),
                TextColumn::make('deliveredBy.name')
                    ->label('Кто отметил доставку')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Заказов')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_total')
                    ->label('Сумма для поставщика')
                    ->state(fn (OrderCycle $record): string => number_format(
                        app(SupplierOrderExportService::class)->totalForCycle($record),
                        2,
                        '.',
                        ' ',
                    ).' ₽')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderCycleStatus::labels()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Изменить'),
                Action::make('sendToSupplier')
                    ->label('Отправить поставщику')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Отправить поставщику')
                    ->modalDescription('Будет создан snapshot итогового заказа и цикл получит статус «Отправлен поставщику». CSV можно скачать отдельным действием.')
                    ->modalSubmitActionLabel('Отправить')
                    ->visible(fn (OrderCycle $record): bool => $record->status === OrderCycleStatus::Closed)
                    ->action(function (OrderCycle $record): void {
                        try {
                            $user = auth()->user();
                            $export = app(SupplierOrderExportService::class)->sendToSupplier(
                                $record,
                                $user instanceof User ? $user : null,
                            );
                        } catch (SupplierOrderCannotBeSentException $exception) {
                            Notification::make()
                                ->title('Не удалось отправить поставщику')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Отправлен поставщику')
                            ->body("Зафиксировано строк: {$export->rows_count}, порций: {$export->total_quantity}.")
                            ->success()
                            ->send();
                    }),
                MarkOrderCycleDeliveredAction::make(),
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
                                        self::escapeCsvCell((string) $row->title_snapshot),
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
                DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранное'),
                ]),
            ])
            ->emptyStateHeading('Недельных циклов пока нет')
            ->emptyStateDescription('Создайте цикл, чтобы открыть сбор заказов на неделю.');
    }

    private static function escapeCsvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) === 1
            ? "'{$value}"
            : $value;
    }
}
