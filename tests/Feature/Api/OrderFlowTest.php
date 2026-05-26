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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_cycle_open_before_deadline_is_orderable(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.is_open_status', true)
            ->assertJsonPath('data.is_orderable', true)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.deadline_passed', false)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.is_delivered', false)
            ->assertJsonPath('data.status_label', 'Открыт')
            ->assertJsonPath('data.availability_label', 'Заказ открыт')
            ->assertJsonStructure([
                'data' => [
                    'deadline_label',
                    'deadline_date',
                    'deadline_time',
                    'deadline_display',
                    'deadline_display_full',
                    'availability_description',
                ],
            ]);
    }

    #[Test]
    public function current_cycle_payload_returns_deadline_display_without_timezone_shift(): void
    {
        $cycle = $this->createCycle(
            OrderCycleStatus::Open,
            CarbonImmutable::create(2026, 5, 29, 7, 0, 0, 'UTC'),
        );

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.closes_at', '2026-05-29T07:00:00.000000Z')
            ->assertJsonPath('data.deadline_date', '29.05')
            ->assertJsonPath('data.deadline_time', '12:00')
            ->assertJsonPath('data.deadline_display', '29.05, 12:00')
            ->assertJsonPath('data.deadline_display_full', '29.05.2026, 12:00');
    }

    #[Test]
    public function current_cycle_open_after_deadline_is_auto_closed(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Open, now()->subMinute());

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.is_open_status', false)
            ->assertJsonPath('data.is_orderable', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.deadline_passed', true)
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.is_closed', true)
            ->assertJsonPath('data.status_label', 'Закрыт')
            ->assertJsonPath('data.availability_label', 'Заказ закрыт')
            ->assertJsonPath('data.availability_description', 'Администратор закрыл сбор заказов.');

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function user_cannot_add_or_edit_order_after_lazy_cycle_auto_close(): void
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem(
            quantity: 1,
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );
        $menuItem = $this->createMenuItem(title: 'Soup', price: 180);

        Sanctum::actingAs($user);

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.status', OrderCycleStatus::Closed->value);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Прием заказов для этой недели закрыт.');

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertForbidden();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 1,
        ]);
    }

    #[Test]
    public function my_order_endpoint_auto_closes_expired_open_cycle(): void
    {
        [$user, $order] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.cycle.id', $order->order_cycle_id)
            ->assertJsonPath('data.cycle.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.cycle.can_order', false)
            ->assertJsonPath('data.cycle.is_closed', true);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function adding_item_after_expired_deadline_auto_closes_cycle_and_rejects_mutation(): void
    {
        [$user, $order] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );
        $menuItem = $this->createMenuItem(title: 'Soup', price: 180);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
        ]);
    }

    #[Test]
    public function updating_item_after_expired_deadline_auto_closes_cycle_and_rejects_mutation(): void
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem(
            quantity: 2,
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])->assertForbidden();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 2,
        ]);
    }

    #[Test]
    public function deleting_item_after_expired_deadline_auto_closes_cycle_and_rejects_mutation(): void
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );

        Sanctum::actingAs($user);

        $this->deleteJson("/api/my-order/items/{$orderItem->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
        ]);
    }

    #[Test]
    public function submitting_order_after_expired_deadline_auto_closes_cycle_and_rejects_mutation(): void
    {
        [$user, $order] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/submit')
            ->assertUnprocessable();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Draft->value,
        ]);
    }

    #[Test]
    public function reopening_submitted_order_after_expired_deadline_auto_closes_cycle_and_rejects_mutation(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: now()->subMinute(),
        );

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/reopen')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This order can no longer be edited.');

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function current_cycle_after_reopen_is_orderable_until_new_deadline(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed, now()->subHour());
        $newDeadline = now()->addHours(2)->setSecond(0);

        $cycle->forceFill([
            'status' => OrderCycleStatus::Open,
            'closes_at' => $newDeadline,
        ])->save();

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.is_orderable', true)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.deadline_passed', false)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.is_closed', false);
    }

    #[Test]
    public function reopened_cycle_is_auto_closed_again_after_new_deadline_passes(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed, now()->subHour());
        $newDeadline = now()->addMinute()->setSecond(0);

        $cycle->forceFill([
            'status' => OrderCycleStatus::Open,
            'closes_at' => $newDeadline,
        ])->save();

        $this->travelTo($newDeadline->copy()->addMinute());

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.is_orderable', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.deadline_passed', true)
            ->assertJsonPath('data.is_closed', true);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function user_can_add_and_edit_order_after_cycle_reopened_with_future_deadline(): void
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem(
            quantity: 1,
            cycleStatus: OrderCycleStatus::Closed,
            closesAt: now()->subHour(),
        );
        $additionalMenuItem = $this->createMenuItem(title: 'Soup', price: 180);

        $cycle = OrderCycle::query()->findOrFail($order->order_cycle_id);
        $cycle->forceFill([
            'status' => OrderCycleStatus::Open,
            'closes_at' => now()->addHour(),
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $additionalMenuItem->id,
            'quantity' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Draft->value);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $additionalMenuItem->id,
            'quantity' => 1,
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
            ->assertJsonPath('data.status_label', 'Отправлен')
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
    public function submitted_order_can_be_reopened_before_deadline(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/reopen')
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.can_submit', true)
            ->assertJsonPath('data.can_reopen_for_editing', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Draft->value,
            'submitted_at' => null,
        ]);
    }

    #[Test]
    public function reopened_order_can_be_changed_and_submitted_again(): void
    {
        [$user, $order, $orderItem] = $this->createSubmittedOrderItem();

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])->assertUnprocessable();

        $this->postJson('/api/my-order/reopen')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Draft->value);

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.total_price', '300.00');

        $this->postJson('/api/my-order/submit')
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', OrderStatus::Submitted->value)
            ->assertJsonPath('data.can_reopen_for_editing', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
            'total_price' => 300,
        ]);
        $this->assertNotNull($order->fresh()->submitted_at);
    }

    #[Test]
    public function submitted_order_cannot_be_reopened_after_deadline(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem(closesAt: now()->subMinute());

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/reopen')
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    #[DataProvider('notOrderableCycleStatuses')]
    public function submitted_order_cannot_be_reopened_when_cycle_is_not_orderable(OrderCycleStatus $status): void
    {
        [$user, $order] = $this->createSubmittedOrderItem(cycleStatus: $status);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/reopen')
            ->assertUnprocessable();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function user_cannot_reopen_foreign_submitted_order(): void
    {
        [, $order] = $this->createSubmittedOrderItem();
        $attacker = User::factory()->create();

        Sanctum::actingAs($attacker);

        $this->postJson('/api/my-order/reopen')
            ->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Submitted->value,
        ]);
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
            ->assertJsonPath('data.order.status_label', 'Черновик');
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
    private function createDraftOrderItem(
        int $quantity = 1,
        int $price = 100,
        OrderCycleStatus $cycleStatus = OrderCycleStatus::Open,
        mixed $closesAt = null,
    ): array
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem(price: $price);
        $cycle = $this->createCycle($cycleStatus, $closesAt);
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
    private function createSubmittedOrderItem(
        OrderCycleStatus $cycleStatus = OrderCycleStatus::Open,
        mixed $closesAt = null,
    ): array
    {
        [$user, $order, $orderItem] = $this->createDraftOrderItem(
            cycleStatus: $cycleStatus,
            closesAt: $closesAt,
        );
        $order->forceFill([
            'status' => OrderStatus::Submitted,
            'submitted_at' => now()->subHour(),
        ])->save();

        return [$user, $order->fresh(), $orderItem->fresh('order')];
    }

    private function createCycle(OrderCycleStatus $status, mixed $closesAt = null): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => $closesAt ?? now()->addDay(),
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
