<?php

namespace App\Filament\Resources\OrderCycles\Tables;

use App\Enums\OrderCycleStatus;
use App\Exceptions\SupplierOrderCannotBeSentException;
use App\Filament\Resources\OrderCycles\Actions\MarkOrderCycleDeliveredAction;
use App\Filament\Resources\OrderCycles\Actions\ReopenOrderCycleAction;
use App\Filament\Support\AdminDashboard;
use App\Models\OrderCycle;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));

        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Неделя')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (OrderCycleStatus $state, OrderCycle $record): string => AdminDashboard::cycleStatusLabel($record))
                    ->badge()
                    ->color(fn (OrderCycleStatus $state, OrderCycle $record): string => AdminDashboard::cycleStatusColor($record))
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Старт')
                    ->dateTime('d.m.Y, H:i', $businessTimezone)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closes_at')
                    ->label('Дедлайн заказа')
                    ->dateTime('d.m.Y, H:i', $businessTimezone)
                    ->sortable(),
                TextColumn::make('sent_to_supplier_at')
                    ->label('Отправка поставщику')
                    ->dateTime('d.m.Y, H:i', $businessTimezone)
                    ->placeholder('Не отправлен')
                    ->sortable(),
                TextColumn::make('sentToSupplierBy.name')
                    ->label('Кто отправил')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label('Доставка')
                    ->dateTime('d.m.Y, H:i', $businessTimezone)
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
                    ->alignEnd()
                    ->sortable(),
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
                Action::make('sendToSupplier')
                    ->label('Отправить')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Отправить поставщику')
                    ->modalDescription('Будет создан снимок итогового заказа и цикл получит статус «Отправлен поставщику». CSV можно скачать отдельным действием.')
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
                EditAction::make()
                    ->label('Открыть')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                ActionGroup::make([
                    ReopenOrderCycleAction::make(),
                    Action::make('exportCsv')
                        ->label('Скачать CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (OrderCycle $record) {
                            $csv = app(SupplierOrderExportService::class)->csvForCycle($record);

                            $filename = "supplier-order-cycle-{$record->id}.csv";

                            return response()->streamDownload(
                                function () use ($csv): void {
                                    echo $csv;
                                },
                                $filename,
                                ['Content-Type' => 'text/csv; charset=UTF-8'],
                            );
                        }),
                    Action::make('exportXlsx')
                        ->label('Скачать XLSX')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (OrderCycle $record) {
                            $xlsx = app(SupplierOrderExportService::class)->xlsxForCycle($record);

                            $filename = "supplier-order-cycle-{$record->id}.xlsx";

                            return response()->streamDownload(
                                function () use ($xlsx): void {
                                    echo $xlsx;
                                },
                                $filename,
                                ['Content-Type' => SupplierOrderExportService::xlsxMimeType()],
                            );
                        }),
                    DeleteAction::make()
                        ->label('Удалить'),
                ])
                    ->label('Еще')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray'),
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
}
