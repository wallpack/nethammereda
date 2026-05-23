<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\OrderCycles\Actions\MarkOrderCycleDeliveredAction;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderCycle extends EditRecord
{
    protected static string $resource = OrderCycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkOrderCycleDeliveredAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
