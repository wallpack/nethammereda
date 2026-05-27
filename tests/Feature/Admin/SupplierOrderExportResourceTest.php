<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\SupplierOrderExports\Pages\ListSupplierOrderExports;
use App\Filament\Resources\SupplierOrderExports\Pages\ViewSupplierOrderExport;
use App\Filament\Resources\SupplierOrderExports\SupplierOrderExportResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\SupplierOrderExport;
use App\Models\User;
use App\Services\SupplierOrderExportService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierOrderExportResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_index_is_available_to_admins_and_renders_exports(): void
    {
        $this->actingAsAdmin();
        $export = $this->createSupplierExport(title: 'Cutlet', quantity: 2, price: 150);

        $this->get(SupplierOrderExportResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Отправки поставщику')
            ->assertSee('Test Week');

        Livewire::test(ListSupplierOrderExports::class)
            ->assertCanSeeTableRecords([$export])
            ->assertSee('Test Week')
            ->assertSee('Admin Sender')
            ->assertSee('2')
            ->assertSee('300');
    }

    #[Test]
    public function view_page_renders_readable_snapshot_rows(): void
    {
        $this->actingAsAdmin();
        $export = $this->createSupplierExport(title: 'Chicken Bowl', quantity: 3, price: 210);

        Livewire::test(ViewSupplierOrderExport::class, ['record' => $export->id])
            ->assertSee('Отправка поставщику')
            ->assertSee('Снимок отправки')
            ->assertSee('Test Week')
            ->assertSee('Admin Sender')
            ->assertSee('Chicken Bowl')
            ->assertSee('3')
            ->assertSee('210,00')
            ->assertSee('630,00')
            ->assertDontSee('Unit price')
            ->assertDontSee('Total price')
            ->assertDontSee('snapshot_json');
    }

    #[Test]
    public function csv_download_uses_stored_snapshot_instead_of_current_orders(): void
    {
        $this->actingAsAdmin();
        [$export, $orderItem] = $this->createSupplierExportWithOrderItem(
            title: 'Stored Soup',
            quantity: 2,
            price: 120,
            supplierName: 'Stored Soup full name (260 г)',
        );

        $orderItem->forceFill([
            'title_snapshot' => 'Changed Soup',
            'supplier_name_snapshot' => 'Changed Soup full name (260 г)',
            'quantity' => 7,
            'price_snapshot' => 999,
        ])->save();

        Livewire::test(ListSupplierOrderExports::class)
            ->callAction(TestAction::make('downloadCsv')->table($export))
            ->assertFileDownloaded(
                "supplier-order-export-{$export->id}.csv",
                content: "\xEF\xBB\xBFФИО;Наименование;Цена;количество;Сумма\n\"Чертова Е.Н.\";\"Stored Soup full name (260 г)\";120;2;240\n",
                contentType: 'text/csv; charset=UTF-8',
            );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function xlsx_download_action_is_available_and_downloads_file(): void
    {
        $this->actingAsAdmin();
        [$export] = $this->createSupplierExportWithOrderItem(
            title: 'Stored Soup',
            quantity: 2,
            price: 120,
            supplierName: 'Stored Soup full name (260 г)',
        );

        Livewire::test(ListSupplierOrderExports::class)
            ->callAction(TestAction::make('downloadXlsx')->table($export))
            ->assertFileDownloaded(
                "supplier-order-export-{$export->id}.xlsx",
                contentType: SupplierOrderExportService::xlsxMimeType(),
            );
    }

    #[Test]
    public function create_edit_and_delete_are_unavailable_for_export_history(): void
    {
        $this->actingAsAdmin();
        $export = $this->createSupplierExport();

        $this->assertFalse(SupplierOrderExportResource::canCreate());
        $this->assertFalse(SupplierOrderExportResource::canEdit($export));
        $this->assertFalse(SupplierOrderExportResource::canDelete($export));
        $this->assertFalse(SupplierOrderExportResource::canDeleteAny());

        Livewire::test(ListSupplierOrderExports::class)
            ->assertActionDoesNotExist(TestAction::make('edit')->table($export))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($export));
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

    private function createSupplierExport(
        string $title = 'Test Dish',
        int $quantity = 1,
        int $price = 100,
        ?string $supplierName = null,
    ): SupplierOrderExport {
        return $this->createSupplierExportWithOrderItem($title, $quantity, $price, $supplierName)[0];
    }

    /**
     * @return array{SupplierOrderExport, OrderItem}
     */
    private function createSupplierExportWithOrderItem(
        string $title = 'Test Dish',
        int $quantity = 1,
        int $price = 100,
        ?string $supplierName = null,
    ): array {
        $cycle = OrderCycle::query()->create([
            'title' => 'Test Week',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->startOfWeek()->addDays(4)->setTime(12, 0),
            'status' => OrderCycleStatus::Closed,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Sender',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $orderItem = $this->createOrderItem($cycle, $title, $quantity, $price, $supplierName);
        $export = app(SupplierOrderExportService::class)->sendToSupplier($cycle, $admin);

        return [$export->fresh(['orderCycle', 'exportedBy']), $orderItem->fresh()];
    }

    private function createOrderItem(OrderCycle $cycle, string $title, int $quantity, int $price, ?string $supplierName = null): OrderItem
    {
        $user = User::factory()->create([
            'full_name' => 'Чертова Е.Н.',
            'name' => 'Chertova',
            'email' => 'chertova@example.com',
        ]);
        $menuItem = $this->createMenuItem($title, $price, $supplierName);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => $quantity * $price,
            'submitted_at' => now(),
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'supplier_name_snapshot' => $supplierName ?? $menuItem->supplier_name ?? $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => OrderItemStatus::Ordered,
        ]);
    }

    private function createMenuItem(string $title, int $price, ?string $supplierName = null): MenuItem
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
            'supplier_name' => $supplierName ?? $title,
            'price' => $price,
            'is_active' => true,
        ]);
    }
}
