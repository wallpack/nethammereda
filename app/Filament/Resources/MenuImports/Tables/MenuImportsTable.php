<?php

namespace App\Filament\Resources\MenuImports\Tables;

use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use App\Models\MenuImport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenuImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Имя файла')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (MenuImportStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (MenuImportStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('importedBy.name')
                    ->label('Кто загрузил')
                    ->placeholder('Не указано')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('imported_at')
                    ->label('Когда импортирован')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Не импортирован')
                    ->sortable(),
                TextColumn::make('rows_total')
                    ->label('Строки')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('rows_valid')
                    ->label('Успешно')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('rows_failed')
                    ->label('Ошибки')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (MenuImport $record): string => $record->rows_failed > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('format')
                    ->label('Формат')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '-')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(MenuImportStatus::labels()),
                SelectFilter::make('format')
                    ->label('Формат')
                    ->options([
                        MenuImportFormat::Csv->value => MenuImportFormat::Csv->label(),
                        MenuImportFormat::Xlsx->value => MenuImportFormat::Xlsx->label(),
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Открыть отчет')
                    ->icon('heroicon-o-document-magnifying-glass'),
            ])
            ->emptyStateHeading('Импортов меню пока нет')
            ->emptyStateDescription('Загрузите CSV или XLSX файл поставщика, чтобы обновить внутренний каталог.');
    }
}
