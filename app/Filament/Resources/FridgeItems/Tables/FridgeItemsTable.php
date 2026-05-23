<?php

namespace App\Filament\Resources\FridgeItems\Tables;

use App\Enums\FridgeItemStatus;
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity_remaining')
                    ->label('Остаток')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (FridgeItemStatus $state): string => $state->label())
                    ->color(fn (FridgeItemStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('arrived_at')
                    ->label('Поступило')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')
                    ->label('Срок годности')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Не указан'),
                TextColumn::make('eaten_at')
                    ->label('Съедено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discarded_at')
                    ->label('Выброшено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(FridgeItemStatus::labels()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Изменить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранное'),
                ]),
            ])
            ->emptyStateHeading('В холодильнике пока нет блюд')
            ->emptyStateDescription('Блюда появятся после отметки доставки по отправленному поставщику циклу.');
    }
}
