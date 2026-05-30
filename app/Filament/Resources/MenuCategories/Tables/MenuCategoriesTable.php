<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use App\Models\MenuCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->label('Категория')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('sort_order')
                    ->label('Сортировка')
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Статус')
                    ->state(fn (MenuCategory $record): string => $record->is_active ? 'Активна' : 'Выключена')
                    ->badge()
                    ->color(fn (MenuCategory $record): string => $record->is_active ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Блюд')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активна'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Открыть')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                ActionGroup::make([
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
            ->emptyStateHeading('Категорий пока нет')
            ->emptyStateDescription('Создайте категории, чтобы сгруппировать блюда меню.');
    }
}
