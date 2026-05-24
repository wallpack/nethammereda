<?php

namespace App\Services\MenuAudit;

use Illuminate\Support\Facades\File;

class CsvReportWriter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function write(string $path, array $headers, iterable $rows): void
    {
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть CSV-отчет для записи: {$path}");
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv(
                $handle,
                array_map(
                    static fn (string $header): string => (string) ($row[$header] ?? ''),
                    $headers,
                ),
                ';',
            );
        }

        fclose($handle);
    }
}
