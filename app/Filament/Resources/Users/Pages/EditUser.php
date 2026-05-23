<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Редактирование пользователя';

    protected static ?string $breadcrumb = 'Редактирование';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Удалить'),
        ];
    }
}
