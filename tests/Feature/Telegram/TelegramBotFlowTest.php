<?php

namespace Tests\Feature\Telegram;

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
use App\Services\CurrentOrderCycleResolver;
use App\Services\FridgeItemService;
use App\Services\FridgeSummaryFormatter;
use App\Services\OrderSummaryFormatter;
use App\Services\Telegram\BotClient;
use App\Services\Telegram\KeyboardBuilder;
use App\Services\Telegram\UpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramBotFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_shows_primary_navigation_without_creating_an_unlinked_user(): void
    {
        config()->set('services.telegram.webapp_url', 'https://lunch.example.test');

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/start', telegramId: 777));

        $this->assertCount(1, $bot->messages);
        $this->assertStringContainsString('NethammerEda', $bot->messages[0]['text']);
        $this->assertStringContainsString('войдите через Telegram', $bot->messages[0]['text']);

        $keyboard = $bot->messages[0]['reply_markup']['keyboard'] ?? [];
        $labels = collect($keyboard)->flatten(1)->pluck('text')->all();

        $this->assertContains('Открыть каталог', $labels);
        $this->assertContains('Мой заказ', $labels);
        $this->assertContains('Холодильник', $labels);
        $this->assertContains('История', $labels);
        $this->assertContains('Помощь', $labels);
        $this->assertSame(
            'https://lunch.example.test',
            $keyboard[0][0]['web_app']['url'] ?? null,
        );
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '777',
        ]);
    }

    #[Test]
    public function help_explains_user_actions_without_creating_an_unlinked_user(): void
    {
        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/help', telegramId: 778));

        $this->assertCount(1, $bot->messages);
        $text = $bot->messages[0]['text'];

        $this->assertStringContainsString('/menu', $text);
        $this->assertStringContainsString('/order', $text);
        $this->assertStringContainsString('/fridge', $text);
        $this->assertStringContainsString('/history', $text);
        $this->assertStringContainsString('холодильник', $text);
        $this->assertStringContainsString('не отображается', $text);
        $this->assertNotEmpty($bot->messages[0]['reply_markup']['keyboard'] ?? []);
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '778',
        ]);
    }

    #[Test]
    public function menu_shows_current_ordering_state_and_webapp_without_creating_a_user(): void
    {
        config()->set('services.telegram.webapp_url', 'https://lunch.example.test');
        $this->createCycle(OrderCycleStatus::Open, now()->addDay());

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/menu', telegramId: 779));

        $this->assertCount(1, $bot->messages);
        $this->assertStringContainsString('Заказ открыт', $bot->messages[0]['text']);
        $this->assertStringContainsString('Дедлайн:', $bot->messages[0]['text']);
        $this->assertSame(
            'https://lunch.example.test',
            $bot->messages[0]['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null,
        );
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '779',
        ]);
    }

    #[Test]
    public function order_shows_only_the_linked_users_current_order_without_creating_an_order(): void
    {
        config()->set('services.telegram.webapp_url', 'https://lunch.example.test');
        $user = User::factory()->create(['telegram_id' => '801']);
        $cycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $this->createOrderItem($user, $cycle, quantity: 2, price: 250);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/order', telegramId: 801));

        $this->assertCount(1, $bot->messages);
        $text = $bot->messages[0]['text'];

        $this->assertStringContainsString('Статус: Черновик', $text);
        $this->assertStringContainsString('Лазанья ×2', $text);
        $this->assertStringContainsString('500.00 ₽', $text);
        $this->assertSame(
            'Открыть мой заказ',
            $bot->messages[0]['reply_markup']['inline_keyboard'][0][0]['text'] ?? null,
        );
        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function status_is_public_and_says_when_ordering_is_open(): void
    {
        $this->createCycle(OrderCycleStatus::Open, now()->addDay());

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/status', telegramId: 802));

        $this->assertCount(1, $bot->messages);
        $this->assertStringContainsString('Статус: Заказ открыт', $bot->messages[0]['text']);
        $this->assertStringContainsString('Заказывать еще можно', $bot->messages[0]['text']);
        $this->assertStringContainsString('Дедлайн:', $bot->messages[0]['text']);
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '802',
        ]);
    }

    #[Test]
    #[DataProvider('statusMessages')]
    public function status_uses_clear_wording_for_each_cycle_state(
        OrderCycleStatus $status,
        bool $deadlinePassed,
        string $expectedStatus,
        string $expectedDetail,
    ): void {
        $this->createCycle(
            $status,
            $deadlinePassed ? now()->subMinute() : now()->addDay(),
        );

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/status', telegramId: 803));

        $this->assertStringContainsString("Статус: {$expectedStatus}", $bot->messages[0]['text']);
        $this->assertStringContainsString($expectedDetail, $bot->messages[0]['text']);
    }

    #[Test]
    public function my_order_is_a_backward_compatible_alias_for_order(): void
    {
        $user = User::factory()->create(['telegram_id' => '804']);
        $cycle = $this->createCycle(OrderCycleStatus::Open, now()->addDay());
        $this->createOrderItem($user, $cycle, quantity: 1, price: 250, status: OrderStatus::Submitted);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/my_order', telegramId: 804));

        $this->assertStringContainsString('Статус: Отправлен', $bot->messages[0]['text']);
        $this->assertStringContainsString('Лазанья ×1', $bot->messages[0]['text']);
    }

    #[Test]
    public function order_empty_state_opens_catalog_without_creating_an_order(): void
    {
        config()->set('services.telegram.webapp_url', 'https://lunch.example.test');
        User::factory()->create(['telegram_id' => '805']);
        $this->createCycle(OrderCycleStatus::Open, now()->addDay());

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/order', telegramId: 805));

        $this->assertStringContainsString('пока нет заказа', $bot->messages[0]['text']);
        $this->assertSame(
            'Открыть каталог',
            $bot->messages[0]['reply_markup']['inline_keyboard'][0][0]['text'] ?? null,
        );
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    #[DataProvider('progressedOrderStatuses')]
    public function order_reflects_progressed_cycle_status(
        OrderCycleStatus $cycleStatus,
        string $expectedStatus,
    ): void {
        $user = User::factory()->create(['telegram_id' => '806']);
        $cycle = $this->createCycle($cycleStatus, now()->subDay());
        $this->createOrderItem($user, $cycle, quantity: 1, price: 250, status: OrderStatus::Submitted);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/order', telegramId: 806));

        $this->assertStringContainsString("Статус: {$expectedStatus}", $bot->messages[0]['text']);
    }

    #[Test]
    public function fridge_shows_active_food_with_expiry_status_and_actions(): void
    {
        $user = User::factory()->create(['telegram_id' => '807']);
        $this->createFridgeItem($user, quantity: 2, expiresAt: now()->addDay());

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/fridge', telegramId: 807));

        $this->assertCount(2, $bot->messages);
        $this->assertStringContainsString('Лазанья', $bot->messages[0]['text']);
        $this->assertStringContainsString('Годен до:', $bot->messages[0]['text']);
        $this->assertStringContainsString('Статус: В холодильнике', $bot->messages[0]['text']);

        $itemMessage = $bot->messages[1];
        $this->assertStringContainsString('Осталось порций: 2', $itemMessage['text']);
        $buttons = collect($itemMessage['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text')->all();
        $this->assertContains('Съел 1', $buttons);
        $this->assertContains('Съел всё', $buttons);
        $this->assertContains('Выбросил', $buttons);
    }

    #[Test]
    public function fridge_has_a_clear_empty_state(): void
    {
        User::factory()->create(['telegram_id' => '808']);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/fridge', telegramId: 808));

        $this->assertCount(1, $bot->messages);
        $this->assertStringContainsString('В холодильнике пока ничего нет.', $bot->messages[0]['text']);
    }

    #[Test]
    public function history_shows_completed_items_with_russian_status_and_action_date(): void
    {
        $user = User::factory()->create(['telegram_id' => '809']);
        $this->createFridgeItem($user, status: FridgeItemStatus::Eaten);
        $this->createFridgeItem($user, status: FridgeItemStatus::Discarded);
        $this->createFridgeItem($user, status: FridgeItemStatus::Expired);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/history', telegramId: 809));

        $text = $bot->messages[0]['text'];
        $this->assertStringContainsString('Съедено', $text);
        $this->assertStringContainsString('Выброшено', $text);
        $this->assertStringContainsString('Просрочено', $text);
        $this->assertStringContainsString(now()->format('d.m.Y'), $text);
    }

    #[Test]
    public function history_has_a_clear_empty_state(): void
    {
        User::factory()->create(['telegram_id' => '819']);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/history', telegramId: 819));

        $this->assertStringContainsString('Истории пока нет.', $bot->messages[0]['text']);
    }

    #[Test]
    public function fridge_callback_eat_one_decrements_remaining_portions(): void
    {
        $user = User::factory()->create(['telegram_id' => '810']);
        $item = $this->createFridgeItem($user, quantity: 2);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:eat_one:{$item->id}", telegramId: 810));

        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'quantity_remaining' => 1,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
        $this->assertSame('Готово', $bot->callbacks[0]['text']);
        $this->assertStringContainsString('Остаток: 1/2', $bot->messages[0]['text']);
    }

    #[Test]
    public function fridge_callback_eat_all_marks_food_as_eaten(): void
    {
        $user = User::factory()->create(['telegram_id' => '811']);
        $item = $this->createFridgeItem($user, quantity: 2);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:eat_all:{$item->id}", telegramId: 811));

        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Eaten->value,
        ]);
        $this->assertSame('Готово', $bot->callbacks[0]['text']);
    }

    #[Test]
    public function fridge_callback_discard_marks_food_as_discarded(): void
    {
        $user = User::factory()->create(['telegram_id' => '812']);
        $item = $this->createFridgeItem($user, quantity: 2);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:discard:{$item->id}", telegramId: 812));

        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'quantity_remaining' => 0,
            'status' => FridgeItemStatus::Discarded->value,
        ]);
        $this->assertSame('Готово', $bot->callbacks[0]['text']);
    }

    #[Test]
    public function fridge_callback_rejects_another_users_item_without_disclosing_it(): void
    {
        $owner = User::factory()->create(['telegram_id' => '813']);
        User::factory()->create(['telegram_id' => '814']);
        $item = $this->createFridgeItem($owner, quantity: 2);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:discard:{$item->id}", telegramId: 814));

        $this->assertStringContainsString('недоступна', $bot->callbacks[0]['text']);
        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'quantity_remaining' => 2,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
        $this->assertCount(0, $bot->messages);
    }

    #[Test]
    public function fridge_callback_does_not_mutate_an_already_completed_item(): void
    {
        $user = User::factory()->create(['telegram_id' => '815']);
        $item = $this->createFridgeItem($user, status: FridgeItemStatus::Eaten);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:discard:{$item->id}", telegramId: 815));

        $this->assertStringContainsString('уже нельзя изменить', $bot->callbacks[0]['text']);
        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'status' => FridgeItemStatus::Eaten->value,
            'quantity_remaining' => 0,
        ]);
        $this->assertCount(0, $bot->messages);
    }

    #[Test]
    public function fridge_callback_requires_an_already_linked_telegram_user(): void
    {
        $owner = User::factory()->create(['telegram_id' => '816']);
        $item = $this->createFridgeItem($owner, quantity: 1);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:eat_all:{$item->id}", telegramId: 817));

        $this->assertStringContainsString('войдите', $bot->callbacks[0]['text']);
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '817',
        ]);
        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'status' => FridgeItemStatus::InFridge->value,
        ]);
    }

    #[Test]
    public function deactivated_telegram_user_cannot_mutate_fridge_items(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '818',
            'is_active' => false,
        ]);
        $item = $this->createFridgeItem($user, quantity: 1);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->callbackUpdate("fridge:discard:{$item->id}", telegramId: 818));

        $this->assertStringContainsString('войдите', $bot->callbacks[0]['text']);
        $this->assertDatabaseHas('fridge_items', [
            'id' => $item->id,
            'status' => FridgeItemStatus::InFridge->value,
            'quantity_remaining' => 1,
        ]);
    }

    #[Test]
    public function unknown_command_points_to_current_command_names(): void
    {
        User::factory()->create(['telegram_id' => '820']);

        $bot = new CapturingTelegramBot;
        $this->handler($bot)->handle($this->message('/unknown', telegramId: 820));

        $this->assertStringContainsString('/order', $bot->messages[0]['text']);
        $this->assertStringContainsString('/my_order', $bot->messages[0]['text']);
        $this->assertStringContainsString('/help', $bot->messages[0]['text']);
    }

    /**
     * @return array<string, array{OrderCycleStatus, bool, string, string}>
     */
    public static function statusMessages(): array
    {
        return [
            'open deadline passed' => [OrderCycleStatus::Open, true, 'Прием заказов завершен', 'Дедлайн прошел'],
            'closed' => [OrderCycleStatus::Closed, false, 'Прием заказов завершен', 'Цикл закрыт'],
            'sent to supplier' => [OrderCycleStatus::SentToSupplier, false, 'Заказ отправлен поставщику', 'Заказывать уже нельзя'],
            'delivered' => [OrderCycleStatus::Delivered, false, 'Доставка отмечена, проверьте холодильник', 'Заказывать уже нельзя'],
        ];
    }

    /**
     * @return array<string, array{OrderCycleStatus, string}>
     */
    public static function progressedOrderStatuses(): array
    {
        return [
            'closed' => [OrderCycleStatus::Closed, 'Закрыт'],
            'sent to supplier' => [OrderCycleStatus::SentToSupplier, 'Отправлен поставщику'],
            'delivered' => [OrderCycleStatus::Delivered, 'Доставлен'],
        ];
    }

    private function handler(CapturingTelegramBot $bot): UpdateHandler
    {
        return new UpdateHandler(
            $bot,
            app(KeyboardBuilder::class),
            app(CurrentOrderCycleResolver::class),
            app(OrderSummaryFormatter::class),
            app(FridgeSummaryFormatter::class),
            app(FridgeItemService::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $text, int $telegramId = 202): array
    {
        return [
            'message' => [
                'chat' => ['id' => 101],
                'from' => ['id' => $telegramId, 'first_name' => 'Иван'],
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackUpdate(string $data, int $telegramId): array
    {
        return [
            'callback_query' => [
                'id' => 'callback-1',
                'data' => $data,
                'from' => ['id' => $telegramId, 'first_name' => 'Иван'],
                'message' => ['chat' => ['id' => 101]],
            ],
        ];
    }

    private function createCycle(OrderCycleStatus $status, mixed $closesAt): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => 'Неделя 25.05.2026',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => $closesAt,
            'status' => $status,
        ]);
    }

    private function createOrderItem(
        User $user,
        OrderCycle $cycle,
        int $quantity,
        int $price,
        OrderStatus $status = OrderStatus::Draft,
    ): OrderItem {
        $category = MenuCategory::query()->firstOrCreate(
            ['name' => 'Основное'],
            ['sort_order' => 10, 'is_active' => true],
        );
        $menuItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Лазанья',
            'price' => $price,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => $status,
            'total_price' => $quantity * $price,
            'submitted_at' => $status === OrderStatus::Submitted ? now() : null,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => $quantity,
            'status' => OrderItemStatus::Ordered,
        ]);
    }

    private function createFridgeItem(
        User $user,
        int $quantity = 1,
        FridgeItemStatus $status = FridgeItemStatus::InFridge,
        mixed $expiresAt = null,
    ): FridgeItem {
        return FridgeItem::query()->create([
            'user_id' => $user->id,
            'title_snapshot' => 'Лазанья',
            'quantity_total' => $quantity,
            'quantity_remaining' => $status === FridgeItemStatus::InFridge ? $quantity : 0,
            'status' => $status,
            'arrived_at' => now()->subDay(),
            'expires_at' => $expiresAt,
            'eaten_at' => $status === FridgeItemStatus::Eaten ? now() : null,
            'discarded_at' => $status === FridgeItemStatus::Discarded ? now() : null,
        ]);
    }
}

class CapturingTelegramBot extends BotClient
{
    /** @var array<int, array{chat_id: int|string, text: string, reply_markup: array<string, mixed>|null}> */
    public array $messages = [];

    /** @var array<int, array{id: string, text: string}> */
    public array $callbacks = [];

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $this->messages[] = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ];
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $this->callbacks[] = [
            'id' => $callbackQueryId,
            'text' => $text,
        ];
    }
}
