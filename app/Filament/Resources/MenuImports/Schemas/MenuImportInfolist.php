<?php

namespace App\Filament\Resources\MenuImports\Schemas;

use App\Enums\MenuImportStatus;
use App\Models\MenuImport;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuImportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сведения об импорте')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 4,
                    ])
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('Имя файла'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->formatStateUsing(fn (MenuImportStatus $state): string => $state->label())
                            ->badge()
                            ->color(fn (MenuImportStatus $state): string => $state->color()),
                        TextEntry::make('format')
                            ->label('Формат')
                            ->formatStateUsing(fn ($state): string => $state?->label() ?? '-')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('importedBy.name')
                            ->label('Кто загрузил')
                            ->placeholder('Не указано'),
                        TextEntry::make('imported_at')
                            ->label('Когда импортирован')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Не импортирован'),
                    ]),
                Section::make('Итоги')
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        TextEntry::make('rows_total')
                            ->label('Строки'),
                        TextEntry::make('rows_valid')
                            ->label('Успешно'),
                        TextEntry::make('rows_failed')
                            ->label('Ошибки'),
                    ]),
                Section::make('Отчет об ошибках')
                    ->columnSpanFull()
                    ->visible(fn (MenuImport $record): bool => $record->errorRows() !== [] || $record->errorSummary() !== null)
                    ->schema([
                        TextEntry::make('error_summary')
                            ->label('Описание')
                            ->state(fn (MenuImport $record): ?string => $record->errorSummary())
                            ->placeholder('Ошибок нет'),
                        RepeatableEntry::make('error_rows')
                            ->label('Ошибки')
                            ->state(fn (MenuImport $record): array => $record->errorRows())
                            ->table([
                                TableColumn::make('Строка'),
                                TableColumn::make('Поле'),
                                TableColumn::make('Ошибка'),
                                TableColumn::make('Значение'),
                            ])
                            ->schema([
                                TextEntry::make('row')
                                    ->label('Строка')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('field')
                                    ->label('Поле')
                                    ->hiddenLabel()
                                    ->formatStateUsing(fn (?string $state): string => self::fieldLabel($state))
                                    ->placeholder('-'),
                                TextEntry::make('message')
                                    ->label('Ошибка')
                                    ->hiddenLabel(),
                                TextEntry::make('value')
                                    ->label('Значение')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                            ])
                            ->contained(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function fieldLabel(?string $field): string
    {
        return match ($field) {
            'category' => 'Категория',
            'name' => 'Название',
            'price' => 'Цена',
            'weight' => 'Вес',
            'calories' => 'Калории',
            'proteins' => 'Белки',
            'fats' => 'Жиры',
            'carbs' => 'Углеводы',
            'description' => 'Описание',
            'image_url' => 'Ссылка на изображение',
            'external_id' => 'Внешний ID',
            'supplier_code' => 'Код поставщика',
            'is_active' => 'Активно',
            null, '' => '-',
            default => $field,
        };
    }
}
