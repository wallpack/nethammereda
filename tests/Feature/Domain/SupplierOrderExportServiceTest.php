<?php

namespace Tests\Feature\Domain;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class SupplierOrderExportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function supplier_order_rows_include_submitted_order_item_snapshot_fields(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Чертова Е.Н.',
            'name' => 'Chertova',
            'email' => 'chertova@example.com',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Запеканка "Чиз"',
            quantity: 1,
            price: 130,
        );

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle);

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame('Чертова Е.Н.', $row['full_name']);
        $this->assertSame('Запеканка "Чиз"', $row['title_snapshot']);
        $this->assertSame(130.0, $row['price_snapshot']);
        $this->assertSame(1, $row['quantity']);
        $this->assertSame(130.0, $row['total_sum']);
    }

    #[Test]
    public function supplier_order_rows_include_menu_item_weight_for_export(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            title: 'Запеканка картофельнаяс куриным жульеном',
            quantity: 2,
            price: 156,
            menuWeight: '280 г',
        );

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle);
        $snapshot = app(SupplierOrderExportService::class)->snapshotForCycle($cycle);

        $this->assertSame('280 г', $rows->first()['weight']);
        $this->assertSame('280 г', $snapshot['rows'][0]['weight']);
        $this->assertSame(2, $snapshot['totals']['total_quantity']);
        $this->assertSame(312.0, $snapshot['totals']['total_price']);
    }

    #[Test]
    public function supplier_order_rows_use_empty_weight_fallback_when_menu_weight_is_missing(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            title: 'Лазанья домашняя',
            quantity: 1,
            price: 100,
            menuWeight: null,
        );

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle);
        $snapshot = app(SupplierOrderExportService::class)->snapshotForCycle($cycle);

        $this->assertSame('', $rows->first()['weight']);
        $this->assertSame('', $snapshot['rows'][0]['weight']);
    }

    #[Test]
    public function supplier_order_rows_group_by_user_and_keep_full_name_on_each_line(): void
    {
        $cycle = $this->createCycle();
        $userA = User::factory()->create([
            'full_name' => 'Чертова Е.Н.',
            'name' => 'User A',
            'email' => 'a@example.com',
        ]);
        $userB = User::factory()->create([
            'full_name' => 'Мекшун А.Н.',
            'name' => 'User B',
            'email' => 'b@example.com',
        ]);

        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userA, title: 'Пицца "Мини"', quantity: 1, price: 48);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userA, title: 'Горячий бутерброд', quantity: 2, price: 63);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userB, title: 'Лазанья', quantity: 1, price: 100);

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle)->values();

        $this->assertCount(3, $rows);
        $this->assertSame('Чертова Е.Н.', $rows[0]['full_name']);
        $this->assertSame('Чертова Е.Н.', $rows[1]['full_name']);
        $this->assertSame('Мекшун А.Н.', $rows[2]['full_name']);
    }

    #[Test]
    public function supplier_order_rows_aggregate_same_dish_within_one_user(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Иванов И.И.',
            'name' => 'Ivanov',
            'email' => 'ivanov@example.com',
        ]);

        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $user, title: 'Лазанья', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $user, title: 'Лазанья', quantity: 2, price: 100);

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle)->values();

        $this->assertCount(1, $rows);
        $this->assertSame('Иванов И.И.', $rows[0]['full_name']);
        $this->assertSame('Лазанья', $rows[0]['title_snapshot']);
        $this->assertSame(3, $rows[0]['quantity']);
        $this->assertSame(300.0, $rows[0]['total_sum']);
    }

    #[Test]
    public function supplier_order_rows_exclude_draft_cancelled_orders_and_cancelled_items(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Soup', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Draft, title: 'Draft Soup', quantity: 2, price: 120);
        $this->createOrderItem($cycle, OrderStatus::Cancelled, title: 'Cancelled Soup', quantity: 3, price: 130);
        $this->createOrderItem(
            $cycle,
            OrderStatus::Submitted,
            OrderItemStatus::Cancelled,
            title: 'Cancelled Item',
            quantity: 4,
            price: 90,
        );

        $service = app(SupplierOrderExportService::class);
        $rows = $service->rowsForCycle($cycle);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Soup', $row['title_snapshot']);
        $this->assertSame(1, $row['quantity']);
        $this->assertSame(100.0, $row['total_sum']);
        $this->assertSame(100.0, $service->totalForCycle($cycle));
    }

    #[Test]
    public function supplier_order_full_name_fallback_uses_name_then_email_then_user_id(): void
    {
        $cycle = $this->createCycle();

        $withName = User::factory()->create([
            'full_name' => null,
            'name' => 'Name Fallback',
            'email' => 'name@example.com',
        ]);
        $withEmailOnly = User::factory()->create([
            'full_name' => null,
            'name' => '',
            'email' => 'email-only@example.com',
        ]);
        $withIdFallback = User::factory()->create([
            'full_name' => null,
            'name' => '',
            'email' => null,
        ]);

        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $withName, title: 'Dish A', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $withEmailOnly, title: 'Dish B', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $withIdFallback, title: 'Dish C', quantity: 1, price: 100);

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle)->values();

        $this->assertSame('Name Fallback', $rows[0]['full_name']);
        $this->assertSame('email-only@example.com', $rows[1]['full_name']);
        $this->assertSame("Пользователь #{$withIdFallback->id}", $rows[2]['full_name']);
    }

    #[Test]
    public function supplier_order_csv_uses_employee_blocks_and_stored_snapshot_rows(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Чертова Е.Н.',
            'name' => 'Chertova',
            'email' => 'chertova@example.com',
        ]);
        $orderItem = $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Запеканка "Чиз"',
            quantity: 1,
            price: 130,
        );

        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, User::factory()->create());

        $user->update(['full_name' => 'Новое ФИО']);
        $orderItem->forceFill([
            'title_snapshot' => 'Changed Soup',
            'quantity' => 9,
            'price_snapshot' => 999,
        ])->save();

        $csv = app(SupplierOrderExportService::class)->csvForExport($export->fresh());
        $lines = $this->csvLines($csv);

        $this->assertNotEmpty($lines);
        $this->assertSame('Чертова Е.Н.', str_getcsv($lines[0], ';')[0] ?? '');
        $this->assertSame(['', 'Наименование', 'Вес', 'Цена', 'Количество', 'Сумма'], str_getcsv($lines[1], ';'));
        $this->assertStringContainsString('Итого по сотруднику', $csv);
        $this->assertStringContainsString('ИТОГО ПО ВСЕМ', $csv);
        $this->assertStringNotContainsString('Новое ФИО', $csv);
        $this->assertStringNotContainsString('Changed Soup', $csv);
    }

    #[Test]
    public function direct_cycle_csv_uses_current_full_name_before_download(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => null,
            'name' => 'Administrator',
            'email' => 'admin@lunch.local',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Лазанья',
            quantity: 1,
            price: 100,
        );

        $user->update(['full_name' => 'Тестов Т.Т.']);

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);

        $this->assertStringContainsString('Тестов Т.Т.', $csv);
        $this->assertStringNotContainsString('Administrator', $csv);
    }

    #[Test]
    public function supplier_order_csv_includes_weight_column_and_keeps_totals(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create(['full_name' => 'Иванов И.И.']);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Запеканка картофельнаяс куриным жульеном',
            quantity: 2,
            price: 156,
            menuWeight: '280 г',
        );
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Лазанья домашняя',
            quantity: 1,
            price: 100,
            menuWeight: null,
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);
        $lines = $this->csvLines($csv);
        $header = str_getcsv($lines[1], ';');
        $weightedRow = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Запеканка'));
        $missingWeightRow = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Лазанья'));
        $employeeTotal = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Итого по сотруднику'));

        $this->assertSame(['', 'Наименование', 'Вес', 'Цена', 'Количество', 'Сумма'], $header);
        $this->assertSame(['', 'Запеканка картофельнаяс куриным жульеном', '280 г', '156', '2', '312'], str_getcsv($weightedRow, ';'));
        $this->assertSame(['', 'Лазанья домашняя', '', '100', '1', '100'], str_getcsv($missingWeightRow, ';'));
        $this->assertSame(['Итого по сотруднику', '', '', '', '3', '412'], str_getcsv($employeeTotal, ';'));
        $this->assertStringContainsString('ИТОГО ПО ВСЕМ', $csv);
    }

    #[Test]
    public function supplier_order_csv_escapes_formula_cells(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => '=Formula User',
            'name' => 'Formula User',
            'email' => 'formula@example.com',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: '+Formula Dish, test',
            quantity: 2,
            price: 120,
        );

        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, User::factory()->create());
        $csv = app(SupplierOrderExportService::class)->csvForExport($export->fresh());

        $this->assertStringContainsString("'=Formula User", $csv);
        $this->assertStringContainsString("'+Formula Dish, test", $csv);
    }

    #[Test]
    public function supplier_order_csv_uses_supplier_name_snapshot_in_name_column(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Новый Н.Н.',
            'name' => 'Administrator',
            'email' => 'admin@lunch.local',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Баветте с курицей и грибами',
            quantity: 1,
            price: 105,
            menuSupplierName: 'Баветте с курицей и грибами в сливочном соусе (260 г)',
            supplierSnapshot: 'Баветте с курицей и грибами в сливочном соусе (260 г)',
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);

        $this->assertStringContainsString('Баветте с курицей и грибами в сливочном соусе (260 г)', $csv);
        $this->assertStringNotContainsString("\"Баветте с курицей и грибами\";105;1;105", $csv);
    }

    #[Test]
    public function supplier_order_csv_falls_back_to_menu_item_supplier_name_for_legacy_order_items(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Петров П.П.',
            'name' => 'Petrov',
            'email' => 'petrov@example.com',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Котлета по-Киевски с пюре',
            quantity: 1,
            price: 110,
            menuSupplierName: 'Котлета (по-Киевски) с картофельным пюре (260г)',
            supplierSnapshot: null,
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);

        $this->assertStringContainsString('Котлета (по-Киевски) с картофельным пюре (260г)', $csv);
    }

    #[Test]
    public function supplier_order_csv_falls_back_to_title_snapshot_when_supplier_name_missing_everywhere(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Сидоров С.С.',
            'name' => 'Sidorov',
            'email' => 'sidorov@example.com',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Лазанья домашняя',
            quantity: 1,
            price: 100,
            menuSupplierName: null,
            supplierSnapshot: null,
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);

        $this->assertStringContainsString('Лазанья домашняя', $csv);
    }

    #[Test]
    public function supplier_order_csv_data_rows_use_semicolon_delimiter(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Новый Н.Н.',
            'name' => 'Administrator',
            'email' => 'admin@lunch.local',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Тестовое блюдо',
            quantity: 2,
            price: 123,
            menuSupplierName: 'Тестовое блюдо (123 г)',
            supplierSnapshot: 'Тестовое блюдо (123 г)',
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);
        $lines = $this->csvLines($csv);
        $dishRow = collect($lines)->first(fn (string $line): bool => str_contains($line, 'Тестовое блюдо'));

        $this->assertIsString($dishRow);
        $this->assertCount(6, str_getcsv($dishRow, ';'));
        $this->assertSame(1, count(str_getcsv($dishRow, ',')));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function supplier_order_xlsx_has_expected_block_formatting_and_totals(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create([
            'full_name' => 'Ivanov I.I.',
            'name' => 'Ivanov',
            'email' => 'ivanov@example.com',
        ]);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Very long dish name for wrap text verification in xlsx export',
            quantity: 2,
            price: 150,
            menuSupplierName: 'Very long supplier dish name for wrap text verification in xlsx export',
            supplierSnapshot: 'Very long supplier dish name for wrap text verification in xlsx export',
        );

        $xlsx = app(SupplierOrderExportService::class)->xlsxForCycle($cycle);
        $path = tempnam(sys_get_temp_dir(), 'supplier-xlsx-');
        $this->assertNotFalse($path);
        file_put_contents($path, $xlsx);

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $zip->close();

            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('Ivanov I.I.', (string) $sheet->getCell('A1')->getValue());
                $this->assertSame('Наименование', (string) $sheet->getCell('B2')->getValue());
            $this->assertSame('Вес', (string) $sheet->getCell('C2')->getValue());
            $this->assertSame('Цена', (string) $sheet->getCell('D2')->getValue());
            $this->assertSame('Количество', (string) $sheet->getCell('E2')->getValue());
            $this->assertSame('Сумма', (string) $sheet->getCell('F2')->getValue());
            $this->assertSame(28.0, $sheet->getColumnDimension('A')->getWidth());
            $this->assertSame(75.0, $sheet->getColumnDimension('B')->getWidth());
            $this->assertSame(12.0, $sheet->getColumnDimension('C')->getWidth());
            $this->assertSame(14.0, $sheet->getColumnDimension('D')->getWidth());
            $this->assertSame(14.0, $sheet->getColumnDimension('E')->getWidth());
            $this->assertSame(14.0, $sheet->getColumnDimension('F')->getWidth());
            $this->assertSame('A2', $sheet->getFreezePane());
            $this->assertTrue($sheet->getStyle('B3')->getAlignment()->getWrapText());
            $this->assertStringContainsString('0.00', $sheet->getStyle('D3')->getNumberFormat()->getFormatCode());
            $this->assertStringContainsString('0.00', $sheet->getStyle('F3')->getNumberFormat()->getFormatCode());
            $this->assertSame('Итого по сотруднику', (string) $sheet->getCell('A4')->getValue());
            $this->assertSame(2.0, (float) $sheet->getCell('E4')->getCalculatedValue());
            $this->assertSame(300.0, (float) $sheet->getCell('F4')->getCalculatedValue());
            $this->assertSame('ИТОГО ПО ВСЕМ', (string) $sheet->getCell('A6')->getValue());
            $this->assertSame(2.0, (float) $sheet->getCell('E6')->getCalculatedValue());
            $this->assertSame(300.0, (float) $sheet->getCell('F6')->getCalculatedValue());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }

            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function supplier_order_xlsx_includes_weight_column_and_empty_fallback(): void
    {
        $cycle = $this->createCycle();
        $user = User::factory()->create(['full_name' => 'Иванов И.И.']);
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Запеканка картофельнаяс куриным жульеном',
            quantity: 2,
            price: 156,
            menuWeight: '280 г',
        );
        $this->createOrderItem(
            cycle: $cycle,
            orderStatus: OrderStatus::Submitted,
            user: $user,
            title: 'Лазанья домашняя',
            quantity: 1,
            price: 100,
            menuWeight: null,
        );

        $xlsx = app(SupplierOrderExportService::class)->xlsxForCycle($cycle);
        $path = tempnam(sys_get_temp_dir(), 'supplier-xlsx-');
        $this->assertNotFalse($path);
        file_put_contents($path, $xlsx);

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('Вес', (string) $sheet->getCell('C2')->getValue());
            $this->assertSame('280 г', (string) $sheet->getCell('C3')->getValue());
            $this->assertSame('', (string) $sheet->getCell('C4')->getValue());
            $this->assertSame(3.0, (float) $sheet->getCell('E5')->getCalculatedValue());
            $this->assertSame(412.0, (float) $sheet->getCell('F5')->getCalculatedValue());
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }

            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    public function supplier_order_csv_and_xlsx_use_the_same_logical_row_order(): void
    {
        $cycle = $this->createCycle();
        $userA = User::factory()->create([
            'full_name' => 'Иванов И.И.',
            'name' => 'Ivanov',
            'email' => 'ivanov@example.com',
        ]);
        $userB = User::factory()->create([
            'full_name' => 'Петров П.П.',
            'name' => 'Petrov',
            'email' => 'petrov@example.com',
        ]);

        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userA, title: 'Блюдо A', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userA, title: 'Блюдо B', quantity: 2, price: 120);
        $this->createOrderItem($cycle, OrderStatus::Submitted, user: $userB, title: 'Блюдо C', quantity: 1, price: 90);

        $service = app(SupplierOrderExportService::class);
        $csv = $service->csvForCycle($cycle);
        $xlsx = $service->xlsxForCycle($cycle);

        $csvLines = $this->csvLines($csv);
        $path = tempnam(sys_get_temp_dir(), 'supplier-xlsx-');
        $this->assertNotFalse($path);
        file_put_contents($path, $xlsx);

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $this->assertCount($highestRow, $csvLines);
            $this->assertStringContainsString('Иванов И.И.', $csvLines[0]);
            $this->assertStringContainsString('Петров П.П.', implode("\n", $csvLines));
            $this->assertStringContainsString('Итого по сотруднику', implode("\n", $csvLines));
            $this->assertStringContainsString('ИТОГО ПО ВСЕМ', $csvLines[$highestRow - 1] ?? '');
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }

            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function csvLines(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        if (! is_array($lines)) {
            return [];
        }

        $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            return [];
        }

        $lines[0] = ltrim($lines[0], "\xEF\xBB\xBF");

        return $lines;
    }

    private function createCycle(): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Closed,
        ]);
    }

    private function createOrderItem(
        OrderCycle $cycle,
        OrderStatus $orderStatus,
        OrderItemStatus $itemStatus = OrderItemStatus::Ordered,
        string $title = 'Test Dish',
        int $quantity = 1,
        int $price = 100,
        ?User $user = null,
        ?string $menuSupplierName = null,
        ?string $supplierSnapshot = null,
        ?string $titleSnapshot = null,
        ?string $menuWeight = null,
    ): OrderItem {
        $user ??= User::factory()->create();
        $menuItem = $this->createMenuItem($title, $price, $menuSupplierName, $menuWeight);

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id)
            ->first();

        if ($order === null) {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_cycle_id' => $cycle->id,
                'status' => $orderStatus,
                'total_price' => $quantity * $price,
                'submitted_at' => $orderStatus === OrderStatus::Submitted ? now() : null,
            ]);
        } else {
            $order->forceFill([
                'status' => $orderStatus,
                'submitted_at' => $orderStatus === OrderStatus::Submitted ? ($order->submitted_at ?? now()) : null,
                'total_price' => (float) $order->total_price + ($quantity * $price),
            ])->save();
        }

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $titleSnapshot ?? $menuItem->title,
            'supplier_name_snapshot' => $supplierSnapshot,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => $itemStatus,
        ]);
    }

    private function createMenuItem(string $title, int $price, ?string $supplierName = null, ?string $weight = null): MenuItem
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['name' => 'Test Category'],
            [
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        return MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => $title,
            'supplier_name' => $supplierName ?? $title,
            'weight' => $weight,
            'price' => $price,
            'is_active' => true,
        ]);
    }
}
