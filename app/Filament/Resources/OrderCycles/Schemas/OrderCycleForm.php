<?php

namespace App\Filament\Resources\OrderCycles\Schemas;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Select::make('status')
                    ->label('Статус')
                    ->required()
                    ->options(fn (?OrderCycle $record = null): array => self::statusOptions($record)),
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
