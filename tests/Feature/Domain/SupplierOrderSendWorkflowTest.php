<?php

namespace Tests\Feature\Domain;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SupplierOrderCannotBeSentException;
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

class SupplierOrderSendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function open_cycle_cannot_be_sent_to_supplier(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Open);
        $admin = User::factory()->create();

        try {
            app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

            $this->fail('Open order cycle was sent to supplier.');
        } catch (SupplierOrderCannotBeSentException $exception) {
            $this->assertStringContainsString('закрыть цикл', $exception->getMessage());
        }

        $this->assertSame(OrderCycleStatus::Open, $cycle->fresh()->status);
    }

    #[Test]
    public function closed_cycle_with_submitted_orders_can_be_sent_to_supplier(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Cutlet', quantity: 2, price: 150);

        $this->travelTo(now()->setMicrosecond(0));

        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $cycle->refresh();
        $this->assertSame(OrderCycleStatus::SentToSupplier, $cycle->status);
        $this->assertSame(now()->toDateTimeString(), $cycle->sent_to_supplier_at?->toDateTimeString());
        $this->assertSame($admin->id, $cycle->sent_to_supplier_by);

        $this->assertSame($cycle->id, $export->order_cycle_id);
        $this->assertSame($admin->id, $export->exported_by);
        $this->assertSame(1, $export->rows_count);
        $this->assertSame(2, $export->total_quantity);
        $this->assertEquals(300.0, (float) $export->total_price);
        $this->assertSame('csv', $export->format);
    }

    #[Test]
    public function closed_cycle_without_submitted_items_cannot_be_sent_to_supplier(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Draft, title: 'Draft Soup', quantity: 2, price: 120);

        try {
            app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

            $this->fail('Empty supplier order was sent.');
        } catch (SupplierOrderCannotBeSentException $exception) {
            $this->assertStringContainsString('нет подтвержденных позиций', $exception->getMessage());
        }

        $cycle->refresh();
        $this->assertSame(OrderCycleStatus::Closed, $cycle->status);
        $this->assertNull($cycle->sent_to_supplier_at);
        $this->assertNull($cycle->sent_to_supplier_by);
        $this->assertDatabaseCount('supplier_order_exports', 0);
    }

    #[Test]
    public function supplier_order_snapshot_excludes_draft_orders(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Cutlet', quantity: 2, price: 150);
        $this->createOrderItem($cycle, OrderStatus::Draft, title: 'Draft Soup', quantity: 3, price: 120);

        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $this->assertEquals([
            [
                'title' => 'Cutlet',
                'quantity' => 2,
                'total_price' => 300.0,
            ],
        ], $export->snapshot_json['rows']);
        $this->assertEquals([
            'rows_count' => 1,
            'total_quantity' => 2,
            'total_price' => 300.0,
        ], $export->snapshot_json['totals']);
    }

    #[Test]
    public function supplier_order_snapshot_excludes_cancelled_orders_and_cancelled_items(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted, title: 'Cutlet', quantity: 2, price: 150);
        $this->createOrderItem($cycle, OrderStatus::Cancelled, title: 'Cancelled Order Soup', quantity: 3, price: 120);
        $this->createOrderItem(
            $cycle,
            OrderStatus::Submitted,
            OrderItemStatus::Cancelled,
            title: 'Cancelled Item Salad',
            quantity: 4,
            price: 90,
        );

        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $this->assertEquals([
            [
                'title' => 'Cutlet',
                'quantity' => 2,
                'total_price' => 300.0,
            ],
        ], $export->snapshot_json['rows']);
        $this->assertEquals([
            'rows_count' => 1,
            'total_quantity' => 2,
            'total_price' => 300.0,
        ], $export->snapshot_json['totals']);
    }

    #[Test]
    public function sent_timestamp_is_not_overwritten_without_repeat_action(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted);

        $this->travelTo(now()->setMicrosecond(0));
        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);
        $sentAt = $cycle->fresh()->sent_to_supplier_at;

        $this->travel(10)->minutes();

        try {
            app(SupplierOrderExportService::class)->sendToSupplier($cycle->fresh(), $admin);

            $this->fail('Supplier order was sent twice without an explicit repeat action.');
        } catch (SupplierOrderCannotBeSentException) {
            $cycle->refresh();
            $this->assertSame(OrderCycleStatus::SentToSupplier, $cycle->status);
            $this->assertSame($sentAt?->toDateTimeString(), $cycle->sent_to_supplier_at?->toDateTimeString());
            $this->assertDatabaseCount('supplier_order_exports', 1);
        }
    }

    #[Test]
    public function delivered_flow_after_supplier_send_creates_fridge_items(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted, quantity: 2);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $cycle->refresh();
        $cycle->status = OrderCycleStatus::Delivered;
        $cycle->save();

        $this->assertDatabaseHas('fridge_items', [
            'order_item_id' => $orderItem->id,
            'user_id' => $orderItem->order->user_id,
            'quantity_total' => 2,
            'quantity_remaining' => 2,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
    }

    #[Test]
    public function order_cycle_status_allows_expected_admin_workflow_transitions(): void
    {
        $this->assertTrue(OrderCycleStatus::Open->canTransitionTo(OrderCycleStatus::Closed));
        $this->assertTrue(OrderCycleStatus::Closed->canTransitionTo(OrderCycleStatus::SentToSupplier));
        $this->assertTrue(OrderCycleStatus::SentToSupplier->canTransitionTo(OrderCycleStatus::Delivered));
        $this->assertTrue(OrderCycleStatus::Delivered->canTransitionTo(OrderCycleStatus::Archived));

        $this->assertFalse(OrderCycleStatus::Open->canTransitionTo(OrderCycleStatus::SentToSupplier));
        $this->assertFalse(OrderCycleStatus::Closed->canTransitionTo(OrderCycleStatus::Delivered));
    }

    private function createCycle(OrderCycleStatus $status): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->startOfWeek()->addDays(4)->setTime(12, 0),
            'status' => $status,
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
