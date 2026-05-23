<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->email(),
                TextInput::make('telegram_id')
                    ->label('Telegram ID'),
                Select::make('role')
                    ->label('Роль')
                    ->options(UserRole::labels())
                    ->required(),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->required()
                    ->default(true),
                DateTimePicker::make('email_verified_at')
                    ->label('Email подтвержден в'),
                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
