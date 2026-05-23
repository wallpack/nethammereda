<?php

namespace App\Enums;

enum MenuImportStatus: string
{
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Imported = 'imported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Загружен',
            self::Validated => 'Проверен',
            self::Imported => 'Импортирован',
            self::Failed => 'Ошибка',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Uploaded => 'gray',
            self::Validated => 'info',
            self::Imported => 'success',
            self::Failed => 'danger',
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
