<?php

namespace App\Enums;

enum FridgeItemStatus: string
{
    case InFridge = 'in_fridge';
    case Eaten = 'eaten';
    case Discarded = 'discarded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InFridge => 'В холодильнике',
            self::Eaten => 'Съедено',
            self::Discarded => 'Выброшено',
            self::Expired => 'Просрочено',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InFridge => 'success',
            self::Eaten => 'gray',
            self::Discarded => 'warning',
            self::Expired => 'danger',
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
