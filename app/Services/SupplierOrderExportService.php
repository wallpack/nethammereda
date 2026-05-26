<?php

namespace App\Services;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SupplierOrderCannotBeSentException;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\SupplierOrderExport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierOrderExportService
{
    private const CSV_DELIMITER = ';';

    public function sendToSupplier(OrderCycle $cycle, ?User $exportedBy = null, string $format = 'csv'): SupplierOrderExport
    {
        return DB::transaction(function () use ($cycle, $exportedBy, $format): SupplierOrderExport {
            $cycle = OrderCycle::query()
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            if ($cycle->status !== OrderCycleStatus::Closed) {
                throw SupplierOrderCannotBeSentException::forCycleStatus($cycle);
            }

            $snapshot = $this->snapshotForCycle($cycle);
            $totals = $snapshot['totals'];

            if ($totals['rows_count'] === 0 || $totals['total_quantity'] === 0) {
                throw SupplierOrderCannotBeSentException::forEmptyOrder();
            }

            $exportedAt = now();
            $export = SupplierOrderExport::query()->create([
                'order_cycle_id' => $cycle->id,
                'exported_by' => $exportedBy?->id,
                'exported_at' => $exportedAt,
                'rows_count' => $totals['rows_count'],
                'total_quantity' => $totals['total_quantity'],
                'total_price' => $totals['total_price'],
                'format' => $format,
                'snapshot_json' => $snapshot,
            ]);

            $cycle->transitionTo(OrderCycleStatus::SentToSupplier, [
                'sent_to_supplier_at' => $cycle->sent_to_supplier_at ?? $exportedAt,
                'sent_to_supplier_by' => $cycle->sent_to_supplier_by ?? $exportedBy?->id,
            ]);

            return $export->fresh(['orderCycle', 'exportedBy']);
        });
    }

    public function rowsForCycle(OrderCycle $cycle): Collection
    {
        $rows = OrderItem::query()
            ->selectRaw(
                'orders.user_id,
                users.full_name as user_full_name,
                users.name as user_name,
                users.email as user_email,
                order_items.title_snapshot,
                order_items.supplier_name_snapshot,
                menu_items.supplier_name as menu_item_supplier_name,
                menu_items.title as menu_item_title,
                order_items.price_snapshot,
                SUM(order_items.quantity) as quantity_sum,
                SUM(order_items.quantity * order_items.price_snapshot) as total_sum'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.order_cycle_id', $cycle->id)
            ->where('orders.status', OrderStatus::Submitted->value)
            ->where('order_items.status', '!=', OrderItemStatus::Cancelled->value)
            ->groupBy([
                'orders.user_id',
                'users.full_name',
                'users.name',
                'users.email',
                'order_items.title_snapshot',
                'order_items.supplier_name_snapshot',
                'menu_items.supplier_name',
                'menu_items.title',
                'order_items.price_snapshot',
            ])
            ->orderBy('orders.user_id')
            ->orderBy('order_items.title_snapshot')
            ->get()
            ->map(function (OrderItem $row): array {
                $supplierName = $this->supplierNameForExport(
                    $row->supplier_name_snapshot,
                    $row->menu_item_supplier_name,
                    $row->title_snapshot,
                    $row->menu_item_title,
                );
                $fullName = $this->displayNameForExport(
                    (int) $row->user_id,
                    $row->user_full_name,
                    $row->user_name,
                    $row->user_email,
                );

                return [
                    'user_id' => (int) $row->user_id,
                    'full_name' => $fullName,
                    'supplier_name' => $supplierName,
                    'title_snapshot' => (string) $row->title_snapshot,
                    'price_snapshot' => round((float) $row->price_snapshot, 2),
                    'quantity' => (int) $row->quantity_sum,
                    'total_sum' => round((float) $row->total_sum, 2),
                ];
            })
            ->values();

        $previousUserId = null;

        return $rows->map(function (array $row) use (&$previousUserId): array {
            $resolvedName = $row['full_name'];
            $row['full_name'] = $previousUserId === $row['user_id'] ? '' : $resolvedName;
            $previousUserId = $row['user_id'];

            return $row;
        });
    }

