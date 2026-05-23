<?php

namespace Tests\Feature\Domain;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderCycleCannotBeMarkedDeliveredException;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderCycleDeliveryService;
use App\Services\SupplierOrderExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCycleDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function open_closed_and_draft_cycles_cannot_be_marked_delivered(): void
    {
        foreach ([OrderCycleStatus::Draft, OrderCycleStatus::Open, OrderCycleStatus::Closed] as $status) {
            $cycle = $this->createCycle($status);

            try {
                app(OrderCycleDeliveryService::class)->markDelivered($cycle, User::factory()->create());

                $this->fail("Cycle with status {$status->value} was marked delivered.");
            } catch (OrderCycleCannotBeMarkedDeliveredException) {
                $this->assertSame($status, $cycle->fresh()->status);
            }
        }
    }

    #[Test]
    public function direct_status_save_cannot_mark_unsent_cycle_delivered(): void
    {
        foreach ([OrderCycleStatus::Draft, OrderCycleStatus::Open, OrderCycleStatus::Closed] as $status) {
            $cycle = $this->createCycle($status);

            try {
                $cycle->status = OrderCycleStatus::Delivered;
                $cycle->save();

                $this->fail("Cycle with status {$status->value} was saved as delivered.");
            } catch (OrderCycleCannotBeMarkedDeliveredException) {
                $this->assertSame($status, $cycle->fresh()->status);
            }
        }
    }

    #[Test]
    public function sent_to_supplier_cycle_can_be_marked_delivered_with_audit_and_fridge_items(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted, quantity: 2);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $this->travelTo(now()->setMicrosecond(0)->addHour());

        $deliveredCycle = app(OrderCycleDeliveryService::class)->markDelivered($cycle->fresh(), $admin);

        $this->assertSame(OrderCycleStatus::Delivered, $deliveredCycle->status);
        $this->assertSame(now()->toDateTimeString(), $deliveredCycle->delivered_at?->toDateTimeString());
        $this->assertSame($admin->id, $deliveredCycle->delivered_by);

        $this->assertDatabaseHas('fridge_items', [
            'order_item_id' => $orderItem->id,
            'user_id' => $orderItem->order->user_id,
            'quantity_total' => 2,
            'quantity_remaining' => 2,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
    }

    #[Test]
    public function repeated_delivery_attempt_does_not_create_duplicate_fridge_items(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);
        app(OrderCycleDeliveryService::class)->markDelivered($cycle->fresh(), $admin);

        try {
            app(OrderCycleDeliveryService::class)->markDelivered($cycle->fresh(), $admin);

            $this->fail('Delivered cycle was marked delivered twice.');
        } catch (OrderCycleCannotBeMarkedDeliveredException) {
            $cycle->fresh()->save();

            $this->assertSame(1, FridgeItem::query()->count());
        }
    }

    #[Test]
    public function delivered_cycle_can_be_archived(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        $cycle = app(OrderCycleDeliveryService::class)->markDelivered($cycle->fresh(), $admin);
        $cycle->transitionTo(OrderCycleStatus::Archived);

        $this->assertSame(OrderCycleStatus::Archived, $cycle->fresh()->status);
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
        ])->fresh('order');
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
