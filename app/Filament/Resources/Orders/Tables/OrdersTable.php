<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cycle.title')
                    ->label('Неделя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (mixed $state): string => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'draft' => 'Черновик',
                        'submitted' => 'Подтвержден',
                        'cancelled' => 'Отменен',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('Подтвержден в')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'draft' => 'Черновик',
                        'submitted' => 'Подтвержден',
                        'cancelled' => 'Отменен',
                    ]),
                SelectFilter::make('order_cycle_id')
                    ->relationship('cycle', 'title')
                    ->label('Неделя'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