    /**
     * @return array{
     *     cycle: array{id: int, title: string, starts_at: ?string, closes_at: ?string},
     *     rows: array<int, array{
     *         full_name: string,
     *         title: string,
     *         supplier_name: string,
     *         catalog_title: string,
     *         unit_price: float,
     *         quantity: int,
     *         total_price: float
     *     }>,
     *     totals: array{rows_count: int, total_quantity: int, total_price: float}
     * }
     */
    public function snapshotForCycle(OrderCycle $cycle): array
    {
        $rows = $this->rowsForCycle($cycle)
            ->map(fn (array $row): array => [
                'full_name' => (string) $row['full_name'],
                'title' => (string) $row['supplier_name'],
                'supplier_name' => (string) $row['supplier_name'],
                'catalog_title' => (string) $row['title_snapshot'],
                'unit_price' => round((float) $row['price_snapshot'], 2),
                'quantity' => (int) $row['quantity'],
                'total_price' => round((float) $row['total_sum'], 2),
            ])
            ->values();

        return [
            'cycle' => [
                'id' => $cycle->id,
                'title' => $cycle->title,
                'starts_at' => $cycle->starts_at?->toDateTimeString(),
                'closes_at' => $cycle->closes_at?->toDateTimeString(),
            ],
            'rows' => $rows->all(),
            'totals' => [
                'rows_count' => $rows->count(),
                'total_quantity' => $rows->sum('quantity'),
                'total_price' => round((float) $rows->sum('total_price'), 2),
            ],
        ];
    }

    public function totalForCycle(OrderCycle $cycle): float
    {
        return (float) $this->rowsForCycle($cycle)
            ->sum(fn (array $row): float => (float) $row['total_sum']);
    }

    public function csvForCycle(OrderCycle $cycle): string
    {
        return $this->csvFromRows($this->snapshotForCycle($cycle)['rows']);
    }

    public function csvForExport(SupplierOrderExport $export): string
    {
        return $this->csvFromRows($export->snapshotRows());
    }

    /**
     * @param  iterable<int, array{
     *     full_name?: mixed,
     *     title?: mixed,
     *     supplier_name?: mixed,
     *     catalog_title?: mixed,
     *     unit_price?: mixed,
     *     quantity?: mixed,
     *     total_price?: mixed
     * }>  $rows
     */
    private function csvFromRows(iterable $rows): string
    {
        $handle = fopen('php://temp', 'wb+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ФИО', 'Наименование', 'Цена', 'количество', 'Сумма'], self::CSV_DELIMITER);

        foreach ($rows as $row) {
            $nameForSupplier = $this->supplierNameForExport(
                is_scalar($row['supplier_name'] ?? null) ? (string) $row['supplier_name'] : null,
                is_scalar($row['title'] ?? null) ? (string) $row['title'] : null,
                is_scalar($row['catalog_title'] ?? null) ? (string) $row['catalog_title'] : null,
                null,
            );
            $quantity = (int) ($row['quantity'] ?? 0);
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            $sum = array_key_exists('total_price', $row)
                ? round((float) ($row['total_price'] ?? 0), 2)
                : round($unitPrice * $quantity, 2);

            fputcsv($handle, [
                $this->escapeCsvCell((string) ($row['full_name'] ?? '')),
                $this->escapeCsvCell($nameForSupplier),
                $this->formatNumber($unitPrice),
                (string) $quantity,
                $this->formatNumber($sum),
            ], self::CSV_DELIMITER);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents === false ? '' : $contents;
    }

    private function escapeCsvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) === 1
            ? "'{$value}"
            : $value;
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function displayNameForExport(int $userId, ?string $fullName, ?string $name, ?string $email): string
    {
        $candidates = [
            $this->trimToNullable($fullName),
            $this->trimToNullable($name),
            $this->trimToNullable($email),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return "Пользователь #{$userId}";
    }

    private function trimToNullable(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function supplierNameForExport(
        ?string $supplierNameSnapshot,
        ?string $menuItemSupplierName,
        ?string $titleSnapshot,
        ?string $menuItemTitle,
    ): string {
        $candidates = [
            $this->trimToNullable($supplierNameSnapshot),
            $this->trimToNullable($menuItemSupplierName),
            $this->trimToNullable($titleSnapshot),
            $this->trimToNullable($menuItemTitle),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return 'Без названия';
    }
}
