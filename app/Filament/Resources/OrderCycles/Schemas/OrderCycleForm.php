<?php

namespace App\Filament\Resources\OrderCycles\Schemas;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;

class OrderCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название недели')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('starts_at')
                    ->label('Начало недели')
                    ->required(),
                DateTimePicker::make('closes_at')
                    ->label('Дедлайн заказа')
                    ->default(fn (): string => now()
                        ->startOfWeek(Carbon::MONDAY)
                        ->addDays(4)
                        ->setTime(12, 0)
                        ->toDateTimeString())
                    ->required(),
                Select::make('status')
                    ->label('Статус')
                    ->required()
                    ->options(fn (?OrderCycle $record = null): array => self::statusOptions($record)),
                Callout::make('Цикл открыт, но дедлайн уже прошел')
                    ->description('Пользователи уже не могут добавлять блюда. Закройте цикл, чтобы перейти к отправке поставщику.')
                    ->warning()
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->status === OrderCycleStatus::Open
                        && $record->closes_at !== null
                        && $record->closes_at->isPast()),
                DateTimePicker::make('sent_to_supplier_at')
                    ->label('Дата отправки поставщику')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->sent_to_supplier_at !== null),
                Select::make('sent_to_supplier_by')
                    ->label('Кто отправил поставщику')
                    ->relationship('sentToSupplierBy', 'name')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('-')
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->sent_to_supplier_by !== null),
                DateTimePicker::make('delivered_at')
                    ->label('Дата доставки')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->delivered_at !== null),
                Select::make('delivered_by')
                    ->label('Кто отметил доставку')
                    ->relationship('deliveredBy', 'name')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('-')
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->delivered_by !== null),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(?OrderCycle $record): array
    {
        if (! $record?->exists) {
            return self::labelsFor([
                OrderCycleStatus::Draft,
                OrderCycleStatus::Open,
            ]);
        }

        $current = $record->status;
        $statuses = [$current->value => $current];

        foreach (OrderCycleStatus::cases() as $status) {
            if ($current === OrderCycleStatus::Closed && $status === OrderCycleStatus::SentToSupplier) {
                continue;
            }

            if ($current === OrderCycleStatus::SentToSupplier && $status === OrderCycleStatus::Delivered) {
                continue;
            }

            if ($current->canTransitionTo($status)) {
                $statuses[$status->value] = $status;
            }
        }

        return self::labelsFor(array_values($statuses));
    }

    /**
     * @param  array<int, OrderCycleStatus>  $statuses
     * @return array<string, string>
     */
    private static function labelsFor(array $statuses): array
    {
        $labels = [];

        foreach ($statuses as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }
}
