<?php

namespace App\Enums;

use InvalidArgumentException;

enum MenuImportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public static function fromFilename(string $filename): self
    {
        $extension = mb_strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => self::Csv,
            'xlsx' => self::Xlsx,
            default => throw new InvalidArgumentException('Поддерживаются только CSV и XLSX файлы.'),
        };
    }
}
