<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('order_cycle_id')
                    ->label('Неделя')
                    ->relationship('cycle', 'title')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->label('Статус заказа')
                    ->options([
                        OrderStatus::Draft->value => 'Черновик',
                        OrderStatus::Submitted->value => 'Подтвержден',
                        OrderStatus::Cancelled->value => 'Отменен',
                    ])
                    ->required(),
                TextInput::make('total_price')
                    ->label('Итоговая сумма')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                DateTimePicker::make('submitted_at')
                    ->label('Отправлен в'),
                Repeater::make('items')
                    ->label('Позиции заказа')
                    ->relationship()
                    ->addable(false)
                    ->deletable(false)
                    ->schema([
                        TextInput::make('title_snapshot')
                            ->label('Блюдо')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('price_snapshot')
                            ->label('Цена, ₽')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('quantity')
                            ->label('Количество')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        Select::make('status')
                            ->label('Статус позиции')
                            ->options([
                                OrderItemStatus::Ordered->value => 'Заказано',
                                OrderItemStatus::Arrived->value => 'Доставлено',
                                OrderItemStatus::Received->value => 'Получено',
                                OrderItemStatus::Eaten->value => 'Съедено',
                                OrderItemStatus::Cancelled->value => 'Отменено',
                            ])
                            ->required(),
                    ])
                    ->columns(4),
            ]);
    }
}
