<?php

namespace App\Filament\Resources\OrderCycles;

use App\Filament\Resources\OrderCycles\Pages\CreateOrderCycle;
use App\Filament\Resources\OrderCycles\Pages\EditOrderCycle;
use App\Filament\Resources\OrderCycles\Pages\ListOrderCycles;
use App\Filament\Resources\OrderCycles\Schemas\OrderCycleForm;
use App\Filament\Resources\OrderCycles\Tables\OrderCyclesTable;
use App\Models\OrderCycle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderCycleResource extends Resource
{
    protected static ?string $model = OrderCycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?string $navigationLabel = 'Недельные циклы';

    protected static ?string $modelLabel = 'недельный цикл';

    protected static ?string $pluralModelLabel = 'недельные циклы';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return OrderCycleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderCyclesTable::configure($table);
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
            'index' => ListOrderCycles::route('/'),
            'create' => CreateOrderCycle::route('/create'),
            'edit' => EditOrderCycle::route('/{record}/edit'),
        ];
    }
}
