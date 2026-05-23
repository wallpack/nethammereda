<?php

namespace Tests\Feature\Domain;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SubmittedOrderCannotBeChangedException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_item_addition_cannot_change_submitted_order(): void
    {
        [$order, , $menuItem] = $this->createSubmittedOrder();
        $submittedAt = $order->submitted_at;

        try {
            app(OrderService::class)->addItemForUser($order, $menuItem, 1);

            $this->fail('Submitted order was changed through the user order service.');
        } catch (SubmittedOrderCannotBeChangedException) {
            $this->assertDatabaseMissing('order_items', [
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
            ]);

            $order->refresh();
            $this->assertSame(OrderStatus::Submitted, $order->status);
            $this->assertSame($submittedAt?->toDateTimeString(), $order->submitted_at?->toDateTimeString());
        }
    }

    #[Test]
    public function user_item_update_cannot_change_submitted_order(): void
    {
        [$order, $orderItem] = $this->createSubmittedOrder();
        $submittedAt = $order->submitted_at;

        try {
            app(OrderService::class)->updateItemQuantityForUser($orderItem, 3);

            $this->fail('Submitted order item quantity was changed through the user order service.');
        } catch (SubmittedOrderCannotBeChangedException) {
            $this->assertDatabaseHas('order_items', [
                'id' => $orderItem->id,
                'quantity' => 1,
            ]);

            $order->refresh();
            $this->assertSame(OrderStatus::Submitted, $order->status);
            $this->assertSame($submittedAt?->toDateTimeString(), $order->submitted_at?->toDateTimeString());
        }
    }

    #[Test]
    public function user_item_deletion_cannot_change_submitted_order(): void
    {
        [$order, $orderItem] = $this->createSubmittedOrder();
        $submittedAt = $order->submitted_at;

        try {
            app(OrderService::class)->deleteItemForUser($orderItem);

            $this->fail('Submitted order item was deleted through the user order service.');
        } catch (SubmittedOrderCannotBeChangedException) {
            $this->assertDatabaseHas('order_items', [
                'id' => $orderItem->id,
            ]);

            $order->refresh();
            $this->assertSame(OrderStatus::Submitted, $order->status);
            $this->assertSame($submittedAt?->toDateTimeString(), $order->submitted_at?->toDateTimeString());
        }
    }

    #[Test]
    public function user_item_addition_can_change_draft_order(): void
    {
        [$order, $menuItem] = $this->createDraftOrder();

        $updated = app(OrderService::class)->addItemForUser($order, $menuItem, 2);

        $this->assertSame(OrderStatus::Draft, $updated->status);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_price' => 200,
        ]);
    }

    /**
     * @return array{Order, OrderItem, MenuItem}
     */
    private function createSubmittedOrder(): array
    {
        $user = User::factory()->create();
        $cycle = OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 100,
            'submitted_at' => now()->subHour(),
        ]);

        $existingItem = $this->createMenuItem('Existing Dish');
        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $existingItem->id,
            'title_snapshot' => $existingItem->title,
            'price_snapshot' => $existingItem->price,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);

        return [$order->fresh(), $orderItem->fresh('order'), $this->createMenuItem('New Dish')];
    }

    /**
     * @return array{Order, MenuItem}
     */
    private function createDraftOrder(): array
    {
        $user = User::factory()->create();
        $cycle = OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Draft,
            'total_price' => 0,
        ]);

        return [$order->fresh(), $this->createMenuItem('Draft Dish')];
    }

    private function createMenuItem(string $title): MenuItem
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
            'price' => 100,
            'is_active' => true,
        ]);
    }
}
