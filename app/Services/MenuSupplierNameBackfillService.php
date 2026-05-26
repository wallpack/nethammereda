<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Services\MenuAudit\CsvReportWriter;
use App\Support\MenuCatalogTitleFormatter;
use App\Support\MenuTextNormalizer;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class MenuSupplierNameBackfillService
{
    public function __construct(
        private readonly MenuCatalogTitleFormatter $titleFormatter,
        private readonly MenuTextNormalizer $normalizer,
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array{
     *     source_path: string,
     *     menu_items_total: int,
     *     matched: int,
     *     applied: int,
     *     review: int,
     *     supplier_name_filled: int,
     *     supplier_name_missing: int,
     *     reports: array{
     *         applied: string,
     *         needs_review: string,
     *         missing_supplier_name: string
     *     }
     * }
     */
    public function run(?string $sourcePath = null): array
    {
        $resolvedSourcePath = $this->resolveSourcePath($sourcePath);
        $sourceRows = $this->supplierRows($resolvedSourcePath);
        $index = $this->buildSourceIndex($sourceRows);

        $reportDirectory = base_path('_menu_audit/reports');
        File::ensureDirectoryExists($reportDirectory);

        $appliedRows = [];
        $reviewRows = [];
        $matched = 0;
        $applied = 0;

        $menuItems = MenuItem::query()
            ->with('category:id,name')
            ->orderBy('id')
            ->get();

        foreach ($menuItems as $menuItem) {
            $categoryName = (string) ($menuItem->category?->name ?? '');
            $match = $this->matchSupplierRow($menuItem, $categoryName, $index);
            $resolvedSupplierName = $this->titleFormatter->supplierName((string) ($menuItem->supplier_name ?? ''));
            $matchBasis = 'existing_supplier_name';

            if ($match === null) {
                $reviewRows[] = [
                    'menu_item_id' => (string) $menuItem->id,
                    'category' => $categoryName,
                    'price' => $this->normalizePrice((float) $menuItem->price),
                    'title' => (string) $menuItem->title,
                    'supplier_name_current' => (string) ($menuItem->supplier_name ?? ''),
                    'reason' => 'no_exact_category_price_name_match',
                ];

            } else {
                $matched++;
                $resolvedSupplierName = $this->titleFormatter->supplierName($match['supplier_name']);
                $matchBasis = $match['match_basis'];
            }

            $catalogSource = $resolvedSupplierName !== '' ? $resolvedSupplierName : (string) $menuItem->title;
            $catalogTitle = $this->titleFormatter->catalogTitle($catalogSource);

            $beforeSupplierName = (string) ($menuItem->supplier_name ?? '');
            $beforeTitle = (string) $menuItem->title;

            $updates = [];

            if ($resolvedSupplierName !== '' && $beforeSupplierName !== $resolvedSupplierName) {
                $updates['supplier_name'] = $resolvedSupplierName;
            }

            if ($catalogTitle !== '' && $beforeTitle !== $catalogTitle) {
                $updates['title'] = $catalogTitle;
            }

            if ($updates !== []) {
                $menuItem->forceFill($updates)->save();
                $applied++;
            }

            if ($match !== null || $updates !== []) {
                $appliedRows[] = [
                    'menu_item_id' => (string) $menuItem->id,
                    'category' => $categoryName,
                    'price' => $this->normalizePrice((float) $menuItem->price),
                    'supplier_name_source' => $resolvedSupplierName,
                    'title_before' => $beforeTitle,
                    'title_after' => (string) ($updates['title'] ?? $beforeTitle),
                    'supplier_name_before' => $beforeSupplierName,
                    'supplier_name_after' => (string) ($updates['supplier_name'] ?? $beforeSupplierName),
                    'match_basis' => $matchBasis,
                ];
            }
        }

        $missingSupplierNameRows = MenuItem::query()
            ->with('category:id,name')
            ->where(function ($query): void {
                $query
                    ->whereNull('supplier_name')
                    ->orWhere('supplier_name', '');
            })
            ->orderBy('id')
            ->get()
            ->map(fn (MenuItem $item): array => [
                'menu_item_id' => (string) $item->id,
                'category' => (string) ($item->category?->name ?? ''),
                'price' => $this->normalizePrice((float) $item->price),
                'title' => (string) $item->title,
                'supplier_name' => (string) ($item->supplier_name ?? ''),
            ])
            ->all();

        $appliedReport = base_path('_menu_audit/reports/supplier-name-backfill-applied.csv');
        $reviewReport = base_path('_menu_audit/reports/supplier-name-backfill-needs-review.csv');
        $missingReport = base_path('_menu_audit/reports/menu-items-without-supplier-name.csv');

        $this->csv->write($appliedReport, [
            'menu_item_id',
            'category',
            'price',
            'supplier_name_source',
            'title_before',
            'title_after',
            'supplier_name_before',
            'supplier_name_after',
            'match_basis',
        ], $appliedRows);

        $this->csv->write($reviewReport, [
            'menu_item_id',
            'category',
            'price',
            'title',
            'supplier_name_current',
            'reason',
        ], $reviewRows);

        $this->csv->write($missingReport, [
            'menu_item_id',
            'category',
            'price',
            'title',
            'supplier_name',
        ], $missingSupplierNameRows);

        $filledSupplierName = MenuItem::query()
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->count();
        $missingSupplierName = MenuItem::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('supplier_name')
                    ->orWhere('supplier_name', '');
            })
            ->count();

        return [
            'source_path' => $resolvedSourcePath,
            'menu_items_total' => $menuItems->count(),
            'matched' => $matched,
            'applied' => $applied,
            'review' => count($reviewRows),
            'supplier_name_filled' => $filledSupplierName,
            'supplier_name_missing' => $missingSupplierName,
            'reports' => [
                'applied' => $appliedReport,
                'needs_review' => $reviewReport,
                'missing_supplier_name' => $missingReport,
            ],
        ];
    }

    private function resolveSourcePath(?string $sourcePath): string
    {
        $candidates = array_values(array_filter([
            $sourcePath,
            base_path('_menu_audit/incoming/Заказ еды в dvizh _ nethammer - Меню (3).csv'),
            base_path('_menu_audit/incoming/Заказ еды в dvizh _ nethammer - Меню.csv'),
            base_path('_menu_audit/incoming/Заказ еды в dvizh _ nethammer (3).xlsx'),
            base_path('_menu_audit/incoming/Заказ еды в dvizh _ nethammer.xlsx'),
        ], static fn (?string $path): bool => is_string($path) && $path !== ''));

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $fallback = collect(File::files(storage_path('app/private/menu-imports')))
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->sort()
            ->last();

        if (is_string($fallback) && is_file($fallback)) {
            return $fallback;
        }

        throw new RuntimeException('Не найден supplier CSV/XLSX для backfill supplier_name.');
    }

    /**
     * @return array<int, array{category: string, supplier_name: string, price: float}>
     */
    private function supplierRows(string $sourcePath): array
    {
        $extension = mb_strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->supplierRowsFromCsv($sourcePath),
            'xlsx' => $this->supplierRowsFromXlsx($sourcePath),
            default => throw new RuntimeException("Неподдерживаемый формат backfill файла: {$extension}."),
        };
    }

    /**
     * @return array<int, array{category: string, supplier_name: string, price: float}>
     */
    private function supplierRowsFromCsv(string $sourcePath): array
    {
        $handle = fopen($sourcePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Не удалось открыть supplier CSV: {$sourcePath}");
        }

        $firstLine = fgets($handle);
        $delimiter = $this->detectDelimiter($firstLine === false ? '' : $firstLine);
        rewind($handle);

        $rows = [];
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn (mixed $value): string => $this->normalizeEncoding((string) ($value ?? '')), $cells);
        }

        fclose($handle);

        return $this->extractSupplierRows($rows);
    }

    /**
     * @return array<int, array{category: string, supplier_name: string, price: float}>
     */
    private function supplierRowsFromXlsx(string $sourcePath): array
    {
        $reader = IOFactory::createReaderForFile($sourcePath);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($sourcePath)->getActiveSheet();
        $rawRows = $sheet->toArray(null, false, false, false);

        $rows = array_map(
            fn (array $cells): array => array_map(
                fn (mixed $value): string => $this->normalizeEncoding((string) ($value ?? '')),
                $cells,
            ),
            $rawRows,
        );

        return $this->extractSupplierRows($rows);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array{category: string, supplier_name: string, price: float}>
     */
    private function extractSupplierRows(array $rows): array
    {
        $items = [];
        $currentCategory = '';
        $headerFound = false;

        foreach ($rows as $row) {
            $nameCell = $this->normalizer->clean((string) ($row[0] ?? ''));
            $priceCell = $this->normalizer->clean((string) ($row[1] ?? ''));

            if (! $headerFound) {
                if ($this->normalizer->normalizeName($nameCell) === $this->normalizer->normalizeName('Наименование продукции')) {
                    $headerFound = true;
                }

                continue;
            }

            if ($nameCell === '') {
                continue;
            }

            $price = $this->parsePrice($priceCell);

            if ($price === null) {
                $currentCategory = $nameCell;

                continue;
            }

            $items[] = [
                'category' => $currentCategory,
                'supplier_name' => $nameCell,
                'price' => $price,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array{category: string, supplier_name: string, price: float}>  $rows
     * @return array<string, array<int, array{category: string, supplier_name: string, price: float}>>
     */
    private function buildSourceIndex(array $rows): array
    {
        $index = [];

        foreach ($rows as $row) {
            $category = $this->normalizer->normalizeName($row['category']);
            $price = $this->normalizePrice($row['price']);
            $supplierKey = $this->titleFormatter->normalizedNameKey($row['supplier_name']);
            $titleKey = $this->titleFormatter->normalizedNameKey($this->titleFormatter->catalogTitle($row['supplier_name']));

            $index["supplier|{$category}|{$price}|{$supplierKey}"][] = array_merge($row, [
                'match_basis' => 'category+price+supplier_name',
            ]);
            $index["title|{$category}|{$price}|{$titleKey}"][] = array_merge($row, [
                'match_basis' => 'category+price+title',
            ]);
        }

        return $index;
    }

    /**
     * @param  array<string, array<int, array{category: string, supplier_name: string, price: float, match_basis: string}>>  $index
     * @return array{category: string, supplier_name: string, price: float, match_basis: string}|null
     */
    private function matchSupplierRow(MenuItem $menuItem, string $categoryName, array $index): ?array
    {
        $category = $this->normalizer->normalizeName($categoryName);
        $price = $this->normalizePrice((float) $menuItem->price);

        $supplierName = $this->titleFormatter->supplierName((string) ($menuItem->supplier_name ?? ''));
        $supplierKey = $this->titleFormatter->normalizedNameKey($supplierName);
        $titleKey = $this->titleFormatter->normalizedNameKey((string) $menuItem->title);

        $candidates = [];

        if ($supplierKey !== '') {
            $candidates[] = "supplier|{$category}|{$price}|{$supplierKey}";
        }

        if ($titleKey !== '') {
            $candidates[] = "title|{$category}|{$price}|{$titleKey}";
            $candidates[] = "supplier|{$category}|{$price}|{$titleKey}";
        }

        foreach ($candidates as $key) {
            $matches = $index[$key] ?? [];

            if (count($matches) === 1) {
                return $matches[0];
            }
        }

        return null;
    }

    private function detectDelimiter(string $line): string
    {
        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');

        return $semicolon >= $comma ? ';' : ',';
    }

    private function normalizeEncoding(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1251,ISO-8859-1');
    }

    private function parsePrice(string $value): ?float
    {
        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/(?:₽|руб\.?|р\.?)$/ui', '', $normalized) ?? $normalized;

        if ($normalized === '' || preg_match('/^\d+(?:\.\d+)?$/', $normalized) !== 1) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function normalizePrice(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
