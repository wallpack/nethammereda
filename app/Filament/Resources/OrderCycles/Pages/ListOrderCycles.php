<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderCycles extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderCycleResource::class;

    protected static ?string $title = 'Недельные циклы';

    protected static ?string $breadcrumb = 'Недельные циклы';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Создать цикл'),
        ];
    }
}
