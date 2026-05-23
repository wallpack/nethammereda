<?php

namespace App\Services;

use App\Enums\MenuImportFormat;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class MenuImportParser
{
    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array{number: int, cells: array<int, string>}>
     * }
     */
    public function parse(string $storedPath, MenuImportFormat $format): array
    {
        if (! Storage::disk('local')->exists($storedPath)) {
            throw new RuntimeException('Файл импорта не найден.');
        }

        $absolutePath = Storage::disk('local')->path($storedPath);

        return match ($format) {
            MenuImportFormat::Csv => $this->parseCsv($absolutePath),
            MenuImportFormat::Xlsx => $this->parseXlsx($absolutePath),
        };
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array{number: int, cells: array<int, string>}>
     * }
     */
    private function parseCsv(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Файл импорта не удалось открыть.');
        }

        $firstLine = fgets($handle);
        $delimiter = $this->detectDelimiter($firstLine === false ? '' : $firstLine);
        rewind($handle);

        $headers = [];
        $rows = [];
        $lineNumber = 0;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;
            $normalizedCells = $this->normalizeCells($cells);

            if ($lineNumber === 1) {
                $headers = $normalizedCells;

                continue;
            }

            $rows[] = [
                'number' => $lineNumber,
                'cells' => $normalizedCells,
            ];
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     rows: array<int, array{number: int, cells: array<int, string>}>
     * }
     */
    private function parseXlsx(string $absolutePath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rawRows = $sheet->toArray(null, false, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable) {
            throw new RuntimeException('XLSX файл не удалось прочитать. Проверьте, что это корректный Excel-файл.');
        }

        $headers = [];
        $rows = [];

        foreach ($rawRows as $index => $cells) {
            $lineNumber = $index + 1;
            $normalizedCells = $this->normalizeCells(is_array($cells) ? $cells : []);

            if ($lineNumber === 1) {
                $headers = $normalizedCells;

                continue;
            }

            $rows[] = [
                'number' => $lineNumber,
                'cells' => $normalizedCells,
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $candidates = [';', ',', "\t"];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($headerLine, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, string>
     */
    private function normalizeCells(array $cells): array
    {
        return array_map(
            fn (mixed $cell): string => $this->normalizeEncoding((string) ($cell ?? '')),
            $cells,
        );
    }

    private function normalizeEncoding(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1251,ISO-8859-1');
    }
}
