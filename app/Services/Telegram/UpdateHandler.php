<?php

namespace App\Services\Telegram;

use App\Enums\FridgeItemStatus;
use App\Enums\UserRole;
use App\Models\FridgeItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\User;
use App\Services\CurrentOrderCycleResolver;
use App\Services\FridgeItemService;
use App\Services\FridgeSummaryFormatter;
use App\Services\OrderSummaryFormatter;
use Illuminate\Support\Facades\Log;

class UpdateHandler
{
    public function __construct(
        private readonly BotClient $botClient,
        private readonly CurrentOrderCycleResolver $resolver,
        private readonly OrderSummaryFormatter $summaryFormatter,
        private readonly FridgeSummaryFormatter $fridgeSummaryFormatter,
        private readonly FridgeItemService $fridgeItemService,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (is_array($callbackQuery)) {
            $this->handleCallbackQuery($callbackQuery);

            return;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));
        $from = $message['from'] ?? [];

        if ($chatId === null || ! is_array($from)) {
            return;
        }

        $user = $this->resolveTelegramUser($from);
        if ($user === null) {
            return;
        }

        $command = $this->extractCommand($text, $message);
        $aliasCommand = $this->extractAliasCommand($text);

        if ($command === '' && $aliasCommand !== null) {
            $command = $aliasCommand;
        }

        if ($command !== '') {
            Log::info('Telegram command received', [
                'telegram_id' => $user->telegram_id,
                'chat_id' => $chatId,
                'command' => $command,
            ]);
        }

        if ($command === '/start') {
            $this->botClient->sendMessage(
                $chatId,
                "Привет! Это бот корпоративных обедов.\n\n".
                "Команды:\n".
                "/menu — открыть каталог\n".
                "/my_order — мой текущий заказ\n".
                "/status — статус текущей недели\n".
                "/fridge — мой холодильник\n".
                "/history — история холодильника",
            );

            return;
        }

        if ($command === '/menu') {
            $this->handleMenuCommand($chatId);

            return;
        }

        if ($command === '/my_order') {
            $this->handleMyOrderCommand($chatId, $user);

            return;
        }

        if ($command === '/status') {
            $this->handleStatusCommand($chatId);

            return;
        }

        if ($command === '/fridge') {
            $this->sendActiveFridgeItems($chatId, $user);

            return;
        }

        if ($command === '/history') {
            $this->sendFridgeHistory($chatId, $user);

            return;
        }

        if ($command !== '') {
            $this->botClient->sendMessage(
                $chatId,
                'Неизвестная команда. Используйте /menu, /my_order, /status, /fridge или /history.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $from = $callbackQuery['from'] ?? [];
        $message = $callbackQuery['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;

        if ($callbackId === '' || ! is_array($from)) {
            return;
        }

        $user = $this->resolveTelegramUser($from);
        if ($user === null) {
            $this->botClient->answerCallbackQuery($callbackId, 'Пользователь не найден.');

            return;
        }

        $parts = explode(':', $data);
        if (count($parts) !== 3 || $parts[0] !== 'fridge') {
            $this->botClient->answerCallbackQuery($callbackId, 'Неизвестное действие.');

            return;
        }

        $action = $parts[1];
        $itemId = (int) $parts[2];

        $fridgeItem = FridgeItem::query()
            ->where('id', $itemId)
            ->where('user_id', $user->id)
            ->first();

        if ($fridgeItem === null) {
            $this->botClient->answerCallbackQuery($callbackId, 'Позиция не найдена.');

            return;
        }

        $updated = match ($action) {
            'eat_one' => $this->fridgeItemService->eatOne($fridgeItem),
            'eat_all' => $this->fridgeItemService->eatAll($fridgeItem),
            'discard' => $this->fridgeItemService->discard($fridgeItem),
            default => null,
        };

        if ($updated === null) {
            $this->botClient->answerCallbackQuery($callbackId, 'Неизвестное действие.');

            return;
        }

        $this->botClient->answerCallbackQuery($callbackId, 'Готово');

        if ($chatId !== null) {
            $this->botClient->sendMessage(
                $chatId,
                "Обновлено: {$updated->title_snapshot}\n".
                "Статус: {$this->fridgeStatusLabel($updated->status)}\n".
                "Остаток: {$updated->quantity_remaining}/{$updated->quantity_total}",
            );
        }
    }

    private function handleMenuCommand(int|string $chatId): void
    {
        $webappUrl = (string) config('services.telegram.webapp_url');

        if ($webappUrl === '') {
            $this->botClient->sendMessage($chatId, 'Не настроен TELEGRAM_WEBAPP_URL в .env');

            return;
        }

        if (! str_starts_with($webappUrl, 'https://')) {
            $this->botClient->sendMessage(
                $chatId,
                "Каталог: {$webappUrl}\n\n".
                'Для кнопки WebApp нужен HTTPS URL. Для локальной разработки используйте ссылку напрямую.',
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Откройте каталог кнопкой ниже.\n\n".
            "Если WebApp не открылся, используйте прямую ссылку: {$webappUrl}",
            [
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Открыть каталог',
                            'web_app' => ['url' => $webappUrl],
                        ],
                    ],
                    [
                        [
                            'text' => 'Открыть ссылкой',
                            'url' => $webappUrl,
                        ],
                    ],
                ],
            ],
        );
    }

