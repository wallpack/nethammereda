<?php

namespace App\Filament\Resources\OrderCycles\Actions;

use App\Enums\OrderCycleStatus;
use App\Exceptions\OrderCycleCannotBeMarkedDeliveredException;
use App\Models\OrderCycle;
use App\Models\User;
use App\Services\OrderCycleDeliveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class MarkOrderCycleDeliveredAction
{
    public static function make(): Action
    {
        return Action::make('markDelivered')
            ->label('Отметить доставку')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Отметить доставку')
            ->modalDescription('Цикл получит статус «Доставлен», а блюда из подтвержденных заказов появятся в холодильниках пользователей.')
            ->modalSubmitActionLabel('Отметить доставку')
            ->visible(fn (OrderCycle $record): bool => $record->status === OrderCycleStatus::SentToSupplier)
            ->action(function (OrderCycle $record): void {
                try {
                    $user = auth()->user();

                    app(OrderCycleDeliveryService::class)->markDelivered(
                        $record,
                        $user instanceof User ? $user : null,
                    );
                } catch (OrderCycleCannotBeMarkedDeliveredException $exception) {
                    Notification::make()
                        ->title('Не удалось отметить доставку')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Доставка отмечена')
                    ->body('Блюда из подтвержденных заказов синхронизированы с холодильниками пользователей.')
                    ->success()
                    ->send();
            });
    }
}
