<?php

namespace App\Services\MenuAudit;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ImageAuditService
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function audit(string $path): array
    {
        $root = $this->resolveDirectory($path);
        $auditDirectory = storage_path('app/menu-audit');
        File::ensureDirectoryExists($auditDirectory);

        $rows = [];
        $invalidRows = [];

        foreach ($this->imageFiles($root) as $file) {
            $absolutePath = $file->getPathname();
            $relativePath = $this->relativePath($root, $absolutePath);
            $extension = mb_strtolower($file->getExtension());
            $hash = hash_file('sha256', $absolutePath) ?: '';
            $imageSize = @getimagesize($absolutePath);
            $valid = is_array($imageSize);
            $row = [
                'relative_path' => $relativePath,
                'filename' => $file->getFilename(),
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                'sha256' => $hash,
                'width' => $valid ? (string) ($imageSize[0] ?? '') : '',
                'height' => $valid ? (string) ($imageSize[1] ?? '') : '',
                'mime' => $valid ? (string) ($imageSize['mime'] ?? '') : '',
                'valid' => $valid ? '1' : '0',
                'error' => $valid ? '' : 'Файл не удалось прочитать как изображение.',
                'perceptual_hash' => $valid ? ($this->differenceHash($absolutePath) ?? '') : '',
            ];

            $rows[] = $row;

            if (! $valid) {
                $invalidRows[] = $row;
            }
        }

        $duplicateRows = $this->exactDuplicateRows($rows);
        $possibleDuplicateRows = $this->possibleDuplicateRows($rows);

        $this->csv->write($auditDirectory.'/image-files.csv', [
            'relative_path',
            'filename',
            'extension',
            'size_bytes',
            'sha256',
            'width',
            'height',
            'mime',
            'valid',
            'error',
            'perceptual_hash',
        ], $rows);
        $this->csv->write($auditDirectory.'/image-duplicates.csv', [
            'sha256',
            'relative_path',
            'filename',
            'size_bytes',
        ], $duplicateRows);
        $this->csv->write($auditDirectory.'/image-possible-duplicates.csv', [
            'left_relative_path',
            'right_relative_path',
            'hamming_distance',
            'left_hash',
            'right_hash',
        ], $possibleDuplicateRows);
        $this->csv->write($auditDirectory.'/image-invalid-files.csv', [
            'relative_path',
            'filename',
            'extension',
            'size_bytes',
            'error',
        ], $invalidRows);

        return [
            'source_path' => $root,
            'valid_files' => count(array_filter($rows, static fn (array $row): bool => $row['valid'] === '1')),
            'invalid_files' => count($invalidRows),
            'exact_duplicate_groups' => count(array_filter($this->groupBy($rows, 'sha256'), static fn (array $group): bool => count($group) > 1)),
            'exact_duplicate_rows' => count($duplicateRows),
            'possible_duplicate_pairs' => count($possibleDuplicateRows),
        ];
    }

    private function resolveDirectory(string $path): string
    {
        $candidate = $this->isAbsolutePath($path) ? $path : base_path($path);
        $realPath = realpath($candidate);

        if ($realPath === false || ! is_dir($realPath)) {
            throw new \InvalidArgumentException("Папка с изображениями не найдена: {$path}");
        }

        return $realPath;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function imageFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $extension = mb_strtolower($file->getExtension());

            if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                continue;
            }

            $realPath = realpath($file->getPathname());

            if ($realPath === false || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $file;
        }

        usort($files, static fn (SplFileInfo $left, SplFileInfo $right): int => strcmp($left->getFilename(), $right->getFilename()));

        return $files;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function exactDuplicateRows(array $rows): array
    {
        $duplicates = [];

        foreach ($this->groupBy($rows, 'sha256') as $hash => $group) {
            if ($hash === '' || count($group) < 2) {
                continue;
            }

            foreach ($group as $row) {
                $duplicates[] = [
                    'sha256' => $hash,
                    'relative_path' => $row['relative_path'],
                    'filename' => $row['filename'],
                    'size_bytes' => $row['size_bytes'],
                ];
            }
        }

        return $duplicates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function possibleDuplicateRows(array $rows): array
    {
        $validRows = array_values(array_filter($rows, static fn (array $row): bool => $row['perceptual_hash'] !== ''));
        $duplicates = [];

        for ($i = 0; $i < count($validRows); $i++) {
            for ($j = $i + 1; $j < count($validRows); $j++) {
                if ($validRows[$i]['sha256'] === $validRows[$j]['sha256']) {
                    continue;
                }

                $distance = $this->hammingDistance((string) $validRows[$i]['perceptual_hash'], (string) $validRows[$j]['perceptual_hash']);

                if ($distance > 4) {
                    continue;
                }

                $duplicates[] = [
                    'left_relative_path' => $validRows[$i]['relative_path'],
                    'right_relative_path' => $validRows[$j]['relative_path'],
                    'hamming_distance' => $distance,
                    'left_hash' => $validRows[$i]['perceptual_hash'],
                    'right_hash' => $validRows[$j]['perceptual_hash'],
                ];
            }
        }

        return $duplicates;
    }

    private function differenceHash(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            return null;
        }

        $thumb = imagecreatetruecolor(9, 8);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 9, 8, imagesx($source), imagesy($source));

        $values = [];

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $gray = (($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114;
                $values[$y][$x] = $gray;
            }
        }

        imagedestroy($source);
        imagedestroy($thumb);

        $bits = [];

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits[] = $values[$y][$x] > $values[$y][$x + 1] ? '1' : '0';
            }
        }

        return implode('', $bits);
    }

    private function hammingDistance(string $left, string $right): int
    {
        $distance = 0;
        $length = min(strlen($left), strlen($right));

        for ($i = 0; $i < $length; $i++) {
            if ($left[$i] !== $right[$i]) {
                $distance++;
            }
        }

        return $distance + abs(strlen($left) - strlen($right));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBy(array $rows, string $key): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) ($row[$key] ?? '')][] = $row;
        }

        return $groups;
    }

    private function relativePath(string $root, string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR));
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR);
    }
}
