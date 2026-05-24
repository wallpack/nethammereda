<?php

namespace App\Services\MenuImages;

use App\Models\MenuItem;
use App\Services\MenuAudit\CsvReportWriter;
use App\Support\MenuTextNormalizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalMenuImageAttacher
{
    private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly MenuTextNormalizer $normalizer,
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function attach(string $path, bool $dryRun = false, bool $force = false): array
    {
        $root = $this->resolveDirectory($path);
        $items = MenuItem::query()->with('category:id,name')->orderBy('title')->get();
        $images = $this->imageFiles($root);
        $matchesByItem = [];
        $conflicts = [];
        $unmatchedImages = [];

        foreach ($images as $image) {
            $matches = $this->matchesForImage($image, $items);

            if ($matches === []) {
                $unmatchedImages[] = $image;

                continue;
            }

            if (count($matches) > 1 && ! $this->isAllowedDuplicateNameShare($matches)) {
                $conflicts[] = [
                    'source_file' => $this->relativePath($root, $image->getPathname()),
                    'reason' => 'Одна картинка подходит нескольким разным блюдам.',
                    'candidates' => $this->candidateList($matches),
                ];

                continue;
            }

            foreach ($matches as $match) {
                $matchesByItem[$match['item']->id][] = [
                    'image' => $image,
                    'type' => $match['type'],
                    'confidence' => $match['confidence'],
                    'note' => count($matches) > 1 ? 'shared_duplicate_name' : '',
                ];
            }
        }

        foreach ($matchesByItem as $itemId => $matches) {
            $uniqueSources = collect($matches)
                ->map(fn (array $match): string => $match['image']->getPathname())
                ->unique()
                ->values();

            if ($uniqueSources->count() <= 1) {
                continue;
            }

            $item = $items->firstWhere('id', $itemId);
            $conflicts[] = [
                'source_file' => $uniqueSources->implode(' | '),
                'reason' => 'Несколько картинок подходят одному блюду.',
                'candidates' => $item instanceof MenuItem ? "#{$item->id} {$item->title}" : (string) $itemId,
            ];
            unset($matchesByItem[$itemId]);
        }

        $rows = [];
        $attached = 0;
        $skippedExisting = 0;

        foreach ($matchesByItem as $itemId => $matches) {
            $item = $items->firstWhere('id', $itemId);

            if (! $item instanceof MenuItem) {
                continue;
            }

            $match = $matches[0];
            $image = $match['image'];
            $destination = $this->destinationPath($item, $image);
            $action = 'attach';
            $note = $match['note'];

            if (filled($item->image_path) && ! $force) {
                $action = 'skip_existing';
                $note = trim($note.' existing image_path');
                $skippedExisting++;
            } elseif (! $dryRun) {
                Storage::disk('public')->put($destination, file_get_contents($image->getPathname()));
                $item->forceFill([
                    'image_path' => $destination,
                    'image_source' => 'manual',
                    'image_assigned_at' => now(),
                ])->save();
                $attached++;
            } else {
                $attached++;
            }

            $rows[] = [
                'source_file' => $this->relativePath($root, $image->getPathname()),
                'menu_item_id' => $item->id,
                'menu_item_title' => $item->title,
                'menu_item_category' => $item->category?->name,
                'match_type' => $match['type'],
                'confidence' => number_format((float) $match['confidence'], 3, '.', ''),
                'action' => $dryRun ? 'dry_run_'.$action : $action,
                'destination_path' => $destination,
                'note' => $note,
            ];
        }

        $missingRows = [];

        foreach ($unmatchedImages as $image) {
            $missingRows[] = [
                'type' => 'unmatched_image',
                'source_file' => $this->relativePath($root, $image->getPathname()),
                'menu_item_id' => '',
                'menu_item_title' => '',
                'note' => 'Не удалось уверенно сопоставить файл с блюдом.',
            ];
        }

        foreach ($items as $item) {
            if (filled($item->image_path) || isset($matchesByItem[$item->id])) {
                continue;
            }

            $missingRows[] = [
                'type' => 'menu_item_without_image',
                'source_file' => '',
                'menu_item_id' => $item->id,
                'menu_item_title' => $item->title,
                'note' => 'Для блюда не найдена локальная картинка.',
            ];
        }

        $auditDirectory = storage_path('app/menu-audit');
        $this->csv->write($auditDirectory.'/image-attach-dry-run.csv', [
            'source_file',
            'menu_item_id',
            'menu_item_title',
            'menu_item_category',
            'match_type',
            'confidence',
            'action',
            'destination_path',
            'note',
        ], $rows);
        $this->csv->write($auditDirectory.'/image-attach-conflicts.csv', [
            'source_file',
            'reason',
            'candidates',
        ], $conflicts);
        $this->csv->write($auditDirectory.'/image-attach-missing.csv', [
            'type',
            'source_file',
            'menu_item_id',
            'menu_item_title',
            'note',
        ], $missingRows);

        return [
            'source_path' => $root,
            'images_scanned' => count($images),
            'matched_items' => count($matchesByItem),
            'attached_or_planned' => $attached,
            'skipped_existing' => $skippedExisting,
            'conflicts' => count($conflicts),
            'unmatched_images' => count($unmatchedImages),
            'menu_items_without_image' => count(array_filter($missingRows, static fn (array $row): bool => $row['type'] === 'menu_item_without_image')),
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
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $items
     * @return array<int, array{item: MenuItem, type: string, confidence: float}>
     */
    private function matchesForImage(SplFileInfo $image, $items): array
    {
        $filename = pathinfo($image->getFilename(), PATHINFO_FILENAME);

        if (preg_match('/^menu-item-(\d+)$/i', $filename, $match) === 1) {
            $item = $items->firstWhere('id', (int) $match[1]);

            return $item instanceof MenuItem
                ? [['item' => $item, 'type' => 'id_filename', 'confidence' => 1.0]]
                : [];
        }

        $normalizedFilename = $this->normalizer->normalizeName($filename);
        $slugFilename = $this->normalizer->slug($filename);
        $exact = [];

        foreach ($items as $item) {
            $normalizedTitle = $this->normalizer->normalizeName($item->title);

            if ($normalizedFilename === $normalizedTitle) {
                $exact[] = ['item' => $item, 'type' => 'normalized_name', 'confidence' => 1.0];
            }
        }

        if ($exact !== []) {
            return $exact;
        }

        $slugMatches = [];

        foreach ($items as $item) {
            if ($slugFilename === $this->normalizer->slug($item->title)) {
                $slugMatches[] = ['item' => $item, 'type' => 'suggested_slug', 'confidence' => 0.98];
            }
        }

        if ($slugMatches !== []) {
            return $slugMatches;
        }

        $bestSimilarity = 0.0;
        $fuzzyMatches = [];

        foreach ($items as $item) {
            $similarity = $this->normalizer->similarity($filename, $item->title);

            if ($similarity < 0.93 || $similarity < $bestSimilarity) {
                continue;
            }

            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $fuzzyMatches = [];
            }

            $fuzzyMatches[] = ['item' => $item, 'type' => 'fuzzy_name', 'confidence' => $similarity];
        }

        return $fuzzyMatches;
    }

    /**
     * @param  array<int, array{item: MenuItem, type: string, confidence: float}>  $matches
     */
    private function isAllowedDuplicateNameShare(array $matches): bool
    {
        $names = collect($matches)
            ->map(fn (array $match): string => $this->normalizer->normalizeName($match['item']->title))
            ->unique();

        return $names->count() === 1;
    }

    /**
     * @param  array<int, array{item: MenuItem, type: string, confidence: float}>  $matches
     */
    private function candidateList(array $matches): string
    {
        return collect($matches)
            ->map(fn (array $match): string => "#{$match['item']->id} {$match['item']->title} ({$match['type']}, ".number_format($match['confidence'], 3, '.', '').')')
            ->implode(' | ');
    }

    private function destinationPath(MenuItem $item, SplFileInfo $image): string
    {
        $extension = mb_strtolower($image->getExtension());
        $filename = $this->normalizer->suggestedFilename($item->id, $item->title, $extension);

        return "menu-items/manual/{$item->id}/{$filename}";
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
