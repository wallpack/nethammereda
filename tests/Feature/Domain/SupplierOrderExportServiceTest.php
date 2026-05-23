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
    public function supplier_order_rows_include_submitted_order_items(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Cutlet', quantity: 3, price: 150);

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Cutlet', $row->title_snapshot);
        $this->assertSame(3, (int) $row->quantity_sum);
        $this->assertEquals(450.0, (float) $row->total_sum);
    }

    #[Test]
    public function supplier_order_rows_exclude_draft_order_items(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem($cycle, OrderStatus::Draft, title: 'Draft Soup', quantity: 2, price: 120);

        $rows = app(SupplierOrderExportService::class)->rowsForCycle($cycle);

        $this->assertTrue($rows->isEmpty());
    }

    #[Test]
    public function supplier_order_rows_exclude_cancelled_orders_and_cancelled_items(): void
    {
        $cycle = $this->createCycle();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Soup', quantity: 1, price: 100);
        $this->createOrderItem($cycle, OrderStatus::Cancelled, title: 'Soup', quantity: 5, price: 100);
        $this->createOrderItem(
            $cycle,
            OrderStatus::Submitted,
            OrderItemStatus::Cancelled,
            title: 'Soup',
            quantity: 9,
            price: 100,
        );

        $service = app(SupplierOrderExportService::class);
        $rows = $service->rowsForCycle($cycle);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Soup', $row->title_snapshot);
        $this->assertSame(1, (int) $row->quantity_sum);
        $this->assertEquals(100.0, (float) $row->total_sum);
        $this->assertEquals(100.0, $service->totalForCycle($cycle));
    }

    #[Test]
    public function supplier_order_csv_is_built_from_stored_snapshot_and_escapes_formula_cells(): void
    {
        $cycle = $this->createCycle();
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted, title: '=Formula Soup', quantity: 2, price: 120);
        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, User::factory()->create());

        $orderItem->forceFill([
            'title_snapshot' => 'Changed Soup',
            'quantity' => 9,
            'price_snapshot' => 999,
        ])->save();

        $csv = app(SupplierOrderExportService::class)->csvForExport($export->fresh());

        $this->assertStringContainsString("'=Formula Soup", $csv);
        $this->assertStringContainsString('2', $csv);
        $this->assertStringContainsString('240.00', $csv);
        $this->assertStringNotContainsString('Changed Soup', $csv);
        $this->assertStringNotContainsString('8991.00', $csv);
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
    ): OrderItem {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem($title, $price);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => $orderStatus,
            'total_price' => $quantity * $price,
            'submitted_at' => $orderStatus === OrderStatus::Submitted ? now() : null,
        ]);

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
