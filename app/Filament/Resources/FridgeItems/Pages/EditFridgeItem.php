<?php

namespace App\Filament\Resources\FridgeItems\Pages;

use App\Filament\Resources\FridgeItems\FridgeItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFridgeItem extends EditRecord
{
    protected static string $resource = FridgeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

