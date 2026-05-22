<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Сортировка')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Блюд'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активна'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
