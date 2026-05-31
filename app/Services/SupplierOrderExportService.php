<?php

namespace App\Services;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SupplierOrderCannotBeSentException;
use App\Models\MenuItem;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\SupplierOrderExport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SupplierOrderExportService
{
    private const CSV_DELIMITER = ';';
    private const XLSX_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const ITEM_TABLE_HEADERS = ['Наименование', 'Вес', 'Цена', 'Количество', 'Сумма'];

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
                'orders.user_id,
                users.full_name as user_full_name,
                users.name as user_name,
                users.email as user_email,
                order_items.title_snapshot,
                order_items.supplier_name_snapshot,
                menu_items.supplier_name as menu_item_supplier_name,
                menu_items.title as menu_item_title,
                MAX(NULLIF(menu_items.weight, \'\')) as menu_item_weight,
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
                    'weight' => $this->weightForExport($row->menu_item_weight, $row->menu_item_supplier_name),
                    'price_snapshot' => round((float) $row->price_snapshot, 2),
                    'quantity' => (int) $row->quantity_sum,
                    'total_sum' => round((float) $row->total_sum, 2),
                ];
            })
            ->values();
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
                'weight' => (string) $row['weight'],
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

    public function xlsxForCycle(OrderCycle $cycle): string
    {
        return $this->xlsxFromRows($this->snapshotForCycle($cycle)['rows']);
    }

    public function xlsxForExport(SupplierOrderExport $export): string
    {
        return $this->xlsxFromRows($export->snapshotRows());
    }

    public static function xlsxMimeType(): string
    {
        return self::XLSX_MIME_TYPE;
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

        foreach ($this->buildExportRows($rows) as $row) {
            fputcsv($handle, $this->csvCellsForRow($row), self::CSV_DELIMITER);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents === false ? '' : $contents;
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
    private function xlsxFromRows(iterable $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $content = null;

        try {
            $sheet->getColumnDimension('A')->setWidth(28);
            $sheet->getColumnDimension('B')->setWidth(75);
            $sheet->getColumnDimension('C')->setWidth(12);
            $sheet->getColumnDimension('D')->setWidth(14);
            $sheet->getColumnDimension('E')->setWidth(14);
            $sheet->getColumnDimension('F')->setWidth(14);
            $sheet->freezePane('A2');

            $rowNumber = 1;
            $tableStartRow = null;
            $tableRanges = [];

            foreach ($this->buildExportRows($rows) as $row) {
                $type = (string) ($row['type'] ?? '');
                $quantity = (int) ($row['quantity'] ?? 0);
                $totalPrice = round((float) ($row['total_price'] ?? 0), 2);

                if ($type === 'employee_title') {
                    $sheet->setCellValueExplicit(
                        "A{$rowNumber}",
                        (string) ($row['full_name'] ?? ''),
                        DataType::TYPE_STRING,
                    );
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFEFF6FF');
                }

                if ($type === 'table_header') {
                    $sheet->setCellValueExplicit("B{$rowNumber}", self::ITEM_TABLE_HEADERS[0], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("C{$rowNumber}", self::ITEM_TABLE_HEADERS[1], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("D{$rowNumber}", self::ITEM_TABLE_HEADERS[2], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("E{$rowNumber}", self::ITEM_TABLE_HEADERS[3], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("F{$rowNumber}", self::ITEM_TABLE_HEADERS[4], DataType::TYPE_STRING);
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")->getFont()->setBold(true);
                    $tableStartRow = $rowNumber;
                }

                if ($type === 'item') {
                    $sheet->setCellValueExplicit("B{$rowNumber}", (string) ($row['title'] ?? ''), DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("C{$rowNumber}", (string) ($row['weight'] ?? ''), DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$rowNumber}", round((float) ($row['unit_price'] ?? 0), 2));
                    $sheet->setCellValue("E{$rowNumber}", $quantity);
                    $sheet->setCellValue("F{$rowNumber}", $totalPrice);
                }

                if ($type === 'employee_total') {
                    $sheet->setCellValueExplicit("A{$rowNumber}", 'Итого по сотруднику', DataType::TYPE_STRING);
                    $sheet->setCellValue("E{$rowNumber}", $quantity);
                    $sheet->setCellValue("F{$rowNumber}", $totalPrice);
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")->getFont()->setBold(true);

                    if (is_int($tableStartRow)) {
                        $tableRanges[] = "A{$tableStartRow}:F{$rowNumber}";
                        $tableStartRow = null;
                    }
                }

                if ($type === 'grand_total') {
                    $sheet->setCellValueExplicit("A{$rowNumber}", 'ИТОГО ПО ВСЕМ', DataType::TYPE_STRING);
                    $sheet->setCellValue("E{$rowNumber}", $quantity);
                    $sheet->setCellValue("F{$rowNumber}", $totalPrice);
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$rowNumber}:F{$rowNumber}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFF8FAFC');
                }

                $rowNumber++;
            }

            $lastDataRow = max(1, $rowNumber - 1);
            $sheet->getStyle("B1:B{$lastDataRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("D1:D{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $sheet->getStyle("F1:F{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');

            foreach ($tableRanges as $range) {
                $sheet->getStyle($range)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            }

            $writer = new Xlsx($spreadsheet);
            ob_start();
            $writer->save('php://output');
            $content = ob_get_clean();
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return is_string($content) ? $content : '';
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
     * @return array<int, array{
     *     type: string,
     *     full_name?: string,
     *     title?: string,
     *     unit_price?: float,
     *     quantity?: int,
     *     total_price?: float
     * }>
     */
    private function buildExportRows(iterable $rows): array
    {
        $normalizedRows = $this->normalizeExportSourceRows($rows);
        $groups = [];
        $orderedNames = [];
        $grandQuantity = 0;
        $grandTotal = 0.0;

        foreach ($normalizedRows as $row) {
            $fullName = $row['full_name'];

            if (! array_key_exists($fullName, $groups)) {
                $groups[$fullName] = [
                    'items' => [],
                    'quantity' => 0,
                    'total_price' => 0.0,
                ];
                $orderedNames[] = $fullName;
            }

            $groups[$fullName]['items'][] = $row;
            $groups[$fullName]['quantity'] += $row['quantity'];
            $groups[$fullName]['total_price'] += $row['total_price'];
            $grandQuantity += $row['quantity'];
            $grandTotal += $row['total_price'];
        }

        $exportRows = [];

        foreach ($orderedNames as $fullName) {
            $group = $groups[$fullName];
            $exportRows[] = [
                'type' => 'employee_title',
                'full_name' => $fullName,
            ];
            $exportRows[] = ['type' => 'table_header'];

            foreach ($group['items'] as $item) {
                $exportRows[] = [
                    'type' => 'item',
                    'title' => $item['title'],
                    'weight' => $item['weight'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'total_price' => round((float) $item['total_price'], 2),
                ];
            }

            $exportRows[] = [
                'type' => 'employee_total',
                'quantity' => (int) $group['quantity'],
                'total_price' => round((float) $group['total_price'], 2),
            ];
            $exportRows[] = ['type' => 'separator'];
        }

        $exportRows[] = [
            'type' => 'grand_total',
            'quantity' => $grandQuantity,
            'total_price' => round($grandTotal, 2),
        ];

        return $exportRows;
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
     * @return array<int, array{
     *     full_name: string,
     *     title: string,
     *     unit_price: float,
     *     quantity: int,
     *     total_price: float
     * }>
     */
    private function normalizeExportSourceRows(iterable $rows): array
    {
        $normalized = [];
        $previousFullName = null;

        foreach ($rows as $row) {
            $nameForSupplier = $this->supplierNameForExport(
                is_scalar($row['supplier_name'] ?? null) ? (string) $row['supplier_name'] : null,
                is_scalar($row['title'] ?? null) ? (string) $row['title'] : null,
                is_scalar($row['catalog_title'] ?? null) ? (string) $row['catalog_title'] : null,
                null,
            );
            $currentFullName = is_scalar($row['full_name'] ?? null)
                ? $this->trimToNullable((string) $row['full_name'])
                : null;

            if ($currentFullName !== null) {
                $previousFullName = $currentFullName;
            }

            $fullName = $currentFullName ?? $previousFullName ?? 'Пользователь';
            $weight = is_scalar($row['weight'] ?? null)
                ? $this->trimToNullable((string) $row['weight'])
                : null;
            $quantity = max(0, (int) ($row['quantity'] ?? 0));
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            $sum = array_key_exists('total_price', $row)
                ? round((float) ($row['total_price'] ?? 0), 2)
                : round($unitPrice * $quantity, 2);

            $normalized[] = [
                'full_name' => $fullName,
                'title' => $nameForSupplier,
                'weight' => $weight ?? '',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => $sum,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{
     *     type: string,
     *     full_name?: string,
     *     title?: string,
     *     unit_price?: float,
     *     quantity?: int,
     *     total_price?: float
     * }  $row
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}
     */
    private function csvCellsForRow(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $quantity = isset($row['quantity']) ? (string) ((int) $row['quantity']) : '';
        $total = isset($row['total_price']) ? $this->formatNumber((float) $row['total_price']) : '';
        $unitPrice = isset($row['unit_price']) ? $this->formatNumber((float) $row['unit_price']) : '';

        $cells = match ($type) {
            'employee_title' => [
                (string) ($row['full_name'] ?? ''),
                '',
                '',
                '',
                '',
                '',
            ],
            'table_header' => [
                '',
                self::ITEM_TABLE_HEADERS[0],
                self::ITEM_TABLE_HEADERS[1],
                self::ITEM_TABLE_HEADERS[2],
                self::ITEM_TABLE_HEADERS[3],
                self::ITEM_TABLE_HEADERS[4],
            ],
            'item' => [
                '',
                (string) ($row['title'] ?? ''),
                (string) ($row['weight'] ?? ''),
                $unitPrice,
                $quantity,
                $total,
            ],
            'employee_total' => [
                'Итого по сотруднику',
                '',
                '',
                '',
                $quantity,
                $total,
            ],
            'grand_total' => [
                'ИТОГО ПО ВСЕМ',
                '',
                '',
                '',
                $quantity,
                $total,
            ],
            default => ['', '', '', '', '', ''],
        };

        return array_map(fn (string $value): string => $this->escapeCsvCell($value), $cells);
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

    private function weightForExport(mixed $menuItemWeight, mixed $menuItemSupplierName): string
    {
        $menuItem = new MenuItem([
            'weight' => is_scalar($menuItemWeight) ? (string) $menuItemWeight : null,
            'supplier_name' => is_scalar($menuItemSupplierName) ? (string) $menuItemSupplierName : null,
        ]);

        return $menuItem->display_weight ?? '';
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
