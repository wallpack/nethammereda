<?php

namespace App\Services\MenuAudit;

use App\Models\MenuItem;
use App\Support\MenuTextNormalizer;
use Illuminate\Support\Facades\File;

class MenuAuditService
{
    public function __construct(
        private readonly SupplierMenuParser $parser,
        private readonly MenuTextNormalizer $normalizer,
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function auditHtml(string $html): array
    {
        $auditDirectory = storage_path('app/menu-audit');
        File::ensureDirectoryExists($auditDirectory);

        $siteRows = $this->siteRows($this->parser->parse($html));
        $localRows = $this->localRows();
        $siteByName = $this->groupBy($siteRows, 'normalized_name');
        $localByName = $this->groupBy($localRows, 'normalized_name');
        $missing = array_values(array_filter(
            $siteRows,
            fn (array $row): bool => ! isset($localByName[$row['normalized_name']]),
        ));
        $extra = array_values(array_filter(
            $localRows,
            fn (array $row): bool => ! isset($siteByName[$row['normalized_name']]),
        ));
        $duplicateRows = $this->duplicateRows($localByName);
        $categoryMismatches = $this->categoryMismatches($siteRows, $localByName);

        $this->csv->write($auditDirectory.'/site-menu.csv', [
            'category',
            'name',
            'shelf_life',
            'weight',
            'calories',
            'proteins',
            'fats',
            'carbs',
            'composition',
            'image_url',
            'normalized_name',
            'normalized_category',
        ], $siteRows);
        $this->csv->write($auditDirectory.'/local-menu.csv', [
            'id',
            'category',
            'name',
            'description',
            'composition',
            'weight',
            'calories',
            'proteins',
            'fats',
            'carbs',
            'price',
            'image_url',
            'image_path',
            'is_active',
            'normalized_name',
            'normalized_category',
        ], $localRows);
        $this->csv->write($auditDirectory.'/missing-in-local.csv', [
            'category',
            'name',
            'shelf_life',
            'weight',
            'calories',
            'proteins',
            'fats',
            'carbs',
            'composition',
            'image_url',
            'normalized_name',
        ], $missing);
        $this->csv->write($auditDirectory.'/extra-in-local.csv', [
            'id',
            'category',
            'name',
            'price',
            'is_active',
            'normalized_name',
        ], $extra);
        $this->csv->write($auditDirectory.'/duplicates-local.csv', [
            'type',
            'normalized_name',
            'left_id',
            'left_name',
            'left_category',
            'right_id',
            'right_name',
            'right_category',
            'similarity',
        ], $duplicateRows);
        $this->csv->write($auditDirectory.'/category-mismatches.csv', [
            'name',
            'site_category',
            'local_id',
            'local_category',
            'normalized_name',
        ], $categoryMismatches);

        $summary = [
            'site_items' => count($siteRows),
            'local_items' => count($localRows),
            'missing_in_local' => count($missing),
            'extra_in_local' => count($extra),
            'duplicate_local_groups' => count(array_filter($localByName, static fn (array $rows): bool => count($rows) > 1)),
            'duplicate_local_rows' => count($duplicateRows),
            'category_mismatches' => count($categoryMismatches),
            'safe_missing_action' => 'report_only_no_supplier_price',
        ];

        file_put_contents(
            $auditDirectory.'/menu-audit-summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function siteRows(array $items): array
    {
        return array_map(function (array $item): array {
            $category = $this->normalizer->normalizeCategory((string) ($item['category'] ?? ''));
            $name = $this->normalizer->clean((string) ($item['name'] ?? ''));

            return array_merge($item, [
                'category' => $category,
                'name' => $name,
                'normalized_name' => $this->normalizer->normalizeName($name),
                'normalized_category' => $this->normalizer->normalizeName($category),
            ]);
        }, $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function localRows(): array
    {
        return MenuItem::query()
            ->with('category:id,name')
            ->orderBy('title')
            ->get()
            ->map(function (MenuItem $item): array {
                $category = $item->category?->name ?? '';

                return [
                    'id' => $item->id,
                    'category' => $category,
                    'name' => $item->title,
                    'description' => $item->description,
                    'composition' => $item->composition,
                    'weight' => $item->weight,
                    'calories' => $item->calories,
                    'proteins' => $item->proteins,
                    'fats' => $item->fats,
                    'carbs' => $item->carbs,
                    'price' => $item->price,
                    'image_url' => $item->image_url,
                    'image_path' => $item->image_path,
                    'is_active' => $item->is_active ? '1' : '0',
                    'normalized_name' => $this->normalizer->normalizeName($item->title),
                    'normalized_category' => $this->normalizer->normalizeName($category),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBy(array $rows, string $key): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row[$key]][] = $row;
        }

        return $groups;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $localByName
     * @return array<int, array<string, mixed>>
     */
    private function duplicateRows(array $localByName): array
    {
        $rows = [];

        foreach ($localByName as $normalizedName => $items) {
            if (count($items) < 2) {
                continue;
            }

            for ($i = 0; $i < count($items); $i++) {
                for ($j = $i + 1; $j < count($items); $j++) {
                    $rows[] = [
                        'type' => 'exact',
                        'normalized_name' => $normalizedName,
                        'left_id' => $items[$i]['id'],
                        'left_name' => $items[$i]['name'],
                        'left_category' => $items[$i]['category'],
                        'right_id' => $items[$j]['id'],
                        'right_name' => $items[$j]['name'],
                        'right_category' => $items[$j]['category'],
                        'similarity' => '1.000',
                    ];
                }
            }
        }

        $allItems = array_merge(...array_values($localByName ?: [[]]));

        for ($i = 0; $i < count($allItems); $i++) {
            for ($j = $i + 1; $j < count($allItems); $j++) {
                if ($allItems[$i]['normalized_name'] === $allItems[$j]['normalized_name']) {
                    continue;
                }

                $similarity = $this->normalizer->similarity($allItems[$i]['name'], $allItems[$j]['name']);

                if ($similarity < 0.9) {
                    continue;
                }

                $rows[] = [
                    'type' => 'near',
                    'normalized_name' => $allItems[$i]['normalized_name'].' ~ '.$allItems[$j]['normalized_name'],
                    'left_id' => $allItems[$i]['id'],
                    'left_name' => $allItems[$i]['name'],
                    'left_category' => $allItems[$i]['category'],
                    'right_id' => $allItems[$j]['id'],
                    'right_name' => $allItems[$j]['name'],
                    'right_category' => $allItems[$j]['category'],
                    'similarity' => number_format($similarity, 3, '.', ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $siteRows
     * @param  array<string, array<int, array<string, mixed>>>  $localByName
     * @return array<int, array<string, mixed>>
     */
    private function categoryMismatches(array $siteRows, array $localByName): array
    {
        $rows = [];

        foreach ($siteRows as $siteRow) {
            foreach ($localByName[$siteRow['normalized_name']] ?? [] as $localRow) {
                if ($siteRow['normalized_category'] === $localRow['normalized_category']) {
                    continue;
                }

                $rows[] = [
                    'name' => $siteRow['name'],
                    'site_category' => $siteRow['category'],
                    'local_id' => $localRow['id'],
                    'local_category' => $localRow['category'],
                    'normalized_name' => $siteRow['normalized_name'],
                ];
            }
        }

        return $rows;
    }
}
