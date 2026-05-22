<?php

namespace App\Filament\Resources\OrderCycles\Schemas;

use App\Enums\OrderCycleStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название недели')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('starts_at')
                    ->label('Начало недели')
                    ->required(),
                Select::make('status')
                    ->label('Статус')
                    ->required()
                    ->options([
                        OrderCycleStatus::Draft->value => 'Черновик',
                        OrderCycleStatus::Open->value => 'Открыт',
                        OrderCycleStatus::Closed->value => 'Закрыт',
                        OrderCycleStatus::SentToSupplier->value => 'Отправлен поставщику',
                        OrderCycleStatus::Delivered->value => 'Доставлен',
                        OrderCycleStatus::Archived->value => 'Архив',
                    ]),
            ]);
    }
}

