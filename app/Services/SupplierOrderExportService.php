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
        return OrderItem::query()
            ->selectRaw(
                'order_items.title_snapshot,
                SUM(order_items.quantity) as quantity_sum,
                SUM(order_items.quantity * order_items.price_snapshot) as total_sum'
            )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.order_cycle_id', $cycle->id)
            ->where('orders.status', OrderStatus::Submitted->value)
            ->where('order_items.status', '!=', OrderItemStatus::Cancelled->value)
            ->groupBy('order_items.title_snapshot')
            ->orderBy('order_items.title_snapshot')
            ->get();
    }

    /**
     * @return array{
     *     cycle: array{id: int, title: string, starts_at: ?string, closes_at: ?string},
     *     rows: array<int, array{title: string, quantity: int, total_price: float}>,
     *     totals: array{rows_count: int, total_quantity: int, total_price: float}
     * }
     */
    public function snapshotForCycle(OrderCycle $cycle): array
    {
        $rows = $this->rowsForCycle($cycle)
            ->map(fn (OrderItem $row): array => [
                'title' => $row->title_snapshot,
                'quantity' => (int) $row->quantity_sum,
                'total_price' => round((float) $row->total_sum, 2),
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
            ->sum(fn (OrderItem $row): float => (float) $row->total_sum);
    }

    public function csvForExport(SupplierOrderExport $export): string
    {
        $handle = fopen('php://temp', 'wb+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Блюдо', 'Категория', 'Количество', 'Цена', 'Сумма', 'Комментарий'], ';');

        foreach ($export->snapshotRows() as $row) {
            fputcsv($handle, [
                $this->escapeCsvCell($row['title']),
                $this->escapeCsvCell((string) ($row['category'] ?? '')),
                $row['quantity'],
                number_format($row['unit_price'], 2, '.', ''),
                number_format($row['total_price'], 2, '.', ''),
                $this->escapeCsvCell((string) ($row['comment'] ?? '')),
            ], ';');
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
}
