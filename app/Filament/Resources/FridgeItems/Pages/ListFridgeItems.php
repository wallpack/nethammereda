<?php

namespace App\Filament\Resources\FridgeItems\Pages;

use App\Filament\Resources\FridgeItems\FridgeItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFridgeItems extends ListRecords
{
    protected static string $resource = FridgeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

