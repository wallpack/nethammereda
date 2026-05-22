<?php

namespace App\Filament\Resources\FridgeItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FridgeItemsTable
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
                TextColumn::make('title_snapshot')
                    ->label('Блюдо')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity_total')
                    ->label('Всего')
                    ->sortable(),
                TextColumn::make('quantity_remaining')
                    ->label('Остаток')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'in_fridge' => 'В холодильнике',
                        'eaten' => 'Съедено',
                        'discarded' => 'Выброшено',
                        'expired' => 'Просрочено',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('arrived_at')
                    ->label('Поступило')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('eaten_at')
                    ->label('Съедено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('discarded_at')
                    ->label('Выброшено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'in_fridge' => 'В холодильнике',
                        'eaten' => 'Съедено',
                        'discarded' => 'Выброшено',
                        'expired' => 'Просрочено',
                    ]),
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
