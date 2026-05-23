<?php

namespace App\Filament\Resources\SupplierOrderExports\Schemas;

use App\Models\SupplierOrderExport;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierOrderExportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сведения об отправке')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 4,
                    ])
                    ->schema([
                        TextEntry::make('cycle_title')
                            ->label('Недельный цикл')
                            ->state(fn (SupplierOrderExport $record): string => $record->cycleTitle()),
                        TextEntry::make('exportedBy.name')
                            ->label('Кто отправил')
                            ->placeholder('Не указано'),
                        TextEntry::make('exported_at')
                            ->label('Дата отправки')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('format')
                            ->label('Формат')
                            ->formatStateUsing(fn (?string $state): string => strtoupper((string) ($state ?? 'csv')))
                            ->badge()
                            ->color('gray'),
                    ]),
                Section::make('Итоги')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        TextEntry::make('rows_total')
                            ->label('Строк')
                            ->state(fn (SupplierOrderExport $record): int => $record->rowsCount()),
                        TextEntry::make('quantity_total')
                            ->label('Порций')
                            ->state(fn (SupplierOrderExport $record): int => $record->totalQuantity()),
                        TextEntry::make('price_total')
                            ->label('Итого')
                            ->state(fn (SupplierOrderExport $record): string => self::formatMoney($record->totalPrice())),
                    ]),
                Section::make('Снимок отправки')
                    ->columnSpanFull()
                    ->description('Агрегированные строки на момент отправки поставщику.')
                    ->schema([
                        RepeatableEntry::make('snapshot_rows')
                            ->label('Строки отправки')
                            ->state(fn (SupplierOrderExport $record): array => $record->snapshotRows())
                            ->table([
                                TableColumn::make('Блюдо'),
                                TableColumn::make('Категория'),
                                TableColumn::make('Количество'),
                                TableColumn::make('Цена'),
                                TableColumn::make('Сумма'),
                                TableColumn::make('Комментарий'),
                            ])
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Блюдо')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('category')
                                    ->label('Категория')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('quantity')
                                    ->label('Количество')
                                    ->hiddenLabel(),
                                TextEntry::make('unit_price')
                                    ->label('Цена')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (int | float | string | null $state): string => self::formatMoney($state)),
                                TextEntry::make('total_price')
                                    ->label('Сумма')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (int | float | string | null $state): string => self::formatMoney($state)),
                                TextEntry::make('comment')
                                    ->label('Комментарий')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                            ])
                            ->contained(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function formatMoney(int | float | string | null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, ',', ' ').' ₽';
    }
}
