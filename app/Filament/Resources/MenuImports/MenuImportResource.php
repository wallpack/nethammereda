<?php

namespace App\Filament\Resources\MenuImports;

use App\Filament\Resources\MenuImports\Pages\ListMenuImports;
use App\Filament\Resources\MenuImports\Pages\ViewMenuImport;
use App\Filament\Resources\MenuImports\Schemas\MenuImportInfolist;
use App\Filament\Resources\MenuImports\Tables\MenuImportsTable;
use App\Models\MenuImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MenuImportResource extends Resource
{
    protected static ?string $model = MenuImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Меню';

    protected static ?string $navigationLabel = 'Импорт меню';

    protected static ?string $modelLabel = 'импорт меню';

    protected static ?string $pluralModelLabel = 'импорт меню';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 30;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('importedBy');
    }

    public static function table(Table $table): Table
    {
        return MenuImportsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MenuImportInfolist::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuImports::route('/'),
            'view' => ViewMenuImport::route('/{record}'),
        ];
    }
}
