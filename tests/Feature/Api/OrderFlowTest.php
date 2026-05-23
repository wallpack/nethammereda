<?php

namespace Tests\Feature\Api;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_cycle_response_includes_ordering_flags_and_labels(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.is_delivered', false)
            ->assertJsonPath('data.status_label', 'Open')
            ->assertJsonStructure([
                'data' => [
                    'deadline_label',
                ],
            ]);
    }

    #[Test]
    public function user_cannot_add_item_when_there_is_no_current_cycle(): void
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    #[Test]
    #[DataProvider('notOrderableCycleStatuses')]
    public function user_cannot_add_item_to_non_orderable_cycle(OrderCycleStatus $status): void
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem();
        $this->createCycle($status);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    #[Test]
    public function adding_item_creates_draft_order_for_current_user(): void
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem(price: 125);
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.order_cycle_id', $cycle->id)
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Draft->value,
            'total_price' => 250,
        ]);
    }

    #[Test]
    public function user_can_change_quantity_of_own_draft_item(): void
    {
        [$user, , $orderItem] = $this->createDraftOrderItem(quantity: 1, price: 150);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('orders', [
            'id' => $orderItem->order_id,
            'total_price' => 450,
        ]);
    }

    #[Test]
    public function user_can_delete_own_draft_item(): void
    {
        [$user, , $orderItem] = $this->createDraftOrderItem();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/my-order/items/{$orderItem->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseMissing('order_items', [
            'id' => $orderItem->id,
        ]);
    }

    #[Test]
    public function user_can_submit_non_empty_draft_order(): void
    {
        [$user, $order] = $this->createDraftOrderItem();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/submit')
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', OrderStatus::Submitted->value)
            ->assertJsonPath('data.status_label', 'Submitted')
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.can_submit', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function submitting_already_submitted_order_is_idempotent(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem();
        $submittedAt = $order->submitted_at;

        $this->travel(10)->minutes();
        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/submit')
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', OrderStatus::Submitted->value)
            ->assertJsonPath('data.can_submit', false);

        $this->assertSame(
            $submittedAt?->toDateTimeString(),
            $order->fresh()->submitted_at?->toDateTimeString(),
        );
    }

    #[Test]
    public function user_cannot_submit_empty_order(): void
    {
        $user = User::factory()->create();
        $this->createCycle(OrderCycleStatus::Open);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/submit')
            ->assertUnprocessable();

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function user_cannot_change_foreign_order_item(): void
    {
        [, , $orderItem] = $this->createDraftOrderItem();
        $attacker = User::factory()->create();

        Sanctum::actingAs($attacker);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])->assertForbidden();
    }

    #[Test]
    public function user_cannot_add_items_to_submitted_order(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem();
        $menuItem = $this->createMenuItem(title: 'Soup', price: 180);
        $submittedAt = $order->submitted_at;

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Submitted orders cannot be changed.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
        $this->assertSame(
            $submittedAt?->toDateTimeString(),
            $order->fresh()->submitted_at?->toDateTimeString(),
        );
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
        ]);
    }

    #[Test]
    public function user_cannot_update_submitted_order_item(): void
    {
        [$user, $order, $orderItem] = $this->createSubmittedOrderItem();
        $submittedAt = $order->submitted_at;

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Submitted orders cannot be changed.');

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(
            $submittedAt?->toDateTimeString(),
            $order->fresh()->submitted_at?->toDateTimeString(),
        );
    }

    #[Test]
    public function user_cannot_delete_submitted_order_item(): void
    {
        [$user, $order, $orderItem] = $this->createSubmittedOrderItem();
        $submittedAt = $order->submitted_at;

        Sanctum::actingAs($user);

        $this->deleteJson("/api/my-order/items/{$orderItem->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Submitted orders cannot be changed.');

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
        ]);
        $this->assertSame(
            $submittedAt?->toDateTimeString(),
            $order->fresh()->submitted_at?->toDateTimeString(),
        );
    }

    #[Test]
    public function my_order_response_includes_summary_fields(): void
    {
        [$user, $order] = $this->createDraftOrderItem(quantity: 2, price: 200);

        Sanctum::actingAs($user);

        $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.items_count', 1)
            ->assertJsonPath('data.order.total_price', '400.00')
            ->assertJsonPath('data.order.can_submit', true)
            ->assertJsonPath('data.order.status_label', 'Draft');
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
     * @return array{User, Order, OrderItem}
     */
    private function createDraftOrderItem(int $quantity = 1, int $price = 100): array
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem(price: $price);
        $cycle = $this->createCycle(OrderCycleStatus::Open);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Draft,
            'total_price' => $quantity * $price,
        ]);
        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => OrderItemStatus::Ordered,
        ]);

        return [$user, $order, $orderItem->fresh('order')];
    }

    /**
     * @return array{User, Order, OrderItem}
     */
    private function createSubmittedOrderItem(): array
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem();
        $order->forceFill([
            'status' => OrderStatus::Submitted,
            'submitted_at' => now()->subHour(),
        ])->save();

        return [$user, $order->fresh(), $orderItem->fresh('order')];
    }

    private function createCycle(OrderCycleStatus $status): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => $status,
        ]);
    }

    private function createMenuItem(string $title = 'Test Dish', int $price = 100): MenuItem
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
