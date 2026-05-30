<?php

namespace Tests\Feature\Admin;

use App\Enums\FridgeItemStatus;
use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FridgeItems\Pages\ListFridgeItems;
use App\Filament\Resources\MenuCategories\Pages\ListMenuCategories;
use App\Filament\Resources\MenuImports\Pages\ListMenuImports;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Resources\OrderCycles\Pages\ListOrderCycles;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\SupplierOrderExports\Pages\ListSupplierOrderExports;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\CurrentOrderCycleWidget;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuImport;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\SupplierOrderExport;
use App\Models\User;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUxResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_current_cycle_widget_exposes_operational_next_steps(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);

        Livewire::test(CurrentOrderCycleWidget::class)
            ->assertSee('Текущий цикл заказа')
            ->assertSee($cycle->title)
            ->assertSee('Отправить поставщику')
            ->assertSee('Открыть заказы')
            ->assertSee('История циклов');
    }

    #[Test]
    public function admin_tables_expose_operational_filters_and_scan_columns(): void
    {
        $admin = $this->actingAsAdmin();
        $category = $this->createCategory();
        $menuItem = $this->createMenuItem($category);
        $cycle = $this->createCycle(OrderCycleStatus::Open);
        $user = User::factory()->create([
            'name' => 'Lunch User',
            'full_name' => 'Иванов И.И.',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 250,
            'submitted_at' => now(),
        ]);

        FridgeItem::query()->create([
            'user_id' => $user->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'quantity_total' => 2,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        MenuImport::query()->create([
            'original_filename' => 'menu.csv',
            'stored_path' => 'menu-imports/menu.csv',
            'status' => MenuImportStatus::Imported,
            'format' => MenuImportFormat::Csv,
            'rows_total' => 1,
            'rows_valid' => 1,
            'rows_failed' => 0,
            'imported_by' => $admin->id,
            'imported_at' => now(),
        ]);

        SupplierOrderExport::query()->create([
            'order_cycle_id' => $cycle->id,
            'exported_by' => $admin->id,
            'exported_at' => now(),
            'rows_count' => 1,
            'total_quantity' => 1,
            'total_price' => 250,
            'format' => 'csv',
            'snapshot_json' => [],
        ]);

        Livewire::test(ListOrderCycles::class)
            ->assertTableFilterExists('status')
            ->assertTableColumnExists('status', fn ($column): bool => $column instanceof TextColumn);

        Livewire::test(ListOrders::class)
            ->assertTableFilterExists('order_cycle_id')
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('submitted_state')
            ->assertTableFilterExists('user_id')
            ->assertTableColumnExists('user.name')
            ->assertTableColumnExists('submitted_at');

        Livewire::test(ListFridgeItems::class)
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('attention')
            ->assertTableColumnExists('quantity_remaining')
            ->assertTableColumnExists('expires_at');

        Livewire::test(ListMenuCategories::class)
            ->assertTableColumnExists('is_active', fn ($column): bool => $column instanceof TextColumn)
            ->assertTableColumnExists('items_count');

        Livewire::test(ListMenuItems::class)
            ->assertTableColumnExists('image_display_url', fn ($column): bool => $column instanceof ImageColumn)
            ->assertTableColumnExists('image_state', fn ($column): bool => $column instanceof TextColumn)
            ->assertTableFilterExists('category_id')
            ->assertTableFilterExists('is_active')
            ->assertTableFilterExists('image_state')
            ->assertTableFilterExists('weight_state');

        Livewire::test(ListMenuImports::class)
            ->assertTableFilterExists('status')
            ->assertTableFilterExists('format')
            ->assertTableColumnExists('rows_failed');

        Livewire::test(ListSupplierOrderExports::class)
            ->assertTableFilterExists('order_cycle_id')
            ->assertTableFilterExists('format')
            ->assertTableColumnExists('total_price');

        Livewire::test(ListUsers::class)
            ->assertTableColumnExists('role', fn ($column): bool => $column instanceof TextColumn)
            ->assertTableColumnExists('is_active', fn ($column): bool => $column instanceof TextColumn)
            ->assertTableFilterExists('role')
            ->assertTableFilterExists('is_active');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        return $admin;
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

    private function createCategory(): MenuCategory
    {
        return MenuCategory::query()->create([
            'name' => 'Горячее',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function createMenuItem(MenuCategory $category): MenuItem
    {
        return MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Курица с рисом',
            'supplier_name' => 'Курица с рисом 250 г',
            'price' => 250,
            'weight' => null,
            'image_url' => null,
            'image_path' => null,
            'is_active' => true,
        ]);
    }
}
