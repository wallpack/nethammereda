<?php

namespace App\Filament\Resources\OrderCycles\Pages;

use App\Filament\Resources\Concerns\HasCleanResourceBreadcrumbs;
use App\Filament\Resources\OrderCycles\OrderCycleResource;
use App\Services\OrderCycleAutoCloser;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderCycle extends CreateRecord
{
    use HasCleanResourceBreadcrumbs;

    protected static string $resource = OrderCycleResource::class;

    protected static ?string $title = 'Создание недельного цикла';

    protected static ?string $breadcrumb = 'Создание';

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Создать и открыть');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Создать еще');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Отменить');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['closes_at'] = $data['closes_at'] ?? $this->resolveFridayNoon($data['starts_at'] ?? null);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $wasClosed = app(OrderCycleAutoCloser::class)->closeIfExpired($record);

        if (! $wasClosed) {
            return;
        }

        Notification::make()
            ->title('Прием заказов автоматически закрыт')
            ->body('Цикл создан с дедлайном в прошлом и переведен в статус «Закрыт».')
            ->warning()
            ->send();
    }

    private function resolveFridayNoon(mixed $startsAt): string
    {
        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));
        $appTimezone = config('app.timezone', 'UTC');
        $start = $startsAt !== null
            ? Carbon::parse((string) $startsAt, $businessTimezone)
            : now($businessTimezone);

        return $start
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->addDays(4)
            ->setTime(12, 0)
            ->setTimezone($appTimezone)
            ->toDateTimeString();
    }
}
