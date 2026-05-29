<?php

namespace Tests\Feature\Fridge;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\DeliveryToFridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FridgeItemFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function delivered_cycle_creates_fridge_items(): void
    {
        [$cycle, $orderItem] = $this->createOrderItemForCycle();

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
    public function repeated_delivered_sync_does_not_create_duplicates(): void
    {
        [$cycle] = $this->createOrderItemForCycle();

        $cycle->status = OrderCycleStatus::Delivered;
        $cycle->save();

        $cycle->fresh()->save();
        app(DeliveryToFridgeService::class)->syncFromDeliveredCycle($cycle->fresh());

        $this->assertSame(1, FridgeItem::query()->count());
    }

    #[Test]
    public function delivered_cycle_syncs_only_submitted_order_items(): void
    {
        [$cycle, $submittedItem] = $this->createOrderItemForCycle();
        $draftItem = $this->createOrderItem($cycle, OrderStatus::Draft);

        $cycle->status = OrderCycleStatus::Delivered;
        $cycle->save();

        $this->assertDatabaseHas('fridge_items', [
            'order_item_id' => $submittedItem->id,
        ]);
        $this->assertDatabaseMissing('fridge_items', [
            'order_item_id' => $draftItem->id,
        ]);
    }

    #[Test]
    public function user_cannot_modify_foreign_fridge_item(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $attacker = User::query()->create([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $fridgeItem = FridgeItem::query()->create([
            'user_id' => $owner->id,
            'title_snapshot' => 'Тестовое блюдо',
            'quantity_total' => 2,
            'quantity_remaining' => 2,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
        ]);

        Sanctum::actingAs($attacker);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/eat-one")
            ->assertForbidden();
    }

    #[Test]
    public function user_cannot_discard_foreign_fridge_item(): void
    {
        $owner = $this->createUser();
        $attacker = $this->createUser();
        $fridgeItem = $this->createFridgeItem($owner, 2);

        Sanctum::actingAs($attacker);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/discard")
            ->assertForbidden();
    }

    #[Test]
    public function eat_one_decreases_remaining_quantity(): void
    {
        $user = $this->createUser();
        $fridgeItem = $this->createFridgeItem($user, 2);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/eat-one")
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 1)
            ->assertJsonPath('data.status', FridgeItemStatus::InFridge->value);
    }

    #[Test]
    public function eat_one_on_last_portion_marks_item_as_eaten(): void
    {
        $user = $this->createUser();
        $fridgeItem = $this->createFridgeItem($user, 1);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/eat-one")
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 0)
            ->assertJsonPath('data.status', FridgeItemStatus::Eaten->value);

        $this->assertDatabaseHas('fridge_items', [
            'id' => $fridgeItem->id,
            'status' => FridgeItemStatus::Eaten->value,
        ]);
    }

    #[Test]
    public function eat_all_marks_item_as_eaten(): void
    {
        $user = $this->createUser();
        $fridgeItem = $this->createFridgeItem($user, 3);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/eat-all")
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 0)
            ->assertJsonPath('data.status', FridgeItemStatus::Eaten->value);

        $this->assertDatabaseHas('fridge_items', [
            'id' => $fridgeItem->id,
            'status' => FridgeItemStatus::Eaten->value,
        ]);
    }

    #[Test]
    public function discard_marks_item_as_discarded(): void
    {
        $user = $this->createUser();
        $fridgeItem = $this->createFridgeItem($user, 3);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/discard")
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 0)
            ->assertJsonPath('data.status', FridgeItemStatus::Discarded->value);

        $this->assertDatabaseHas('fridge_items', [
            'id' => $fridgeItem->id,
            'status' => FridgeItemStatus::Discarded->value,
        ]);
    }

    #[Test]
    public function fridge_expire_command_marks_due_items_as_expired(): void
    {
        $user = $this->createUser();

        $due = FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Суп харчо',
            'quantity_total' => 1,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now()->subDays(4),
            'expires_at' => now()->subMinute(),
        ]);

        FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Салат оливье',
            'quantity_total' => 1,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('fridge:expire')
            ->expectsOutput('Expired fridge items: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('fridge_items', [
            'id' => $due->id,
            'status' => FridgeItemStatus::Expired->value,
        ]);
    }

    #[Test]
    public function fridge_expire_command_does_not_touch_eaten_or_discarded_items(): void
    {
        $user = $this->createUser();

        $eaten = FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Eaten Soup',
            'quantity_total' => 1,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Eaten,
            'arrived_at' => now()->subDays(4),
            'expires_at' => now()->subMinute(),
            'eaten_at' => now()->subDay(),
        ]);

        $discarded = FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Discarded Salad',
            'quantity_total' => 1,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Discarded,
            'arrived_at' => now()->subDays(4),
            'expires_at' => now()->subMinute(),
            'discarded_at' => now()->subDay(),
        ]);

        $this->artisan('fridge:expire')
            ->expectsOutput('Expired fridge items: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('fridge_items', [
            'id' => $eaten->id,
            'status' => FridgeItemStatus::Eaten->value,
        ]);
        $this->assertDatabaseHas('fridge_items', [
            'id' => $discarded->id,
            'status' => FridgeItemStatus::Discarded->value,
        ]);
    }

    #[Test]
    public function my_fridge_response_includes_summary_counts(): void
    {
        $user = $this->createUser();
        $this->createFridgeItem($user, 2, now()->addHours(12));
        $this->createFridgeItem($user, 3, now()->addDays(3));
        FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Том-ям',
            'quantity_total' => 1,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Eaten,
            'arrived_at' => now()->subDay(),
            'eaten_at' => now()->setTime(12, 0),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/my-fridge')
            ->assertOk()
            ->assertJsonPath('meta.active_count', 2)
            ->assertJsonPath('meta.total_portions', 5)
            ->assertJsonPath('meta.expiring_soon_count', 1)
            ->assertJsonPath('meta.eaten_today_count', 1);
    }

    #[Test]
    public function fridge_index_and_history_show_only_authenticated_users_data(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $ownActive = $this->createFridgeItem($user, 2);
        $foreignActive = $this->createFridgeItem($otherUser, 2);

        $ownHistory = FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Собственная история',
            'quantity_total' => 1,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Discarded,
            'arrived_at' => now()->subDays(3),
            'discarded_at' => now()->subHour(),
        ]);
        $foreignHistory = FridgeItem::query()->create([
            'user_id' => $otherUser->id,
            'title_snapshot' => 'Чужая история',
            'quantity_total' => 1,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Eaten,
            'arrived_at' => now()->subDays(2),
            'eaten_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $indexResponse = $this->getJson('/api/my-fridge')
            ->assertOk();

        $this->assertSame([$ownActive->id], collect($indexResponse->json('data'))->pluck('id')->all());
        $this->assertNotContains($foreignActive->id, collect($indexResponse->json('data'))->pluck('id')->all());

        $historyResponse = $this->getJson('/api/my-fridge/history')
            ->assertOk();

        $historyIds = collect($historyResponse->json('data'))->pluck('id')->all();
        $this->assertContains($ownHistory->id, $historyIds);
        $this->assertNotContains($foreignHistory->id, $historyIds);
    }

    /**
     * @return array{OrderCycle, OrderItem}
     */
    private function createOrderItemForCycle(): array
    {
        $user = $this->createUser();
        $menuCategory = MenuCategory::query()->create([
            'name' => 'Тест',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::query()->create([
            'category_id' => $menuCategory->id,
            'title' => 'Котлета',
            'price' => 250,
            'is_active' => true,
        ]);

        $cycle = OrderCycle::query()->create([
            'title' => 'Неделя 01.01.2026',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->startOfWeek()->addDays(4)->setTime(12, 0),
            'status' => OrderCycleStatus::SentToSupplier,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 500,
            'submitted_at' => now(),
        ]);

        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 2,
            'status' => OrderItemStatus::Ordered,
        ]);

        return [$cycle, $orderItem->fresh('order')];
    }

    private function createOrderItem(OrderCycle $cycle, OrderStatus $orderStatus): OrderItem
    {
        $user = $this->createUser();
        $menuCategory = MenuCategory::query()->create([
            'name' => 'Test',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::query()->create([
            'category_id' => $menuCategory->id,
            'title' => 'Cutlet',
            'price' => 250,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => $orderStatus,
            'total_price' => 500,
            'submitted_at' => $orderStatus === OrderStatus::Submitted ? now() : null,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 2,
            'status' => OrderItemStatus::Ordered,
        ])->fresh('order');
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => 'password',
            'role' => UserRole::User,
            'is_active' => true,
        ]);
    }

    private function createFridgeItem(User $user, int $quantity, mixed $expiresAt = null): FridgeItem
    {
        return FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Лазанья',
            'quantity_total' => $quantity,
            'quantity_remaining' => $quantity,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }
}
