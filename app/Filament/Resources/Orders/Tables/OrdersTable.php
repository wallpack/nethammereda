<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
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
                    ->label('Клиент')
                    ->description(fn (Order $record): ?string => $record->user?->full_name ?: $record->user?->email)
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('cycle.title')
                    ->label('Неделя')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('submitted_at')
                    ->label('Отправлен')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Не отправлен')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderStatus::labels()),
                SelectFilter::make('order_cycle_id')
                    ->relationship('cycle', 'title')
                    ->label('Неделя')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('submitted_state')
                    ->label('Отправка')
                    ->options([
                        'submitted' => 'Отправлен',
                        'not_submitted' => 'Не отправлен',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'submitted' => $query->whereNotNull('submitted_at'),
                        'not_submitted' => $query->whereNull('submitted_at'),
                        default => $query,
                    }),
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Пользователь')
                    ->searchable()
                    ->preload(),
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
            ->emptyStateHeading('Заказов пока нет')
            ->emptyStateDescription('Заказы появятся здесь, когда пользователи начнут собирать обеды на неделю.');
    }
}
