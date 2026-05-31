<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_cycle_id',
        'exported_by',
        'exported_at',
        'rows_count',
        'total_quantity',
        'total_price',
        'format',
        'file_path',
        'snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
            'rows_count' => 'integer',
            'total_quantity' => 'integer',
            'total_price' => 'decimal:2',
            'snapshot_json' => 'array',
        ];
    }

    public function orderCycle(): BelongsTo
    {
        return $this->belongsTo(OrderCycle::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public function rowsCount(): int
    {
        return (int) ($this->snapshotTotals()['rows_count'] ?? $this->rows_count ?? 0);
    }

    public function totalQuantity(): int
    {
        return (int) ($this->snapshotTotals()['total_quantity'] ?? $this->total_quantity ?? 0);
    }

    public function totalPrice(): float
    {
        return (float) ($this->snapshotTotals()['total_price'] ?? $this->total_price ?? 0);
    }

    public function cycleTitle(): string
    {
        $title = data_get($this->snapshot_json, 'cycle.title')
            ?? $this->orderCycle?->title;

        return filled($title) ? (string) $title : 'Цикл удален';
    }

    public static function dishNameWithGrammage(mixed $title, mixed $weight): string
    {
        $name = is_scalar($title) ? trim((string) $title) : '';

        if ($name === '') {
            return '';
        }

        $normalizedName = self::normalizeExistingGrammageParentheses($name);
        $grammage = self::compactGrammage($weight);

        if ($grammage === null || self::containsGrammageParentheses($name)) {
            return $normalizedName;
        }

        return "{$normalizedName} ({$grammage})";
    }

    /**
     * @return array<int, array{
     *     full_name: string,
     *     title: string,
     *     supplier_name: string,
     *     category: ?string,
     *     weight: string,
     *     quantity: int,
     *     unit_price: float,
     *     total_price: float,
     *     comment: ?string
     * }>
     */
    public function snapshotRows(): array
    {
        $rows = data_get($this->snapshot_json, 'rows', []);

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                $quantity = (int) ($row['quantity'] ?? 0);
                $totalPrice = round((float) ($row['total_price'] ?? $row['sum'] ?? 0), 2);
                $unitPrice = array_key_exists('unit_price', $row)
                    ? (float) $row['unit_price']
                    : (array_key_exists('price', $row) ? (float) $row['price'] : null);
                $unitPrice = $unitPrice ?? ($quantity > 0 ? round($totalPrice / $quantity, 2) : 0.0);
                $comment = $row['comment'] ?? $row['metadata'] ?? null;
                $fullName = $row['full_name'] ?? $row['fio'] ?? '';
                $supplierName = $row['supplier_name'] ?? $row['supplier_title'] ?? $row['title'] ?? $row['dish'] ?? '';
                $title = $row['title'] ?? $row['dish'] ?? $supplierName;
                $weight = $row['weight'] ?? $row['display_weight'] ?? $row['grammage'] ?? null;
                $displayWeight = is_scalar($weight) && filled($weight) ? (string) $weight : '';

                return [
                    'full_name' => is_string($fullName) ? $fullName : '',
                    'title' => self::dishNameWithGrammage($title, $displayWeight),
                    'supplier_name' => is_string($supplierName) ? $supplierName : (string) $supplierName,
                    'category' => filled($row['category'] ?? null) ? (string) $row['category'] : null,
                    'weight' => $displayWeight,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'total_price' => $totalPrice,
                    'comment' => is_scalar($comment) && filled($comment) ? (string) $comment : null,
                ];
            })
            ->values()
            ->all();
    }

    private static function compactGrammage(mixed $weight): ?string
    {
        if (! is_scalar($weight)) {
            return null;
        }

        $text = trim((string) $weight);

        if ($text === '') {
            return null;
        }

        if (! preg_match('/^(\d+(?:[,.]\d+)?)\s*(г|гр|кг|мл|л|шт)\.?$/iu', $text, $matches)) {
            return null;
        }

        $unit = mb_strtolower($matches[2]);
        $unit = $unit === 'гр' ? 'г' : $unit;

        return str_replace('.', ',', $matches[1]).$unit;
    }

    private static function containsGrammageParentheses(string $title): bool
    {
        return preg_match(self::grammageParenthesesPattern(), $title) === 1;
    }

    private static function normalizeExistingGrammageParentheses(string $title): string
    {
        return preg_replace_callback(
            self::grammageParenthesesPattern(),
            static function (array $matches): string {
                $unit = mb_strtolower($matches[2]);
                $unit = $unit === 'гр' ? 'г' : $unit;

                return '('.str_replace('.', ',', $matches[1]).$unit.')';
            },
            $title,
        ) ?? $title;
    }

    private static function grammageParenthesesPattern(): string
    {
        return '/\(\s*(\d+(?:[,.]\d+)?)\s*(г|гр|кг|мл|л|шт)\.?\s*\)/iu';
    }

    /**
     * @return array{rows_count: int, total_quantity: int, total_price: float}
     */
    public function snapshotTotals(): array
    {
        $totals = data_get($this->snapshot_json, 'totals', []);
        $rows = $this->snapshotRows();

        return [
            'rows_count' => (int) ($totals['rows_count'] ?? $this->rows_count ?? count($rows)),
            'total_quantity' => (int) ($totals['total_quantity'] ?? $this->total_quantity ?? collect($rows)->sum('quantity')),
            'total_price' => round((float) ($totals['total_price'] ?? $this->total_price ?? collect($rows)->sum('total_price')), 2),
        ];
    }
}
