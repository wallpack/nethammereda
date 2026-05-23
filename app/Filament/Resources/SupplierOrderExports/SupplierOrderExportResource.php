<?php

namespace App\Filament\Resources\SupplierOrderExports;

use App\Filament\Resources\SupplierOrderExports\Pages\ListSupplierOrderExports;
use App\Filament\Resources\SupplierOrderExports\Pages\ViewSupplierOrderExport;
use App\Filament\Resources\SupplierOrderExports\Schemas\SupplierOrderExportInfolist;
use App\Filament\Resources\SupplierOrderExports\Tables\SupplierOrderExportsTable;
use App\Models\SupplierOrderExport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierOrderExportResource extends Resource
{
    protected static ?string $model = SupplierOrderExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|\UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?string $navigationLabel = 'Отправки поставщику';

    protected static ?string $modelLabel = 'отправка поставщику';

    protected static ?string $pluralModelLabel = 'отправки поставщику';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 25;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['orderCycle', 'exportedBy']);
    }

    public static function table(Table $table): Table
    {
        return SupplierOrderExportsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupplierOrderExportInfolist::configure($schema);
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
            'index' => ListSupplierOrderExports::route('/'),
            'view' => ViewSupplierOrderExport::route('/{record}'),
        ];
    }
}
