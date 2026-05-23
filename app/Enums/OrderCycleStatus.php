<?php

namespace App\Enums;

enum OrderCycleStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case SentToSupplier = 'sent_to_supplier';
    case Delivered = 'delivered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Open => 'Открыт',
            self::Closed => 'Закрыт',
            self::SentToSupplier => 'Отправлен поставщику',
            self::Delivered => 'Доставлен',
            self::Archived => 'Архивирован',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'success',
            self::Closed => 'warning',
            self::SentToSupplier => 'info',
            self::Delivered => 'success',
            self::Archived => 'gray',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        if ($status === $this) {
            return true;
        }

        return match ($this) {
            self::Draft => $status === self::Open,
            self::Open => $status === self::Closed,
            self::Closed => $status === self::SentToSupplier,
            self::SentToSupplier => $status === self::Delivered,
            self::Delivered => $status === self::Archived,
            self::Archived => false,
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
