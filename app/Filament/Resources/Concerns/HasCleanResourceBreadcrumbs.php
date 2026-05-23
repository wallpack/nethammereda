<?php

namespace App\Filament\Resources\Concerns;

use Filament\Resources\Pages\ListRecords;

trait HasCleanResourceBreadcrumbs
{
    public function getBreadcrumbs(): array
    {
        if ($this instanceof ListRecords) {
            return [];
        }

        $resource = static::getResource();

        return [
            $this->getResourceUrl() => $resource::getNavigationLabel(),
        ];
    }
}
