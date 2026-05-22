<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->formatStateUsing(fn (mixed $state): string => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'admin' => 'Администратор',
                        'user' => 'Пользователь',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label('Email подтвержден')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Роль')
                    ->options([
                        'admin' => 'Администратор',
                        'user' => 'Пользователь',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
