<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Пользователи';

    protected static ?string $breadcrumb = 'Пользователи';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Добавить пользователя'),
        ];
    }
}
