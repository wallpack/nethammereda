<?php

namespace Tests\Feature\Domain;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderCannotBeSubmittedException;
use App\Exceptions\SubmittedOrderCannotBeChangedException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    public function non_empty_draft_order_can_be_submitted_through_order_service(): void
    {
        [$order] = $this->createDraftOrderWithItem(quantity: 2, price: 125);

        $submitted = app(OrderService::class)->submit($order);

        $this->assertSame(OrderStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
            'total_price' => 250,
        ]);
    }

    #[Test]
    public function repeated_submit_keeps_existing_submitted_timestamp_through_order_service(): void
    {
        [$order] = $this->createSubmittedOrder();
        $submittedAt = $order->submitted_at;

        $this->travel(10)->minutes();

        $submitted = app(OrderService::class)->submit($order);

        $this->assertSame(OrderStatus::Submitted, $submitted->status);
        $this->assertSame(
            $submittedAt?->toDateTimeString(),
            $submitted->submitted_at?->toDateTimeString(),
        );
    }

    #[Test]
    public function submitted_order_can_be_reopened_for_owner_before_deadline_through_order_service(): void
    {
        [$order] = $this->createSubmittedOrder();
        $user = $order->user()->firstOrFail();

        $reopened = app(OrderService::class)->reopenForUserEditing($order, $user);

        $this->assertSame(OrderStatus::Draft, $reopened->status);
        $this->assertNull($reopened->submitted_at);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Draft->value,
            'submitted_at' => null,
        ]);
    }

    #[Test]
    public function reopened_order_allows_user_item_changes_through_order_service(): void
    {
        [$order, $orderItem, $menuItem] = $this->createSubmittedOrder();
        $user = $order->user()->firstOrFail();

        app(OrderService::class)->reopenForUserEditing($order, $user);

        $updated = app(OrderService::class)->updateItemQuantityForUser($orderItem, 3);
        $updated = app(OrderService::class)->addItemForUser($updated, $menuItem, 2);

        $this->assertSame(OrderStatus::Draft, $updated->status);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_price' => 500,
        ]);
    }

    #[Test]
    public function submitted_order_cannot_be_reopened_after_deadline_through_order_service(): void
    {
        [$order] = $this->createSubmittedOrder(closesAt: now()->subMinute());
        $user = $order->user()->firstOrFail();

        $this->expectException(\RuntimeException::class);

        app(OrderService::class)->reopenForUserEditing($order, $user);
    }

    #[Test]
    #[DataProvider('notOrderableCycleStatuses')]
    public function submitted_order_cannot_be_reopened_when_cycle_is_not_orderable_through_order_service(OrderCycleStatus $status): void
    {
        [$order] = $this->createSubmittedOrder(cycleStatus: $status);
        $user = $order->user()->firstOrFail();

        $this->expectException(\RuntimeException::class);

        app(OrderService::class)->reopenForUserEditing($order, $user);
    }

    #[Test]
    public function foreign_submitted_order_cannot_be_reopened_through_order_service(): void
    {
        [$order] = $this->createSubmittedOrder();
        $attacker = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(OrderService::class)->reopenForUserEditing($order, $attacker);
    }

    #[Test]
    public function empty_draft_order_cannot_be_submitted_through_order_service(): void
    {
        [$order] = $this->createDraftOrder();

        try {
            app(OrderService::class)->submit($order);

            $this->fail('Empty draft order was submitted through the order service.');
        } catch (OrderCannotBeSubmittedException) {
            $order->refresh();
            $this->assertSame(OrderStatus::Draft, $order->status);
            $this->assertNull($order->submitted_at);
        }
    }

    /**
     * @return array<string, array{OrderCycleStatus}>
     */
    public static function notOrderableCycleStatuses(): array
    {
        return [
            'closed' => [OrderCycleStatus::Closed],
            'sent_to_supplier' => [OrderCycleStatus::SentToSupplier],
            'delivered' => [OrderCycleStatus::Delivered],
            'archived' => [OrderCycleStatus::Archived],
        ];
    }

    /**
     * @return array{Order, OrderItem, MenuItem}
     */
    private function createSubmittedOrder(
        OrderCycleStatus $cycleStatus = OrderCycleStatus::Open,
        mixed $closesAt = null,
    ): array
    {
        $user = User::factory()->create();
        $cycle = OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => $closesAt ?? now()->addDay(),
            'status' => $cycleStatus,
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

    /**
     * @return array{Order, OrderItem}
     */
    private function createDraftOrderWithItem(int $quantity = 1, int $price = 100): array
    {
        [$order, $menuItem] = $this->createDraftOrder();
        $menuItem->price = $price;
        $menuItem->save();

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => OrderItemStatus::Ordered,
        ]);

        $order->forceFill([
            'total_price' => $quantity * $price,
        ])->save();

        return [$order->fresh(), $orderItem->fresh('order')];
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
