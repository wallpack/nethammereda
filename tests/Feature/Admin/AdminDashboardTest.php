<?php

namespace Tests\Feature\Admin;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FridgeItems\Pages\ListFridgeItems;
use App\Filament\Widgets\CurrentOrderCycleWidget;
use App\Filament\Widgets\SupplierStatusWidget;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_page_renders_for_admins(): void
    {
        $this->actingAsAdmin();
        $this->createCycle(OrderCycleStatus::Open);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Панель управления');
    }

    #[Test]
    public function current_cycle_widget_shows_russian_status_and_next_step(): void
    {
        $this->actingAsAdmin();
        $this->createCycle(OrderCycleStatus::Open);

        Livewire::test(CurrentOrderCycleWidget::class)
            ->assertSee('fi-wi-widget', false)
            ->assertSee('Текущий цикл заказа')
            ->assertSee('Открыт')
            ->assertSee('Закрыть заказ');
    }

    #[Test]
    public function supplier_widget_shows_delivery_pending_signal_for_sent_cycles(): void
    {
        $this->actingAsAdmin();
        $this->createSentToSupplierCycleWithOrderItem();

        Livewire::test(SupplierStatusWidget::class)
            ->assertSee('Доставка ожидает отметки')
            ->assertSee('После фактической доставки нажмите')
            ->assertSee('Строк в snapshot')
            ->assertSee('1');
    }

    #[Test]
    public function status_labels_are_russian_and_not_raw_enum_values(): void
    {
        $this->assertSame('Черновик', OrderCycleStatus::Draft->label());
        $this->assertSame('Отправлен поставщику', OrderCycleStatus::SentToSupplier->label());
        $this->assertSame('Отправлен', OrderStatus::Submitted->label());
        $this->assertSame('Отменен', OrderStatus::Cancelled->label());
        $this->assertSame('В холодильнике', FridgeItemStatus::InFridge->label());
        $this->assertSame('Просрочено', FridgeItemStatus::Expired->label());

        $this->assertNotContains('sent_to_supplier', OrderCycleStatus::labels());
        $this->assertNotContains('submitted', OrderStatus::labels());
        $this->assertNotContains('in_fridge', FridgeItemStatus::labels());
    }

    #[Test]
    public function fridge_status_changes_from_user_actions_are_visible_in_filament(): void
    {
        $user = User::factory()->create([
            'name' => 'Lunch User',
            'role' => UserRole::User,
            'is_active' => true,
        ]);
        $fridgeItem = FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Лазанья',
            'quantity_total' => 1,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/my-fridge/items/{$fridgeItem->id}/eat-one")
            ->assertOk()
            ->assertJsonPath('data.quantity_remaining', 0)
            ->assertJsonPath('data.status', FridgeItemStatus::Eaten->value);

        $this->actingAsAdmin();

        Livewire::test(ListFridgeItems::class)
            ->assertSee('Lunch User')
            ->assertSee('Лазанья')
            ->assertSee('Съедено')
            ->assertSee('0');
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

    private function createSentToSupplierCycleWithOrderItem(): OrderCycle
    {
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $admin = User::factory()->create();
        $this->createOrderItem($cycle, OrderStatus::Submitted);

        app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        return $cycle->fresh();
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
