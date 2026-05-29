<?php

namespace Tests\Feature\Domain;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuDeduplicateCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_does_not_modify_categories_or_items(): void
    {
        $salads = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $weighted = MenuCategory::query()->create([
            'name' => 'Салаты (170 г.)',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'category_id' => $salads->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 190,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $weighted->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 190,
            'is_active' => true,
        ]);

        $this->artisan('menu:deduplicate', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('menu_categories', 2);
        $this->assertDatabaseHas('menu_categories', ['id' => $weighted->id, 'name' => 'Салаты (170 г.)']);
        $this->assertDatabaseCount('menu_items', 2);
    }

    #[Test]
    public function cleanup_prefers_item_with_image_as_primary_and_removes_unreferenced_duplicate(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $withImage = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 190,
            'image_path' => 'menu-items/manual/1/vinegret.png',
            'is_active' => true,
        ]);

        $duplicate = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 210,
            'is_active' => true,
        ]);

        $this->artisan('menu:deduplicate')->assertSuccessful();

        $this->assertDatabaseCount('menu_items', 1);
        $this->assertDatabaseHas('menu_items', [
            'id' => $withImage->id,
            'title' => 'Винегрет',
            'image_path' => 'menu-items/manual/1/vinegret.png',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('menu_items', ['id' => $duplicate->id]);
    }

    #[Test]
    public function cleanup_deactivates_secondary_when_order_reference_cannot_be_moved_safely(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $primary = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 190,
            'image_path' => 'menu-items/manual/1/vinegret.png',
            'is_active' => true,
        ]);

        $secondary = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 210,
            'is_active' => true,
        ]);

        [$order] = $this->createOrderWithItems($primary, $secondary);

        $this->artisan('menu:deduplicate')->assertSuccessful();

        $this->assertDatabaseHas('menu_items', [
            'id' => $primary->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $secondary->id,
            'is_active' => false,
        ]);
        $this->assertSame(1, MenuItem::query()->where('title', 'Винегрет')->where('is_active', true)->count());
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $secondary->id,
        ]);
    }

    #[Test]
    public function cleanup_merges_weighted_category_into_base_category(): void
    {
        $salads = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $weighted = MenuCategory::query()->create([
            'name' => 'Салаты 170гр',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $item = MenuItem::query()->create([
            'category_id' => $weighted->id,
            'title' => 'Восточная сказка',
            'supplier_name' => 'Восточная сказка',
            'price' => 190,
            'is_active' => true,
        ]);

        $this->artisan('menu:deduplicate')->assertSuccessful();

        $item->refresh();

        $this->assertDatabaseMissing('menu_categories', ['id' => $weighted->id]);
        $this->assertDatabaseHas('menu_categories', ['id' => $salads->id, 'name' => 'Салаты']);
        $this->assertSame($salads->id, $item->category_id);
    }

    /**
     * @return array{Order, OrderItem, OrderItem}
     */
    private function createOrderWithItems(MenuItem $primary, MenuItem $secondary): array
    {
        $user = User::factory()->create();
        $cycle = OrderCycle::query()->create([
            'title' => 'Тестовая неделя',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 400,
            'submitted_at' => now(),
        ]);

        $primaryOrderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $primary->id,
            'title_snapshot' => $primary->title,
            'price_snapshot' => $primary->price,
            'quantity' => 1,
        ]);

        $secondaryOrderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $secondary->id,
            'title_snapshot' => $secondary->title,
            'price_snapshot' => $secondary->price,
            'quantity' => 1,
        ]);

        return [$order, $primaryOrderItem, $secondaryOrderItem];
    }
}
