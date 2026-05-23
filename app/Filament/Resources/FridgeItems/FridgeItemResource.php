<?php

namespace App\Filament\Resources\FridgeItems;

use App\Filament\Resources\FridgeItems\Pages\CreateFridgeItem;
use App\Filament\Resources\FridgeItems\Pages\EditFridgeItem;
use App\Filament\Resources\FridgeItems\Pages\ListFridgeItems;
use App\Filament\Resources\FridgeItems\Schemas\FridgeItemForm;
use App\Filament\Resources\FridgeItems\Tables\FridgeItemsTable;
use App\Models\FridgeItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FridgeItemResource extends Resource
{
    protected static ?string $model = FridgeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?string $navigationLabel = 'Холодильник';

    protected static ?string $modelLabel = 'позиция холодильника';

    protected static ?string $pluralModelLabel = 'позиции холодильника';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return FridgeItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FridgeItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFridgeItems::route('/'),
            'create' => CreateFridgeItem::route('/create'),
            'edit' => EditFridgeItem::route('/{record}/edit'),
        ];
    }
}
