<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\MenuItem;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_display_url')
                    ->label('Фото')
                    ->square()
                    ->imageSize(44),
                TextColumn::make('image_state')
                    ->label('Фото')
                    ->state(fn (MenuItem $record): string => $record->image_display_url ? 'Есть фото' : 'Нет фото')
                    ->badge()
                    ->color(fn (MenuItem $record): string => $record->image_display_url ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('Блюдо')
                    ->description(fn (MenuItem $record): ?string => $record->supplier_name && $record->supplier_name !== $record->title ? $record->supplier_name : null)
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('weight')
                    ->label('Вес')
                    ->state(fn (MenuItem $record): string => $record->display_weight ?? 'Не указан')
                    ->badge()
                    ->color(fn (MenuItem $record): string => filled($record->weight) ? 'gray' : 'warning')
                    ->sortable()
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
                TextColumn::make('is_active')
                    ->label('Статус')
                    ->state(fn (MenuItem $record): string => $record->is_active ? 'Активно' : 'Выключено')
                    ->badge()
                    ->color(fn (MenuItem $record): string => $record->is_active ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Категория')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Активно'),
                SelectFilter::make('image_state')
                    ->label('Фото')
                    ->options([
                        'with_image' => 'Есть фото',
                        'missing_image' => 'Нет фото',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_image' => $query->where(function (Builder $query): void {
                            $query
                                ->whereNotNull('image_path')
                                ->where('image_path', '!=', '')
                                ->orWhere(function (Builder $query): void {
                                    $query
                                        ->whereNotNull('image_url')
                                        ->where('image_url', '!=', '');
                                });
                        }),
                        'missing_image' => $query->where(function (Builder $query): void {
                            $query
                                ->where(function (Builder $query): void {
                                    $query->whereNull('image_path')->orWhere('image_path', '');
                                })
                                ->where(function (Builder $query): void {
                                    $query->whereNull('image_url')->orWhere('image_url', '');
                                });
                        }),
                        default => $query,
                    }),
                SelectFilter::make('weight_state')
                    ->label('Вес')
                    ->options([
                        'with_weight' => 'Вес указан',
                        'missing_weight' => 'Вес не указан',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'with_weight' => $query->whereNotNull('weight')->where('weight', '!=', ''),
                        'missing_weight' => $query->where(function (Builder $query): void {
                            $query->whereNull('weight')->orWhere('weight', '');
                        }),
                        default => $query,
                    }),
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
            ->emptyStateHeading('Блюд пока нет')
            ->emptyStateDescription('Добавьте блюда из меню поставщика, чтобы они появились в каталоге.');
    }
}
