<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->label('Название в каталоге')
                    ->required()
                    ->helperText('Название в каталоге видно пользователям, название для поставщика уходит в CSV.')
                    ->maxLength(255),
                TextInput::make('supplier_name')
                    ->label('Название для поставщика')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Описание')
                    ->rows(3),
                Textarea::make('composition')
                    ->label('Состав')
                    ->rows(3),
                TextInput::make('weight')
                    ->label('Вес')
                    ->maxLength(100),
                TextInput::make('calories')
                    ->label('Калории')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('proteins')
                    ->label('Белки, г')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('fats')
                    ->label('Жиры, г')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('carbs')
                    ->label('Углеводы, г')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('price')
                    ->label('Цена, ₽')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('image_url')
                    ->label('Ссылка на изображение')
                    ->url()
                    ->maxLength(500),
                TextInput::make('image_path')
                    ->label('Локальный путь к картинке')
                    ->maxLength(500),
                Select::make('image_source')
                    ->label('Источник картинки')
                    ->options([
                        'manual' => 'manual',
                        'supplier' => 'supplier',
                        'external' => 'external',
                    ])
                    ->native(false),
                TextInput::make('external_id')
                    ->label('Внешний ID')
                    ->maxLength(255),
                TextInput::make('supplier_code')
                    ->label('Код поставщика')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Активно')
                    ->default(true),
            ]);
    }
}
