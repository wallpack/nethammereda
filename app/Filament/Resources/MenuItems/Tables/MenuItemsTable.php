<?php

namespace App\Filament\Resources\MenuItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Блюдо')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('weight')
                    ->label('Вес')
                    ->toggleable(),
                TextColumn::make('calories')
                    ->label('Ккал')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('proteins')
                    ->label('Б, г')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fats')
                    ->label('Ж, г')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('carbs')
                    ->label('У, г')
                    ->numeric(decimalPlaces: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('external_id')
                    ->label('Внешний ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_code')
                    ->label('Код поставщика')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('image_source')
                    ->label('Источник картинки')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('image_path')
                    ->label('Локальная картинка')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Категория'),
                TernaryFilter::make('is_active')
                    ->label('Активно'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Изменить'),
                DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранное'),
                ]),
            ])
            ->emptyStateHeading('Блюд пока нет')
            ->emptyStateDescription('Добавьте блюда из меню поставщика, чтобы они появились в каталоге.');
    }
}
