<?php

namespace App\Filament\Resources\OrderCycles\Actions;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;

class ReopenOrderCycleAction
{
    public static function make(): Action
    {
        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));
        $appTimezone = config('app.timezone', 'UTC');

        return Action::make('reopenOrdering')
            ->label('Открыть приём заказов')
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->modalHeading('Открыть приём заказов')
            ->modalDescription('Цикл снова станет открытым, а дедлайн будет перенесен на выбранное время в будущем.')
            ->modalSubmitActionLabel('Открыть приём')
            ->visible(fn (OrderCycle $record): bool => $record->status === OrderCycleStatus::Closed)
            ->form([
                DateTimePicker::make('new_closes_at')
                    ->label('Новый дедлайн')
                    ->timezone($businessTimezone)
                    ->required()
                    ->seconds(false)
                    ->rules(['after:now'])
                    ->default(fn (): string => now()->addHour()->setSecond(0)->toDateTimeString()),
            ])
            ->action(function (OrderCycle $record, array $data, \Livewire\Component $livewire) use ($businessTimezone, $appTimezone): void {
                $record->refresh();

                if ($record->status !== OrderCycleStatus::Closed) {
                    Notification::make()
                        ->title('Не удалось открыть приём заказов')
                        ->body('Переоткрыть можно только цикл со статусом «Закрыт».')
                        ->danger()
                        ->send();

                    return;
                }

                $rawDeadline = $data['new_closes_at'] ?? '';
                $newDeadline = $rawDeadline instanceof \DateTimeInterface
                    ? Carbon::instance($rawDeadline)->setTimezone($appTimezone)
                    : Carbon::parse((string) $rawDeadline, $appTimezone)->setTimezone($appTimezone);

                if (! $newDeadline->gt(now())) {
                    $livewire->addError('new_closes_at', 'Новый дедлайн должен быть позже текущего времени.');
                    $livewire->addError('mountedActionsData.0.new_closes_at', 'Новый дедлайн должен быть позже текущего времени.');
                    $livewire->addError('mountedActions.0.data.new_closes_at', 'Новый дедлайн должен быть позже текущего времени.');

                    return;
                }

                $record->forceFill([
                    'status' => OrderCycleStatus::Open,
                    'closes_at' => $newDeadline,
                ])->save();

                Notification::make()
                    ->title('Приём заказов открыт')
                    ->body('Приём заказов открыт до '.$newDeadline->copy()->setTimezone($businessTimezone)->format('d.m.Y H:i').'.')
                    ->success()
                    ->send();
            });
    }
}
