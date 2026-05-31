<?php

namespace App\Filament\Resources\OrderCycles\Schemas;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use App\Rules\FourDigitYearDateTime;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderCycleForm
{
    public static function configure(Schema $schema): Schema
    {
        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));
        $appTimezone = config('app.timezone', 'UTC');

        return $schema
            ->components([
                Section::make('Параметры недели')
                    ->description('Название, даты и текущий этап недельного цикла.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('title')
                            ->label('Название недели')
                            ->placeholder('Например: Неделя 27 мая')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        self::dateTimePicker('starts_at')
                            ->label('Начало недели')
                            ->timezone($businessTimezone)
                            ->seconds(false)
                            ->required(),
                        self::dateTimePicker('closes_at')
                            ->label('Дедлайн заказа')
                            ->timezone($businessTimezone)
                            ->seconds(false)
                            ->default(fn (): string => now($businessTimezone)
                                ->startOfWeek(Carbon::MONDAY)
                                ->addDays(4)
                                ->setTime(12, 0)
                                ->setTimezone($appTimezone)
                                ->toDateTimeString())
                            ->required(),
                        Select::make('status')
                            ->label('Статус')
                            ->required()
                            ->options(fn (?OrderCycle $record = null): array => self::statusOptions($record))
                            ->helperText('Операционные действия «Отправить поставщику» и «Отметить доставку» доступны отдельными кнопками.'),
                        Callout::make('Скоро откроется')
                            ->description('Статус уже «Открыт», но пользователи смогут оформить заказ только после начала недели.')
                            ->info()
                            ->columnSpanFull()
                            ->visible(fn (?OrderCycle $record = null): bool => $record?->isUpcomingForOrdering() === true),
                        Callout::make('Цикл открыт, но дедлайн уже прошел')
                            ->description('Пользователи уже не могут добавлять блюда. Закройте цикл, чтобы перейти к отправке поставщику.')
                            ->warning()
                            ->columnSpanFull()
                            ->visible(fn (?OrderCycle $record = null): bool => $record?->status === OrderCycleStatus::Open
                                && $record->closes_at !== null
                                && $record->closes_at->isPast()),
                    ]),
                Section::make('Отправка и доставка')
                    ->description('Служебные отметки появляются после отправки поставщику и доставки.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->visible(fn (?OrderCycle $record = null): bool => $record?->sent_to_supplier_at !== null || $record?->delivered_at !== null)
                    ->schema([
                        self::dateTimePicker('sent_to_supplier_at')
                            ->label('Дата отправки поставщику')
                            ->timezone($businessTimezone)
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
                        self::dateTimePicker('delivered_at')
                            ->label('Дата доставки')
                            ->timezone($businessTimezone)
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
                    ]),
            ]);
    }

    private static function dateTimePicker(string $name): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->native(false)
            ->minDate('0001-01-01 00:00:00')
            ->maxDate('9999-12-31 23:59:59')
            ->rules([
                new FourDigitYearDateTime,
                'date',
                'after_or_equal:0001-01-01 00:00:00',
                'before_or_equal:9999-12-31 23:59:59',
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
