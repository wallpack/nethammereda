<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('full_name')
                    ->label('ФИО')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->placeholder('Не привязан')
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->formatStateUsing(fn (UserRole $state): string => $state->label())
                    ->badge()
                    ->color(fn (UserRole $state): string => $state === UserRole::Admin ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Статус')
                    ->state(fn (User $record): string => $record->is_active ? 'Активен' : 'Выключен')
                    ->badge()
                    ->color(fn (User $record): string => $record->is_active ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label('Email подтвержден')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Нет')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->options(UserRole::labels()),
                TernaryFilter::make('is_active')
                    ->label('Активен'),
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
            ->emptyStateHeading('Пользователей пока нет')
            ->emptyStateDescription('Добавьте сотрудников, чтобы они могли делать заказы.');
    }
}
