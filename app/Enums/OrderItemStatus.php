<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Ordered = 'ordered';
    case Arrived = 'arrived';
    case Received = 'received';
    case Eaten = 'eaten';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Заказано',
            self::Arrived => 'Доставлено',
            self::Received => 'Получено',
            self::Eaten => 'Съедено',
            self::Cancelled => 'Отменено',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }
}
