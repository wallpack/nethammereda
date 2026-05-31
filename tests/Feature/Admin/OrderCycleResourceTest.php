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
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
    public function order_cycle_table_displays_deadline_in_business_timezone(): void
    {
        $this->actingAsAdmin();

        $deadlineUtc = CarbonImmutable::create(2026, 5, 26, 12, 41, 0, 'UTC');
        $cycle = OrderCycle::query()->create([
            'title' => 'Timezone Week',
            'starts_at' => $deadlineUtc->startOfWeek(),
            'closes_at' => $deadlineUtc,
            'status' => OrderCycleStatus::Open,
        ]);

        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));
        $expectedDeadline = $deadlineUtc->setTimezone($businessTimezone)->format('d.m.Y, H:i');

        $tableColumn = Livewire::test(ListOrderCycles::class)
            ->instance()
            ->getTable()
            ->getColumn('closes_at');

        $this->assertInstanceOf(TextColumn::class, $tableColumn);
        $this->assertSame($expectedDeadline, (string) $tableColumn->formatState($cycle->closes_at));
    }

    #[Test]
    public function future_open_cycle_table_status_shows_upcoming_without_changing_database_status(): void
    {
        $this->actingAsAdmin();
        $now = CarbonImmutable::create(2026, 6, 1, 10, 0, 0, config('lunch.business_timezone'));
        $this->travelTo($now);

        $cycle = OrderCycle::query()->create([
            'title' => 'Future Week',
            'starts_at' => $now->addDay(),
            'closes_at' => $now->addDays(4),
            'status' => OrderCycleStatus::Open,
        ]);

        Livewire::test(ListOrderCycles::class)
            ->assertSee('Future Week')
            ->assertSee('Скоро откроется')
            ->assertDontSee('Не выбран');

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Open->value,
        ]);
    }

    #[Test]
    public function order_cycle_edit_form_and_table_use_same_business_timezone(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        $businessTimezone = config('lunch.business_timezone', config('app.timezone'));

        $tableColumn = Livewire::test(ListOrderCycles::class)
            ->instance()
            ->getTable()
            ->getColumn('closes_at');

        $form = Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->instance()
            ->getSchema('form');

        $startsAtField = $form?->getComponentByStatePath('starts_at');
        $closesAtField = $form?->getComponentByStatePath('closes_at');

        $this->assertInstanceOf(TextColumn::class, $tableColumn);
        $this->assertInstanceOf(DateTimePicker::class, $startsAtField);
        $this->assertInstanceOf(DateTimePicker::class, $closesAtField);
        $this->assertSame($businessTimezone, $startsAtField->getTimezone());
        $this->assertSame($businessTimezone, $closesAtField->getTimezone());

        $deadlineUtc = CarbonImmutable::create(2026, 5, 26, 12, 41, 0, 'UTC');
        $expectedDeadline = $deadlineUtc->setTimezone($businessTimezone)->format('d.m.Y, H:i');

        $this->assertSame($expectedDeadline, (string) $tableColumn->formatState($deadlineUtc));
    }

    #[Test]
    public function order_cycle_form_accepts_valid_four_digit_year_dates(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateOrderCycle::class)
            ->fillForm([
                'title' => 'Valid Year Week',
                'starts_at' => '2026-06-01 00:00:00',
                'closes_at' => '2026-06-05 12:00:00',
                'status' => OrderCycleStatus::Open->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('order_cycles', [
            'title' => 'Valid Year Week',
            'status' => OrderCycleStatus::Open->value,
        ]);
    }

    #[Test]
    public function order_cycle_form_rejects_five_digit_year_dates_on_create_and_update(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        Livewire::test(CreateOrderCycle::class)
            ->fillForm([
                'title' => 'Invalid Year Week',
                'starts_at' => '20266-06-01 00:00:00',
                'closes_at' => '20266-06-05 12:00:00',
                'status' => OrderCycleStatus::Open->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['starts_at', 'closes_at']);

        Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->fillForm([
                'title' => $cycle->title,
                'starts_at' => '20266-06-01 00:00:00',
                'closes_at' => '20266-06-05 12:00:00',
                'status' => OrderCycleStatus::Open->value,
            ])
            ->call('save')
            ->assertHasFormErrors(['starts_at', 'closes_at']);
    }

    #[Test]
    public function reopen_ordering_action_is_visible_only_for_closed_cycles(): void
    {
        $this->actingAsAdmin();

        $draftCycle = $this->createCycle(OrderCycleStatus::Draft);
        $openCycle = $this->createCycle(OrderCycleStatus::Open);
        $closedCycle = $this->createCycle(OrderCycleStatus::Closed);
        $sentCycle = $this->createSentToSupplierCycle();
        $deliveredCycle = $this->createCycle(OrderCycleStatus::Delivered);
        $archivedCycle = $this->createCycle(OrderCycleStatus::Archived);

        Livewire::test(ListOrderCycles::class)
            ->assertActionHidden(TestAction::make('reopenOrdering')->table($draftCycle))
            ->assertActionHidden(TestAction::make('reopenOrdering')->table($openCycle))
            ->assertActionVisible(TestAction::make('reopenOrdering')->table($closedCycle))
            ->assertActionHidden(TestAction::make('reopenOrdering')->table($sentCycle))
            ->assertActionHidden(TestAction::make('reopenOrdering')->table($deliveredCycle))
            ->assertActionHidden(TestAction::make('reopenOrdering')->table($archivedCycle));
    }

    #[Test]
    public function closed_cycle_can_be_reopened_by_admin_with_future_deadline(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted);
        $order = $orderItem->order;
        $newDeadline = now()->addHours(3)->setSecond(0);

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('reopenOrdering')->table($cycle), data: [
                'new_closes_at' => $newDeadline
                    ->copy()
                    ->setTimezone(config('lunch.business_timezone'))
                    ->toDateTimeString(),
            ])
            ->assertHasNoActionErrors();

        $cycle->refresh();

        $this->assertSame(OrderCycleStatus::Open, $cycle->status);
        $this->assertSame($newDeadline->toDateTimeString(), $cycle->closes_at?->toDateTimeString());
        $this->assertNull($cycle->sent_to_supplier_at);
        $this->assertNull($cycle->sent_to_supplier_by);
        $this->assertDatabaseCount('supplier_order_exports', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'order_id' => $order->id,
            'menu_item_id' => $orderItem->menu_item_id,
            'quantity' => 1,
        ]);
    }

    #[Test]
    public function closed_cycle_cannot_be_reopened_with_past_deadline(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $pastDeadline = now()->subMinute()->setSecond(0);

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('reopenOrdering')->table($cycle), data: [
                'new_closes_at' => $pastDeadline
                    ->copy()
                    ->setTimezone(config('lunch.business_timezone'))
                    ->toDateTimeString(),
            ])
            ->assertHasActionErrors(['new_closes_at']);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function open_cycle_can_still_be_closed_manually_from_edit_form(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->fillForm([
                'title' => $cycle->title,
                'starts_at' => $cycle->starts_at?->toDateTimeString(),
                'closes_at' => now()->addHour()->toDateTimeString(),
                'status' => OrderCycleStatus::Closed->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function saving_open_cycle_with_past_deadline_auto_closes_cycle(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Open);

        Livewire::test(EditOrderCycle::class, ['record' => $cycle->id])
            ->fillForm([
                'title' => $cycle->title,
                'starts_at' => $cycle->starts_at?->toDateTimeString(),
                'closes_at' => now()->subMinute()->toDateTimeString(),
                'status' => OrderCycleStatus::Open->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function reopened_cycle_is_returned_as_open_and_orderable_in_current_cycle_api(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $newDeadline = now()->addHours(2)->setSecond(0);

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('reopenOrdering')->table($cycle), data: [
                'new_closes_at' => $newDeadline
                    ->copy()
                    ->setTimezone(config('lunch.business_timezone'))
                    ->toDateTimeString(),
            ])
            ->assertHasNoActionErrors();

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.is_orderable', true)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.deadline_passed', false);
    }

    #[Test]
    public function reopened_cycle_is_auto_closed_again_after_new_deadline_passes(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $newDeadline = now()->addMinute()->setSecond(0);

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('reopenOrdering')->table($cycle), data: [
                'new_closes_at' => $newDeadline
                    ->copy()
                    ->setTimezone(config('lunch.business_timezone'))
                    ->toDateTimeString(),
            ])
            ->assertHasNoActionErrors();

        $this->travelTo($newDeadline->copy()->addMinute());

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.id', $cycle->id)
            ->assertJsonPath('data.status', OrderCycleStatus::Closed->value)
            ->assertJsonPath('data.is_orderable', false)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.deadline_passed', true);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
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
    public function direct_cycle_csv_export_uses_user_full_name(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $user = User::factory()->create([
            'name' => 'Administrator',
            'full_name' => 'Тестов Т.Т.',
            'email' => 'admin@lunch.local',
        ]);
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted, $user);
        $orderItem->forceFill([
            'title_snapshot' => 'Test Dish',
            'supplier_name_snapshot' => 'Test Dish full name (260 г)',
        ])->save();

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('exportCsv')->table($cycle))
            ->assertFileDownloaded(
                "supplier-order-cycle-{$cycle->id}.csv",
                content: "\xEF\xBB\xBF\"Тестов Т.Т.\";;;;;\n;Наименование;Вес;Цена;Количество;Сумма\n;\"Test Dish full name (260 г)\";;100;1;100\n\"Итого по сотруднику\";;;;1;100\n;;;;;\n\"ИТОГО ПО ВСЕМ\";;;;1;100\n",
                contentType: 'text/csv; charset=UTF-8',
            );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function direct_cycle_xlsx_export_downloads_formatted_file(): void
    {
        $this->actingAsAdmin();
        $cycle = $this->createCycle(OrderCycleStatus::Closed);
        $user = User::factory()->create([
            'name' => 'Administrator',
            'full_name' => 'Тестов Т.Т.',
            'email' => 'admin@lunch.local',
        ]);
        $orderItem = $this->createOrderItem($cycle, OrderStatus::Submitted, $user);
        $orderItem->forceFill([
            'title_snapshot' => 'Test Dish',
            'supplier_name_snapshot' => 'Test Dish full name (260 г)',
        ])->save();

        Livewire::test(ListOrderCycles::class)
            ->callAction(TestAction::make('exportXlsx')->table($cycle))
            ->assertFileDownloaded(
                "supplier-order-cycle-{$cycle->id}.xlsx",
                contentType: SupplierOrderExportService::xlsxMimeType(),
            );
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

    private function createOrderItem(OrderCycle $cycle, OrderStatus $orderStatus, ?User $user = null): OrderItem
    {
        $user ??= User::factory()->create();
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
            'supplier_name_snapshot' => $menuItem->supplier_name ?? $menuItem->title,
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
            'supplier_name' => 'Test Dish',
            'price' => 100,
            'is_active' => true,
        ]);
    }
}
