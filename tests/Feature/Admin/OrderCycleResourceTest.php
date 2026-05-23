<?php

namespace Tests\Feature\Admin;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FridgeItems\Pages\CreateFridgeItem;
use App\Filament\Resources\FridgeItems\Pages\EditFridgeItem;
use App\Filament\Resources\FridgeItems\Pages\ListFridgeItems;
use App\Filament\Resources\OrderCycles\Pages\CreateOrderCycle;
use App\Filament\Resources\OrderCycles\Pages\EditOrderCycle;
use App\Filament\Resources\OrderCycles\Pages\ListOrderCycles;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCycleResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mark_delivered_action_is_visible_only_for_sent_to_supplier_cycles(): void
    {
        $this->actingAsAdmin();

        $openCycle = $this->createCycle(OrderCycleStatus::Open);
        $closedCycle = $this->createCycle(OrderCycleStatus::Closed);
        $sentCycle = $this->createSentToSupplierCycle();

        Livewire::test(ListOrderCycles::class)
            ->assertActionHidden(TestAction::make('markDelivered')->table($openCycle))
            ->assertActionHidden(TestAction::make('markDelivered')->table($closedCycle))
            ->assertActionVisible(TestAction::make('markDelivered')->table($sentCycle));
    }

    #[Test]
    public function resource_page_titles_are_plain_russian_labels(): void
    {
        $this->actingAsAdmin();

        $cycle = $this->createCycle(OrderCycleStatus::Open);
        $fridgeItem = FridgeItem::query()->create([
            'user_id' => User::factory()->create()->id,
            'title_snapshot' => 'Лазанья',
            'quantity_total' => 1,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
        ]);

        $listOrderCycles = Livewire::test(ListOrderCycles::class)
            ->assertSee('Недельные циклы')
            ->assertDontSee('Недельные Циклы');

        $this->assertSame([], $listOrderCycles->instance()->getBreadcrumbs());

        Livewire::test(CreateOrderCycle::class)
            ->assertSee('Создание недельного цикла')
            ->assertDontSee('Создание Недельный Цикл');

        $editOrderCycle = Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->assertSee('Редактирование недельного цикла')
            ->assertDontSee('Редактирование Недельный Цикл');

        $this->assertSame(['Недельные циклы'], array_values($editOrderCycle->instance()->getBreadcrumbs()));

        $listFridgeItems = Livewire::test(ListFridgeItems::class)
            ->assertSee('Позиции холодильника')
            ->assertDontSee('Позиции Холодильника');

        $this->assertSame([], $listFridgeItems->instance()->getBreadcrumbs());

        Livewire::test(CreateFridgeItem::class)
            ->assertSee('Создание позиции холодильника')
            ->assertDontSee('Создание Позиция Холодильника');

        $editFridgeItem = Livewire::test(EditFridgeItem::class, ['record' => $fridgeItem->id])
            ->assertSee('Редактирование позиции холодильника')
            ->assertDontSee('Редактирование Позиция Холодильника');

        $this->assertSame(['Холодильник'], array_values($editFridgeItem->instance()->getBreadcrumbs()));
    }

    #[Test]
    public function send_to_supplier_action_is_visible_only_for_closed_cycles(): void
    {
        $this->actingAsAdmin();

        $openCycle = $this->createCycle(OrderCycleStatus::Open);
        $closedCycle = $this->createCycle(OrderCycleStatus::Closed);
        $sentCycle = $this->createSentToSupplierCycle();

        Livewire::test(ListOrderCycles::class)
            ->assertActionHidden(TestAction::make('sendToSupplier')->table($openCycle))
            ->assertActionVisible(TestAction::make('sendToSupplier')->table($closedCycle))
            ->assertActionHidden(TestAction::make('sendToSupplier')->table($sentCycle));
    }

    #[Test]
    public function edit_page_warns_when_open_cycle_deadline_has_passed(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Open);
        $cycle->forceFill(['closes_at' => now()->subHour()])->save();

        Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->assertSee('Цикл открыт, но дедлайн уже прошел')
            ->assertSee('Пользователи уже не могут добавлять блюда');
    }

    #[Test]
    public function mark_delivered_table_action_delivers_cycle_and_creates_fridge_items(): void
    {
        $admin = $this->actingAsAdmin();
        [$cycle, $orderItem] = $this->createSentToSupplierCycleWithOrderItem();

        $this->travelTo(now()->setMicrosecond(0)->addHour());

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('markDelivered')->table($cycle))
            ->assertHasNoActionErrors();

        $cycle->refresh();

        $this->assertSame(OrderCycleStatus::Delivered, $cycle->status);
        $this->assertSame(now()->toDateTimeString(), $cycle->delivered_at?->toDateTimeString());
        $this->assertSame($admin->id, $cycle->delivered_by);

        $this->assertDatabaseHas('fridge_items', [
            'order_item_id' => $orderItem->id,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        return $admin;
    }

    private function createSentToSupplierCycle(): OrderCycle
    {
        [$cycle] = $this->createSentToSupplierCycleWithOrderItem();

        return $cycle;
    }

    /**
     * @return array{OrderCycle, OrderItem}
     */
    private function createSentToSupplierCycleWithOrderItem(): array
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        return [$cycle->fresh(), $orderItem->fresh('order')];
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

    private function createOrderItem(OrderCycle $cycle, OrderStatus $orderStatus): OrderItem
    {
        $user = User::factory()->create();
        $menuItem = $this->createMenuItem();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => $orderStatus,
            'total_price' => 100,
            'submitted_at' => $orderStatus === OrderStatus::Submitted ? now() : null,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);
    }

    private function createMenuItem(): MenuItem
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
            'title' => 'Test Dish',
            'price' => 100,
            'is_active' => true,
        ]);
    }
}
