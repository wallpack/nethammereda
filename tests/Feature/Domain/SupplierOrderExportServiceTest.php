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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    public function supplier_order_rows_group_by_user_and_show_full_name_only_on_first_line(): void
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
        $this->assertSame('', $rows[1]['full_name']);
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
    public function supplier_order_csv_has_exact_header_and_uses_stored_snapshot_rows(): void
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

        $this->assertStringStartsWith("\xEF\xBB\xBFФИО;Наименование;Цена;количество;Сумма", $csv);
        $this->assertStringContainsString('"Чертова Е.Н.";"Запеканка ""Чиз""";130;1;130', $csv);
        $this->assertStringNotContainsString('Новое ФИО', $csv);
        $this->assertStringNotContainsString('Changed Soup', $csv);
        $this->assertStringNotContainsString('8991', $csv);

        $lines = $this->csvLines($csv);
        $this->assertSame(['ФИО', 'Наименование', 'Цена', 'количество', 'Сумма'], str_getcsv($lines[0], ';'));
        $this->assertCount(5, str_getcsv($lines[1], ';'));
        $this->assertCount(1, str_getcsv($lines[1], ','));
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

        $this->assertStringStartsWith("\xEF\xBB\xBFФИО;Наименование;Цена;количество;Сумма", $csv);
        $this->assertStringContainsString('"Тестов Т.Т.";Лазанья;100;1;100', $csv);
        $this->assertStringNotContainsString('"Administrator"', $csv);
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
        $this->assertCount(5, str_getcsv($this->csvLines($csv)[1], ';'));
    }

    #[Test]
    public function supplier_order_csv_data_row_is_delimited_by_semicolon_for_excel_locales(): void
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
        );

        $csv = app(SupplierOrderExportService::class)->csvForCycle($cycle);
        $lines = $this->csvLines($csv);

        $this->assertCount(5, str_getcsv($lines[1], ';'));
        $this->assertSame(1, count(str_getcsv($lines[1], ',')));
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
    ): OrderItem {
        $user ??= User::factory()->create();
        $menuItem = $this->createMenuItem($title, $price);

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
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => $itemStatus,
        ]);
    }

    private function createMenuItem(string $title, int $price): MenuItem
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
            'price' => $price,
            'is_active' => true,
        ]);
    }
}