    private function handleMyOrderCommand(int|string $chatId, User $user): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage($chatId, 'Сейчас нет активной недели заказа.');

            return;
        }

        $order = Order::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id)
            ->first();

        if ($order === null) {
            $this->botClient->sendMessage($chatId, "У вас пока нет заказа на неделю «{$cycle->title}».");

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Неделя: {$cycle->title}\n".$this->summaryFormatter->format($order),
        );
    }

    private function handleStatusCommand(int|string $chatId): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage($chatId, 'Сейчас нет активной недели заказа.');

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Текущая неделя: {$cycle->title}\n".
            "Статус: {$this->cycleStatusLabel($cycle)}\n".
            "Дедлайн: {$cycle->closes_at->format('d.m.Y H:i')}",
        );
    }

    private function sendActiveFridgeItems(int|string $chatId, User $user): void
    {
        $items = FridgeItem::query()
            ->where('user_id', $user->id)
            ->where('status', FridgeItemStatus::InFridge)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('arrived_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $this->botClient->sendMessage($chatId, "Ваш холодильник:\n".$this->fridgeSummaryFormatter->formatActive($items));

        foreach ($items as $item) {
            $this->botClient->sendMessage(
                $chatId,
                "{$item->title_snapshot}\nОстаток: {$item->quantity_remaining}/{$item->quantity_total}",
                [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Съел 1',
                                'callback_data' => "fridge:eat_one:{$item->id}",
                            ],
                            [
                                'text' => 'Съел всё',
                                'callback_data' => "fridge:eat_all:{$item->id}",
                            ],
                        ],
                        [
                            [
                                'text' => 'Выбросил',
                                'callback_data' => "fridge:discard:{$item->id}",
                            ],
                        ],
                    ],
                ],
            );
        }
    }

    private function sendFridgeHistory(int|string $chatId, User $user): void
    {
        $items = FridgeItem::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                FridgeItemStatus::Eaten,
                FridgeItemStatus::Discarded,
                FridgeItemStatus::Expired,
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $this->botClient->sendMessage($chatId, "История холодильника:\n".$this->fridgeSummaryFormatter->formatHistory($items));
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function resolveTelegramUser(array $from): ?User
    {
        $telegramId = (string) ($from['id'] ?? '');

        if ($telegramId === '') {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ])));

        if ($name === '') {
            $name = $from['username'] ?? "telegram_{$telegramId}";
        }

        return User::query()->updateOrCreate(
            ['telegram_id' => $telegramId],
            [
                'name' => $name,
                'is_active' => true,
                'role' => UserRole::User,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extractCommand(string $text, array $message): string
    {
        $command = strtolower(trim((string) strtok($text, " \n\r\t")));

        if (str_starts_with($command, '/')) {
            return explode('@', $command)[0];
        }

        $entities = $message['entities'] ?? [];
        if (! is_array($entities)) {
            return '';
        }

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            if (($entity['type'] ?? null) !== 'bot_command') {
                continue;
            }

            $offset = (int) ($entity['offset'] ?? 0);
            $length = (int) ($entity['length'] ?? 0);
            if ($length <= 0) {
                continue;
            }

            $raw = strtolower(substr($text, $offset, $length));

            return explode('@', $raw)[0];
        }

        return '';
    }

    private function extractAliasCommand(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        return match ($normalized) {
            'меню', 'каталог', 'menu' => '/menu',
            'мой заказ', 'заказ', 'my order' => '/my_order',
            'статус', 'status' => '/status',
            'холодильник', 'fridge' => '/fridge',
            'история', 'history' => '/history',
            default => null,
        };
    }

    private function cycleStatusLabel(OrderCycle $cycle): string
    {
        return match ($cycle->status->value) {
            'draft' => 'Черновик',
            'open' => $cycle->isOpenForOrdering() ? 'Открыт' : 'Открыт (дедлайн прошел)',
            'closed' => 'Закрыт',
            'sent_to_supplier' => 'Отправлен поставщику',
            'delivered' => 'Доставлен',
            'archived' => 'Архив',
            default => $cycle->status->value,
        };
    }

    private function fridgeStatusLabel(FridgeItemStatus $status): string
    {
        return match ($status) {
            FridgeItemStatus::InFridge => 'в холодильнике',
            FridgeItemStatus::Eaten => 'съедено',
            FridgeItemStatus::Discarded => 'выброшено',
            FridgeItemStatus::Expired => 'просрочено',
        };
    }
}

