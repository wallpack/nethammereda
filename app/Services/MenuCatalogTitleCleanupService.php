<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Services\MenuAudit\CsvReportWriter;
use App\Support\MenuCatalogTitleFormatter;
use Illuminate\Support\Facades\File;

class MenuCatalogTitleCleanupService
{
    public function __construct(
        private readonly MenuCatalogTitleFormatter $formatter,
        private readonly CsvReportWriter $csv,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     reviewed: int,
     *     changed: int,
     *     needs_review: int,
     *     reports: array{
     *         review: string,
     *         applied: string,
     *         needs_review: string
     *     }
     * }
     */
    public function run(bool $apply = true, string $reportPrefix = 'catalog-title-cleanup'): array
    {
        $items = MenuItem::query()
            ->with('category:id,name')
            ->orderBy('id')
            ->get();

        $reviewRows = [];
        $appliedRows = [];
        $needsReviewRows = [];
        $changed = 0;

        foreach ($items as $item) {
            $category = (string) ($item->category?->name ?? '');
            $currentTitle = trim((string) $item->title);
            $supplierName = trim((string) ($item->supplier_name ?? ''));
            $suggestedTitle = $this->formatter->catalogTitle($supplierName !== '' ? $supplierName : $currentTitle);
            $currentIssues = $this->detectIssues($currentTitle);

            if ($currentIssues !== []) {
                $reviewRows[] = [
                    'id' => (string) $item->id,
                    'category' => $category,
                    'title' => $currentTitle,
                    'supplier_name' => $supplierName,
                    'price' => $this->formatPrice((float) $item->price),
                    'issue' => implode('|', $currentIssues),
                    'suggested_title' => $suggestedTitle,
                ];
            }

            if ($suggestedTitle === '' || $suggestedTitle === $currentTitle) {
                if ($currentIssues !== []) {
                    $needsReviewRows[] = [
                        'id' => (string) $item->id,
                        'category' => $category,
                        'old_title' => $currentTitle,
                        'new_title' => $currentTitle,
                        'supplier_name' => $supplierName,
                        'price' => $this->formatPrice((float) $item->price),
                        'issue' => implode('|', $currentIssues),
                        'suggested_title' => $suggestedTitle,
                    ];
                }

                continue;
            }

            $suggestedIssues = $this->detectIssues($suggestedTitle);

            if ($this->hasBlockingIssues($suggestedIssues)) {
                $needsReviewRows[] = [
                    'id' => (string) $item->id,
                    'category' => $category,
                    'old_title' => $currentTitle,
                    'new_title' => $suggestedTitle,
                    'supplier_name' => $supplierName,
                    'price' => $this->formatPrice((float) $item->price),
                    'issue' => implode('|', $suggestedIssues),
                    'suggested_title' => $suggestedTitle,
                ];

                continue;
            }

            if ($apply) {
                $item->forceFill([
                    'title' => $suggestedTitle,
                ])->save();

                $changed++;
            }

            $appliedRows[] = [
                'id' => (string) $item->id,
                'category' => $category,
                'old_title' => $currentTitle,
                'new_title' => $suggestedTitle,
                'supplier_name' => $supplierName,
                'price' => $this->formatPrice((float) $item->price),
            ];
        }

        $reportDir = base_path('_menu_audit/reports');
        File::ensureDirectoryExists($reportDir);

        $safePrefix = trim($reportPrefix);
        $safePrefix = $safePrefix === '' ? 'catalog-title-cleanup' : $safePrefix;
        $safePrefix = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $safePrefix) ?? $safePrefix;

        $reviewPath = base_path("_menu_audit/reports/{$safePrefix}-review.csv");
        $appliedPath = base_path("_menu_audit/reports/{$safePrefix}-applied.csv");
        $needsReviewPath = base_path("_menu_audit/reports/{$safePrefix}-needs-review.csv");

        $this->csv->write($reviewPath, [
            'id',
            'category',
            'title',
            'supplier_name',
            'price',
            'issue',
            'suggested_title',
        ], $reviewRows);

        $this->csv->write($appliedPath, [
            'id',
            'category',
            'old_title',
            'new_title',
            'supplier_name',
            'price',
        ], $appliedRows);

        $this->csv->write($needsReviewPath, [
            'id',
            'category',
            'old_title',
            'new_title',
            'supplier_name',
            'price',
            'issue',
            'suggested_title',
        ], $needsReviewRows);

        return [
            'total' => $items->count(),
            'reviewed' => count($reviewRows),
            'changed' => $changed,
            'needs_review' => count($needsReviewRows),
            'reports' => [
                'review' => $reviewPath,
                'applied' => $appliedPath,
                'needs_review' => $needsReviewPath,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detectIssues(string $title): array
    {
        $checks = [
            'quotes' => '/["«»]/u',
            'quote_no_space' => '/"(?=\p{L})/u',
            'grams' => '/\b\d+[.,]?\d*\s*\.?\s*(?:г|гр|мл)\.?\b/ui',
            'pieces' => '/\b\d+\s*шт\.?\b|\bшт\.?\b/ui',
            'parentheses' => '/[()]/u',
            'empty_parentheses' => '/\(\s*\)/u',
            'novinka' => '/\bновинка\b/ui',
            'gms' => '/\bГМС\b/ui',
            'triangle' => '/\bтреугольн(?:ый|ая|ое|ые)\b/ui',
            'combo' => '/\bКомбо\./ui',
            'set_prefix' => '/\bС-?т\.?\b/ui',
            'double_spaces' => '/\s{2,}/u',
            'po_kievski_upper' => '/\bпо[\s-]*Киевски\b/u',
            'hot_dog_space' => '/\bХот\s+дог\b/u',
            'sgushch_short' => '/\bсгущ\.?\b/ui',
            'po_domashnemu_upper' => '/\bПо-домашнему\b/u',
            'pizza_upper_s' => '/\bПицца\s+С\b/u',
            'pecheny_without_yo' => '/\bпеченый\b/ui',
            'zelenoy_without_yo' => '/\bзеленой\b/ui',
            'short_hrust' => '/\bхруст\./ui',
            'short_zapech_kartof' => '/\bзапеч\.?\s*картоф\b/ui',
            'short_kart_pure' => '/\bкарт\.\s*пюре\b/ui',
            'bad_ending' => '/[.,;:\-\(]\s*$/u',
        ];

        $issues = [];

        foreach ($checks as $name => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                $issues[] = $name;
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function hasBlockingIssues(array $issues): bool
    {
        $blocking = [
            'grams',
            'pieces',
            'novinka',
            'gms',
            'triangle',
            'combo',
            'set_prefix',
            'double_spaces',
            'po_kievski_upper',
            'hot_dog_space',
            'sgushch_short',
            'quote_no_space',
            'empty_parentheses',
            'po_domashnemu_upper',
            'pizza_upper_s',
            'pecheny_without_yo',
            'zelenoy_without_yo',
            'short_hrust',
            'short_zapech_kartof',
            'short_kart_pure',
            'bad_ending',
        ];

        foreach ($issues as $issue) {
            if (in_array($issue, $blocking, true)) {
                return true;
            }
        }

        return false;
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
