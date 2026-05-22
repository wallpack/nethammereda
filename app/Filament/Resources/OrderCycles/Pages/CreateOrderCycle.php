<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\OrderCycles\OrderCycleResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderCycle extends CreateRecord
{
    protected static string $resource = OrderCycleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['closes_at'] = $this->resolveFridayNoon($data['starts_at'] ?? null);

        return $data;
    }

    private function resolveFridayNoon(mixed $startsAt): string
    {
        $start = $startsAt !== null
            ? Carbon::parse($startsAt)
            : now();

        return $start
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->addDays(4)
            ->setTime(12, 0)
            ->toDateTimeString();
    }
}
