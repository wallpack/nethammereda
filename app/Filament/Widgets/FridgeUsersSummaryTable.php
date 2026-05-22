<?php

namespace App\Filament\Widgets;

use App\Enums\FridgeItemStatus;
use App\Models\FridgeItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FridgeUsersSummaryTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $inFridge = FridgeItemStatus::InFridge->value;
        $expired = FridgeItemStatus::Expired->value;

        return $table
            ->heading('Холодильник по пользователям')
            ->query(
                FridgeItem::query()
                    ->selectRaw(
                        'MIN(fridge_items.id) as id,
                        users.name as user_name,
                        COUNT(CASE WHEN fridge_items.status = ? AND fridge_items.quantity_remaining > 0 THEN 1 END) as active_items,
                        COALESCE(SUM(CASE WHEN fridge_items.status = ? THEN fridge_items.quantity_remaining ELSE 0 END), 0) as remaining_portions,
                        COUNT(CASE WHEN fridge_items.status = ? THEN 1 END) as expired_items',
                        [$inFridge, $inFridge, $expired],
                    )
                    ->join('users', 'users.id', '=', 'fridge_items.user_id')
                    ->groupBy('fridge_items.user_id', 'users.name')
            )
            ->defaultSort('remaining_portions', 'desc')
            ->columns([
                TextColumn::make('user_name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('active_items')
                    ->label('Активных блюд')
                    ->sortable(),
                TextColumn::make('remaining_portions')
                    ->label('Остаток порций')
                    ->sortable(),
                TextColumn::make('expired_items')
                    ->label('Помечено просроченным')
                    ->sortable(),
            ])
            ->emptyStateHeading('Пока нет данных по холодильнику')
            ->emptyStateDescription('Появится после доставки заказов.');
    }
}
