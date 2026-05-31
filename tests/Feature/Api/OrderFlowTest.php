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
            ->assertJsonPath('data.effective_state', 'open')
            ->assertJsonPath('data.accepting_orders', true)
            ->assertJsonPath('data.is_open_status', true)
            ->assertJsonPath('data.is_orderable', true)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.deadline_passed', false)
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.is_delivered', false)
            ->assertJsonPath('data.status_label', OrderCycleStatus::Open->label())
            ->assertJsonPath('data.availability_label', $this->openAvailabilityLabel())
            ->assertJsonStructure([
                'data' => [
                    'deadline_label',
                    'deadline_date',
                    'deadline_time',
                    'deadline_display',
                    'deadline_display_full',
                    'opens_at_display',
                    'opens_at_display_full',
                    'availability_description',
                ],
            ]);
    }

    #[Test]
    public function future_open_cycle_is_upcoming_and_not_orderable_until_starts_at(): void
    {
        $now = CarbonImmutable::create(2026, 6, 1, 10, 0, 0, config('lunch.business_timezone'));
        $this->travelTo($now);

        $cycle = $this->createCycle(
            OrderCycleStatus::Open,
            $now->addDays(4),
            $now->addDay(),
        );

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.effective_state', 'upcoming')
            ->assertJsonPath('data.accepting_orders', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.is_open_for_ordering', false)
            ->assertJsonPath('data.availability_label', 'Скоро откроется')
            ->assertJsonPath('data.opens_at_display', '02.06, 10:00')
            ->assertJsonPath('data.opens_at_display_full', '02.06.2026, 10:00');

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Open->value,
        ]);
    }

    #[Test]
    public function draft_cycle_with_reached_start_remains_not_orderable(): void
    {
        $now = CarbonImmutable::create(2026, 6, 1, 10, 0, 0, config('lunch.business_timezone'));
        $this->travelTo($now);

        $cycle = $this->createCycle(
            OrderCycleStatus::Draft,
            $now->addDays(4),
            $now->subDay(),
        );

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Draft->value)
            ->assertJsonPath('data.effective_state', 'draft')
            ->assertJsonPath('data.accepting_orders', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.availability_label', 'Приём закрыт')
            ->assertJsonPath('data.status_label', OrderCycleStatus::Draft->label());
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
            ->assertJsonPath('data.effective_state', 'closed')
            ->assertJsonPath('data.accepting_orders', false)
            ->assertJsonPath('data.is_open_status', false)
            ->assertJsonPath('data.is_orderable', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.deadline_passed', true)
            ->assertJsonPath('data.is_open', false)
            ->assertJsonPath('data.is_closed', true)
            ->assertJsonPath('data.status_label', OrderCycleStatus::Closed->label())
            ->assertJsonPath('data.availability_label', $this->closedAvailabilityLabel())
            ->assertJsonPath('data.availability_description', $this->closedAvailabilityDescription());

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
            ->assertJsonPath('message', $this->closedOrderingMessage());

        $this->patchJson("/api/my-order/items/{$orderItem->id}", [
            'quantity' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
            ->assertJsonPath('data.cycle.is_closed', true)
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.draft_unavailable', true)
            ->assertJsonPath('data.draft_unavailable_message', $this->draftUnavailableMessage());

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function stale_draft_from_closed_cycle_is_hidden_from_active_cart(): void
    {
        [$user, $order] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Closed,
            closesAt: now()->subDay(),
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.cycle.id', $order->order_cycle_id)
            ->assertJsonPath('data.cycle.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.draft_unavailable', true)
            ->assertJsonPath('data.draft_unavailable_message', $this->draftUnavailableMessage());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Draft->value,
        ]);
    }

    #[Test]
    public function submitted_order_from_closed_cycle_remains_visible_in_my_order(): void
    {
        [$user, $order] = $this->createSubmittedOrderItem(
            cycleStatus: OrderCycleStatus::Closed,
            closesAt: now()->subDay(),
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.cycle.id', $order->order_cycle_id)
            ->assertJsonPath('data.cycle.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.status', OrderStatus::Submitted->value)
            ->assertJsonPath('data.draft_unavailable', false)
            ->assertJsonPath('data.draft_unavailable_message', null);
    }

    #[Test]
    public function history_returns_only_own_submitted_orders_sorted_newest_first(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $currentCycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $pastCycleA = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(8));
        $pastCycleB = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(15));

        $menuItem = $this->createMenuItem(price: 250);

        $newerOrder = $this->createOrderWithItem(
            $user,
            $pastCycleA,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 2,
            submittedAt: now()->subDay(),
        );
        $olderOrder = $this->createOrderWithItem(
            $user,
            $pastCycleB,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 1,
            submittedAt: now()->subDays(3),
        );
        $draftOrder = $this->createOrderWithItem(
            $user,
            $currentCycle,
            $menuItem,
            OrderStatus::Draft,
            quantity: 1,
        );
        $foreignOrder = $this->createOrderWithItem(
            $otherUser,
            $pastCycleA,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 1,
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-orders/history')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerOrder->id)
            ->assertJsonPath('data.1.id', $olderOrder->id)
            ->assertJsonPath('data.0.can_repeat', true);

        $historyIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($draftOrder->id, $historyIds);
        $this->assertNotContains($foreignOrder->id, $historyIds);
    }

    #[Test]
    public function user_can_repeat_submitted_order_into_current_open_cycle_using_current_prices(): void
    {
        $user = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $currentCycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $menuItem = $this->createMenuItem(price: 180);

        $sourceOrder = $this->createOrderWithItem(
            $user,
            $pastCycle,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 2,
            priceSnapshot: 180,
        );

        $menuItem->forceFill(['price' => 230])->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/my-orders/{$sourceOrder->id}/repeat", ['mode' => 'replace'])
            ->assertOk()
            ->assertJsonPath('data.order.order_cycle_id', $currentCycle->id)
            ->assertJsonPath('data.order.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.order.items.0.quantity', 2)
            ->assertJsonPath('data.order.items.0.price_snapshot', '230.00')
            ->assertJsonPath('data.message', 'Заказ добавлен в корзину.');

        $this->assertDatabaseHas('order_items', [
            'order_id' => Order::query()
                ->where('user_id', $user->id)
                ->where('order_cycle_id', $currentCycle->id)
                ->value('id'),
            'menu_item_id' => $menuItem->id,
            'price_snapshot' => 230,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $sourceOrder->id,
            'status' => OrderStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function repeat_replaces_current_draft_items_when_mode_is_replace(): void
    {
        $user = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $currentCycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $sourceMenuItem = $this->createMenuItem(title: 'Source dish', price: 200);
        $oldDraftMenuItem = $this->createMenuItem(title: 'Old draft dish', price: 120);

        $sourceOrder = $this->createOrderWithItem(
            $user,
            $pastCycle,
            $sourceMenuItem,
            OrderStatus::Submitted,
            quantity: 1,
            priceSnapshot: 200,
        );
        $draftOrder = $this->createOrderWithItem(
            $user,
            $currentCycle,
            $oldDraftMenuItem,
            OrderStatus::Draft,
            quantity: 3,
            priceSnapshot: 120,
        );

        Sanctum::actingAs($user);

        $this->postJson("/api/my-orders/{$sourceOrder->id}/repeat", ['mode' => 'replace'])
            ->assertOk()
            ->assertJsonPath('data.order.id', $draftOrder->id)
            ->assertJsonPath('data.order.items_count', 1)
            ->assertJsonPath('data.order.items.0.menu_item_id', $sourceMenuItem->id);

        $this->assertDatabaseMissing('order_items', [
            'order_id' => $draftOrder->id,
            'menu_item_id' => $oldDraftMenuItem->id,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $draftOrder->id,
            'menu_item_id' => $sourceMenuItem->id,
        ]);
    }

    #[Test]
    public function repeat_skips_inactive_items_and_returns_warning(): void
    {
        $user = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $availableItem = $this->createMenuItem(title: 'Available dish', price: 150);
        $inactiveItem = $this->createMenuItem(title: 'Unavailable dish', price: 200);
        $inactiveItem->forceFill(['is_active' => false])->save();

        $sourceOrder = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $pastCycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 350,
            'submitted_at' => now()->subDay(),
        ]);
        OrderItem::query()->create([
            'order_id' => $sourceOrder->id,
            'menu_item_id' => $availableItem->id,
            'title_snapshot' => $availableItem->title,
            'price_snapshot' => 150,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);
        OrderItem::query()->create([
            'order_id' => $sourceOrder->id,
            'menu_item_id' => $inactiveItem->id,
            'title_snapshot' => $inactiveItem->title,
            'price_snapshot' => 200,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/my-orders/{$sourceOrder->id}/repeat", ['mode' => 'replace'])
            ->assertOk()
            ->assertJsonPath('data.order.items_count', 1)
            ->assertJsonPath('data.skipped_items.0', $inactiveItem->title)
            ->assertJsonPath('data.warning', 'Некоторые блюда сейчас недоступны.');
    }

    #[Test]
    public function repeat_returns_error_when_all_items_are_unavailable_and_does_not_create_empty_draft(): void
    {
        $user = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $currentCycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $menuItem = $this->createMenuItem(title: 'Gone dish', price: 220);
        $menuItem->forceFill(['is_active' => false])->save();

        $sourceOrder = $this->createOrderWithItem(
            $user,
            $pastCycle,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 1,
            priceSnapshot: 220,
        );

        Sanctum::actingAs($user);

        $this->postJson("/api/my-orders/{$sourceOrder->id}/repeat", ['mode' => 'replace'])
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->repeatUnavailableMessage());

        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id,
            'order_cycle_id' => $currentCycle->id,
            'status' => OrderStatus::Draft->value,
        ]);
    }

    #[Test]
    public function repeat_is_blocked_when_current_cycle_is_closed(): void
    {
        $user = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $this->createCycle(OrderCycleStatus::Closed, now()->subDay());
        $menuItem = $this->createMenuItem(price: 180);

        $sourceOrder = $this->createOrderWithItem(
            $user,
            $pastCycle,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 1,
        );

        Sanctum::actingAs($user);

        $this->postJson("/api/my-orders/{$sourceOrder->id}/repeat", ['mode' => 'replace'])
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->repeatWhenClosedMessage());
    }

    #[Test]
    public function user_cannot_repeat_foreign_or_draft_order(): void
    {
        $user = User::factory()->create();
        $attacker = User::factory()->create();
        $pastCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDays(10));
        $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $menuItem = $this->createMenuItem(price: 180);

        $foreignSubmitted = $this->createOrderWithItem(
            $user,
            $pastCycle,
            $menuItem,
            OrderStatus::Submitted,
            quantity: 1,
        );
        $ownDraft = $this->createOrderWithItem(
            $attacker,
            $pastCycle,
            $menuItem,
            OrderStatus::Draft,
            quantity: 1,
        );

        Sanctum::actingAs($attacker);

        $this->postJson("/api/my-orders/{$foreignSubmitted->id}/repeat", ['mode' => 'replace'])
            ->assertNotFound();

        $this->postJson("/api/my-orders/{$ownDraft->id}/repeat", ['mode' => 'replace'])
            ->assertUnprocessable();
    }

    #[Test]
    public function when_new_cycle_opens_active_cart_does_not_pull_draft_from_previous_closed_cycle(): void
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem(price: 200);

        $previousCycle = $this->createCycle(OrderCycleStatus::Closed, now()->subDay());
        $previousOrder = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $previousCycle->id,
            'status' => OrderStatus::Draft,
            'total_price' => 200,
        ]);
        OrderItem::query()->create([
            'order_id' => $previousOrder->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);

        $newCycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());

        Sanctum::actingAs($user);

        $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.cycle.id', $newCycle->id)
            ->assertJsonPath('data.cycle.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.order', null)
            ->assertJsonPath('data.draft_unavailable', false)
            ->assertJsonPath('data.draft_unavailable_message', null);

        $this->assertDatabaseHas('orders', [
            'id' => $previousOrder->id,
            'status' => OrderStatus::Draft->value,
        ]);
    }

    #[Test]
    public function adding_or_submitting_order_before_cycle_start_is_rejected_without_changing_cycle_status(): void
    {
        $now = CarbonImmutable::create(2026, 6, 1, 10, 0, 0, config('lunch.business_timezone'));
        $this->travelTo($now);

        [$user, $order] = $this->createDraftOrderItem(
            cycleStatus: OrderCycleStatus::Open,
            closesAt: $now->addDays(4),
            startsAt: $now->addDay(),
        );
        $menuItem = $this->createMenuItem(title: 'Soup', price: 180);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

        $this->postJson('/api/my-order/submit')
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

        $this->assertDatabaseHas('order_cycles', [
            'id' => $order->order_cycle_id,
            'status' => OrderCycleStatus::Open->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Draft->value,
        ]);
        $this->assertDatabaseMissing('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
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
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
        ])->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
            ->assertUnprocessable()
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
            ->assertJsonPath('message', $this->closedOrderingMessage());

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
    public function current_order_items_keep_user_insertion_order_after_add_fetch_and_quantity_update(): void
    {
        $user = User::factory()->create();
        $alphabeticallyEarlierItem = $this->createMenuItem(title: 'Ананасовое блюдо', price: 200);
        $firstAddedItem = $this->createMenuItem(title: 'Яблочное блюдо', price: 100);
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $firstAddedItem->id,
            'quantity' => 1,
        ])->assertOk();

        $secondAddResponse = $this->postJson('/api/my-order/items', [
            'menu_item_id' => $alphabeticallyEarlierItem->id,
            'quantity' => 1,
        ])->assertOk();

        $this->assertSame(
            ['Яблочное блюдо', 'Ананасовое блюдо'],
            collect($secondAddResponse->json('data.items'))->pluck('title_snapshot')->all(),
        );

        $fetchResponse = $this->getJson('/api/my-order')
            ->assertOk()
            ->assertJsonPath('data.cycle.id', $cycle->id);

        $this->assertSame(
            ['Яблочное блюдо', 'Ананасовое блюдо'],
            collect($fetchResponse->json('data.order.items'))->pluck('title_snapshot')->all(),
        );

        $secondOrderItemId = collect($fetchResponse->json('data.order.items'))
            ->firstWhere('menu_item_id', $alphabeticallyEarlierItem->id)['id'];

        $quantityResponse = $this->patchJson("/api/my-order/items/{$secondOrderItemId}", [
            'quantity' => 3,
        ])->assertOk();

        $this->assertSame(
            ['Яблочное блюдо', 'Ананасовое блюдо'],
            collect($quantityResponse->json('data.items'))->pluck('title_snapshot')->all(),
        );
        $this->assertSame(
            [1, 3],
            collect($quantityResponse->json('data.items'))->pluck('quantity')->all(),
        );
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
            ->assertJsonPath('data.status_label', OrderStatus::Submitted->label())
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
            ->assertJsonPath('data.order.status_label', OrderStatus::Draft->label());
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
        mixed $startsAt = null,
    ): array
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem(price: $price);
        $cycle = $this->createCycle($cycleStatus, $closesAt, $startsAt);
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

    private function createCycle(OrderCycleStatus $status, mixed $closesAt = null, mixed $startsAt = null): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => $startsAt ?? now()->startOfWeek(),
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

    private function createOrderWithItem(
        User $user,
        OrderCycle $cycle,
        MenuItem $menuItem,
        OrderStatus $status = OrderStatus::Draft,
        int $quantity = 1,
        ?int $priceSnapshot = null,
        ?\DateTimeInterface $submittedAt = null,
    ): Order {
        $price = $priceSnapshot ?? (int) $menuItem->price;
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => $status,
            'total_price' => $price * $quantity,
            'submitted_at' => $status === OrderStatus::Submitted ? ($submittedAt ?? now()) : null,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $price,
            'quantity' => $quantity,
            'status' => OrderItemStatus::Ordered,
        ]);

        return $order->fresh(['items', 'cycle']);
    }

    private function openAvailabilityLabel(): string
    {
        return 'Приём открыт';
    }

    private function closedAvailabilityLabel(): string
    {
        return 'Приём закрыт';
    }

    private function closedAvailabilityDescription(): string
    {
        return 'Прием заказов завершен.';
    }

    private function closedOrderingMessage(): string
    {
        return json_decode(
            '"\u041f\u0440\u0438\u0451\u043c \u0437\u0430\u043a\u0430\u0437\u043e\u0432 \u0437\u0430\u043a\u0440\u044b\u0442."',
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function draftUnavailableMessage(): string
    {
        return json_decode(
            '"\u0426\u0438\u043a\u043b \u0437\u0430\u043a\u0440\u044b\u0442, \u0447\u0435\u0440\u043d\u043e\u0432\u0438\u043a \u0437\u0430\u043a\u0430\u0437\u0430 \u0431\u043e\u043b\u044c\u0448\u0435 \u043d\u0435\u0434\u043e\u0441\u0442\u0443\u043f\u0435\u043d."',
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function repeatWhenClosedMessage(): string
    {
        return json_decode(
            '"\u041f\u043e\u0432\u0442\u043e\u0440\u0438\u0442\u044c \u0437\u0430\u043a\u0430\u0437 \u043c\u043e\u0436\u043d\u043e, \u043a\u043e\u0433\u0434\u0430 \u043e\u0442\u043a\u0440\u044b\u0442 \u043f\u0440\u0438\u0451\u043c \u0437\u0430\u043a\u0430\u0437\u043e\u0432."',
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function repeatUnavailableMessage(): string
    {
        return json_decode(
            '"\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u043f\u043e\u0432\u0442\u043e\u0440\u0438\u0442\u044c \u0437\u0430\u043a\u0430\u0437: \u0431\u043b\u044e\u0434\u0430 \u0438\u0437 \u043d\u0435\u0433\u043e \u0441\u0435\u0439\u0447\u0430\u0441 \u043d\u0435\u0434\u043e\u0441\u0442\u0443\u043f\u043d\u044b."',
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
