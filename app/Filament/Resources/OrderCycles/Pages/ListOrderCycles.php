<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\OrderCycles\OrderCycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderCycles extends ListRecords
{
    protected static string $resource = OrderCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
