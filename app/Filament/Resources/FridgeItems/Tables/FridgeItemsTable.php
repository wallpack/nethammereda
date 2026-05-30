<?php

namespace App\Filament\Resources\FridgeItems\Tables;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('title_snapshot')
                    ->label('Блюдо')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('quantity_total')
                    ->label('Всего')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity_remaining')
                    ->label('Остаток')
                    ->suffix(fn (FridgeItem $record): string => ' из '.$record->quantity_total)
                    ->sortable()
                    ->alignEnd(),
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
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Не указан')
                    ->color(fn (FridgeItem $record): string => match (true) {
                        $record->status === FridgeItemStatus::Expired => 'danger',
                        $record->status === FridgeItemStatus::InFridge && $record->expires_at?->isPast() => 'danger',
                        $record->status === FridgeItemStatus::InFridge && $record->expires_at?->between(now(), now()->addDays(2)) => 'warning',
                        default => 'gray',
                    }),
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
                SelectFilter::make('attention')
                    ->label('Внимание')
                    ->options([
                        'in_fridge' => 'В холодильнике',
                        'soon_expires' => 'Скоро истекает',
                        'expired' => 'Просрочено',
                        'written_off' => 'Списано',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'in_fridge' => $query
                            ->where('status', FridgeItemStatus::InFridge->value)
                            ->where('quantity_remaining', '>', 0),
                        'soon_expires' => $query
                            ->where('status', FridgeItemStatus::InFridge->value)
                            ->where('quantity_remaining', '>', 0)
                            ->whereNotNull('expires_at')
                            ->whereBetween('expires_at', [now(), now()->addDays(2)]),
                        'expired' => $query->where(function (Builder $query): void {
                            $query
                                ->where('status', FridgeItemStatus::Expired->value)
                                ->orWhere(function (Builder $query): void {
                                    $query
                                        ->where('status', FridgeItemStatus::InFridge->value)
                                        ->where('quantity_remaining', '>', 0)
                                        ->whereNotNull('expires_at')
                                        ->where('expires_at', '<=', now());
                                });
                        }),
                        'written_off' => $query->where('status', FridgeItemStatus::Discarded->value),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Открыть')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
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
