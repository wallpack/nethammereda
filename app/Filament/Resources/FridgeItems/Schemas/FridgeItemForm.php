<?php

namespace App\Filament\Resources\FridgeItems\Schemas;

use App\Enums\FridgeItemStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FridgeItemForm
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
                Select::make('menu_item_id')
                    ->label('Блюдо')
                    ->relationship('menuItem', 'title')
                    ->searchable()
                    ->preload(),
                TextInput::make('title_snapshot')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                TextInput::make('quantity_total')
                    ->label('Всего')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                TextInput::make('quantity_remaining')
                    ->label('Остаток')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Select::make('status')
                    ->label('Статус')
                    ->required()
                    ->options(FridgeItemStatus::labels()),
                DateTimePicker::make('arrived_at')
                    ->label('Поступило в холодильник'),
                DateTimePicker::make('expires_at')
                    ->label('Срок годности'),
                DateTimePicker::make('eaten_at')
                    ->label('Съедено в'),
                DateTimePicker::make('discarded_at')
                    ->label('Выброшено в'),
                Textarea::make('notes')
                    ->label('Заметки')
                    ->rows(3),
            ]);
    }
}
