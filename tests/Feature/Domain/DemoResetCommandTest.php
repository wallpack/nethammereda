<?php

namespace Tests\Feature\Domain;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'testing',
            'lunch.business_timezone' => 'Asia/Yekaterinburg',
            'lunch.order_deadline_time' => '17:00',
            'lunch.demo_reset_allowed' => false,
        ]);
    }

    #[Test]
    public function database_seeder_does_not_clear_existing_domain_data(): void
    {
        [$order, $fridgeItem, $menuItem] = $this->createExistingDomainData();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('fridge_items', ['id' => $fridgeItem->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $menuItem->id]);
    }

    #[Test]
    public function demo_reset_in_testing_creates_current_open_cycle_and_menu(): void
    {
        $this->travelTo($this->applicationTime('2026-05-20 12:00:00'));

        $this->artisan('demo:reset', ['--force' => true])
            ->assertSuccessful();

        $cycle = OrderCycle::query()->sole();
        $businessTimezone = config('lunch.business_timezone');

        $this->assertSame(OrderCycleStatus::Open, $cycle->status);
        $this->assertSame('2026-05-18 00:00', $cycle->starts_at->setTimezone($businessTimezone)->format('Y-m-d H:i'));
        $this->assertSame('2026-05-22 17:00', $cycle->closes_at->setTimezone($businessTimezone)->format('Y-m-d H:i'));
        $this->assertGreaterThan(0, MenuItem::query()->count());
    }

    #[Test]
    public function current_cycle_api_is_orderable_after_demo_reset(): void
    {
        $this->travelTo($this->applicationTime('2026-05-20 12:00:00'));

        $this->artisan('demo:reset', ['--force' => true])
            ->assertSuccessful();

        $this->getJson('/api/current-cycle')
            ->assertOk()
            ->assertJsonPath('data.status', OrderCycleStatus::Open->value)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.is_orderable', true)
            ->assertJsonPath('data.availability_label', 'Приём открыт');
    }

    #[Test]
    public function demo_reset_creates_next_week_cycle_at_or_after_friday_deadline(): void
    {
        $this->travelTo($this->applicationTime('2026-05-22 17:00:00'));

        $this->artisan('demo:reset', ['--force' => true])
            ->assertSuccessful();

        $cycle = OrderCycle::query()->sole();
        $businessTimezone = config('lunch.business_timezone');

        $this->assertSame('2026-05-25 00:00', $cycle->starts_at->setTimezone($businessTimezone)->format('Y-m-d H:i'));
        $this->assertSame('2026-05-29 17:00', $cycle->closes_at->setTimezone($businessTimezone)->format('Y-m-d H:i'));
        $this->assertSame('upcoming', $cycle->effectiveOrderState());
        $this->assertFalse($cycle->isOpenForOrdering());
    }

    #[Test]
    public function demo_reset_is_blocked_outside_local_and_testing_without_explicit_permission(): void
    {
        [$order, $fridgeItem] = $this->createExistingDomainData();
        $this->app->instance('env', 'staging');
        config([
            'app.env' => 'staging',
            'lunch.demo_reset_allowed' => false,
        ]);

        $this->artisan('demo:reset', ['--force' => true])
            ->expectsOutputToContain('DEMO_RESET_ALLOWED=true')
            ->assertFailed();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('fridge_items', ['id' => $fridgeItem->id]);
    }

    #[Test]
    public function demo_reset_is_allowed_outside_local_and_testing_with_explicit_permission(): void
    {
        $this->app->instance('env', 'production');
        config([
            'app.env' => 'production',
            'lunch.demo_reset_allowed' => true,
        ]);

        $this->artisan('demo:reset', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('order_cycles', ['status' => OrderCycleStatus::Open->value]);
        $this->assertGreaterThan(0, MenuItem::query()->count());
    }

    #[Test]
    public function demo_reset_requires_confirmation_without_force(): void
    {
        [$order] = $this->createExistingDomainData();

        $this->artisan('demo:reset')
            ->expectsOutputToContain('This command deletes existing demo menu, orders, cycles, and fridge data.')
            ->expectsConfirmation('Continue with destructive demo reset?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    #[Test]
    public function demo_database_seeder_cannot_be_run_directly(): void
    {
        $this->expectException(LogicException::class);

        $this->seed(DemoDatabaseSeeder::class);
    }

    /**
     * @return array{Order, FridgeItem, MenuItem}
     */
    private function createExistingDomainData(): array
    {
        $user = User::factory()->create();
        $category = MenuCategory::query()->create([
            'name' => 'Existing category',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $menuItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Existing dish',
            'price' => 250,
            'is_active' => true,
        ]);
        $cycle = OrderCycle::query()->create([
            'title' => 'Existing cycle',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 250,
            'submitted_at' => now(),
        ]);
        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ]);
        $fridgeItem = FridgeItem::query()->create([
            'user_id' => $user->id,
            'order_item_id' => $orderItem->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'quantity_total' => 1,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge,
            'arrived_at' => now(),
        ]);

        return [$order, $fridgeItem, $menuItem];
    }

    private function applicationTime(string $businessDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($businessDateTime, config('lunch.business_timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
