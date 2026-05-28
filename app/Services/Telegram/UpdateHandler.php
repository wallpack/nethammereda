<?php

namespace App\Services\Telegram;

use App\Enums\FridgeItemStatus;
use App\Enums\OrderCycleStatus;
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
        private readonly KeyboardBuilder $keyboardBuilder,
        private readonly TelegramLinkService $telegramLinkService,
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

        $command = $this->extractCommand($text, $message);
        $aliasCommand = $this->extractAliasCommand($text);

        if ($command === '' && $aliasCommand !== null) {
            $command = $aliasCommand;
        }

        if ($command !== '') {
            Log::info('Telegram command received', [
                'telegram_id' => (string) ($from['id'] ?? ''),
                'chat_id' => $chatId,
                'command' => $command,
            ]);
        }

        if ($command === '/start') {
            $this->handleStartCommand(
                $chatId,
                $from,
                $this->extractStartPayload($text),
            );

            return;
        }

        if ($command === '/help') {
            $this->handleHelpCommand($chatId);

            return;
        }

        if ($command === '/menu') {
            $this->handleMenuCommand($chatId);

            return;
        }

        if ($command === '/status') {
            $this->handleStatusCommand($chatId);

            return;
        }

        $user = $this->findTelegramUser($from);
        if ($user === null) {
            $this->sendLoginRequired($chatId);

            return;
        }

        if (in_array($command, ['/order', '/my_order'], true)) {
            $this->handleMyOrderCommand($chatId, $user);

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
                'Не понял команду. Нажмите «Помощь».',
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

        $user = $this->findTelegramUser($from);
        if ($user === null) {
            $this->botClient->answerCallbackQuery($callbackId, 'Сначала войдите через каталог.');

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
            $this->botClient->answerCallbackQuery($callbackId, 'Эта позиция недоступна.');

            return;
        }

        if ($fridgeItem->status !== FridgeItemStatus::InFridge || $fridgeItem->quantity_remaining <= 0) {
            $this->botClient->answerCallbackQuery($callbackId, 'Эту позицию уже нельзя изменить.');

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
        $webappUrl = trim((string) config('services.telegram.webapp_url'));
        $menuInlineKeyboard = $this->keyboardBuilder->menuInlineKeyboard();

        if ($menuInlineKeyboard !== null) {
            $this->botClient->sendMessage(
                $chatId,
                'Открыть меню 🍽️',
                $menuInlineKeyboard,
            );

            return;
        }

        if (filter_var($webappUrl, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($webappUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->botClient->sendMessage(
                $chatId,
                "Открыть меню 🍽️\n{$webappUrl}",
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            'Меню временно недоступно. Попробуйте позже.',
        );
    }

    private function menuCycleSummary(): string
    {
        $cycle = $this->resolver->resolve();

        if ($cycle === null) {
            return 'Сейчас нет активной недели заказа.';
        }

        $state = match ($cycle->status->value) {
            'open' => $cycle->isOpenForOrdering() ? 'Заказ открыт.' : 'Прием заказов завершен.',
            'closed' => 'Прием заказов завершен.',
            'sent_to_supplier' => 'Заказ отправлен поставщику. Ожидается доставка.',
            'delivered' => 'Доставка отмечена, проверьте холодильник.',
            default => $this->cycleStatusLabel($cycle).'.',
        };

        return "Неделя: {$cycle->title}\n{$state}\nДедлайн: {$cycle->closes_at->format('d.m.Y H:i')}";
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function handleStartCommand(int|string $chatId, array $from, ?string $startPayload): void
    {
        $telegramId = (string) ($from['id'] ?? '');
        $isLinkPayload = $startPayload !== null
            && str_starts_with(strtolower(trim($startPayload)), TelegramLinkService::START_PREFIX);

        if ($isLinkPayload) {
            $linkToken = $this->telegramLinkService->extractTokenFromStartPayload($startPayload);

            if ($linkToken === null || $telegramId === '') {
                $this->botClient->sendMessage(
                    $chatId,
                    'Ссылка недействительна. Привяжите Telegram в профиле на сайте.',
                    $this->keyboardBuilder->navigation(),
                );

                return;
            }

            $this->handleTelegramLinkStart($chatId, $linkToken, $telegramId);

            return;
        }

        $user = $this->findTelegramUser($from);

        $text = "Привет! Я бот Nethammer Eda\n".
            'Помогу открыть меню, проверить заказ и узнать статус приёма.';

        if ($user === null) {
            $text .= "\n\nЧтобы видеть свои заказы, привяжите Telegram в профиле на сайте.";
        }

        $this->botClient->sendMessage($chatId, $text, $this->keyboardBuilder->navigation());
    }

    private function handleTelegramLinkStart(int|string $chatId, string $linkToken, string $telegramId): void
    {
        $result = $this->telegramLinkService->consumeToken($linkToken, $telegramId);

        $text = match ($result) {
            'linked' => "Telegram подключён ✅\nТеперь я смогу показывать ваш заказ и статус.",
            'used' => 'Эта ссылка уже использована. Запросите новую в профиле на сайте.',
            'expired' => 'Срок действия ссылки истёк. Запросите новую в профиле на сайте.',
            'telegram_conflict' => 'Этот Telegram уже привязан к другому аккаунту. Обратитесь к администратору.',
            'user_already_linked' => 'Ваш аккаунт уже привязан к другому Telegram. Обратитесь к администратору.',
            'user_inactive' => 'Ваш аккаунт деактивирован. Обратитесь к администратору.',
            default => 'Ссылка недействительна. Запросите новую в профиле на сайте.',
        };

        $this->botClient->sendMessage($chatId, $text, $this->keyboardBuilder->navigation());
    }

    private function handleHelpCommand(int|string $chatId): void
    {
        $this->botClient->sendMessage(
            $chatId,
            "Что я умею:\n".
            "🍽️ Меню — открыть каталог\n".
            "📦 Мой заказ — посмотреть текущий заказ\n".
            "⏰ Статус — узнать, открыт ли приём заказов\n".
            "🧊 Холодильник — посмотреть доступные остатки\n".
            '🕓 История — посмотреть прошлые заказы',
            $this->keyboardBuilder->navigation(),
        );
    }

    private function sendLoginRequired(int|string $chatId): void
    {
        $this->botClient->sendMessage(
            $chatId,
            'Чтобы видеть свои заказы, привяжите Telegram в профиле на сайте.',
            $this->keyboardBuilder->navigation(),
        );
    }

    private function handleMyOrderCommand(int|string $chatId, User $user): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage(
                $chatId,
                "У вас пока нет активного заказа.\n".
                'Откройте меню и выберите блюда.',
                $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $order = Order::query()
            ->with('items')
            ->where('user_id', $user->id)
            ->where('order_cycle_id', $cycle->id)
            ->first();

        if ($order === null) {
            $this->botClient->sendMessage(
                $chatId,
                "У вас пока нет активного заказа.\n".
                'Откройте меню и выберите блюда.',
                $this->keyboardBuilder->webAppAction('Открыть меню')
                    ?? $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Ваш текущий заказ 📦\n".
            "Статус: {$this->orderStatusLabel($order, $cycle)}\n\n".
            $this->summaryFormatter->format($order),
            $this->keyboardBuilder->webAppAction('Мой заказ')
                ?? $this->keyboardBuilder->navigation(),
        );
    }

    private function handleStatusCommand(int|string $chatId): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage(
                $chatId,
                "Приём заказов сейчас закрыт ⏰\n".
                'Меню можно посмотреть, но оформить заказ получится позже.',
                $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $isOpen = $cycle->status === OrderCycleStatus::Open
            && $cycle->isOpenForOrdering();

        $text = $isOpen
            ? "Приём заказов открыт ✅\nМожно выбрать блюда в меню."
            : "Приём заказов сейчас закрыт ⏰\nМеню можно посмотреть, но оформить заказ получится позже.";

        $this->botClient->sendMessage(
            $chatId,
            $text,
            $this->keyboardBuilder->navigation(),
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

        if ($items->isEmpty()) {
            $this->botClient->sendMessage(
                $chatId,
                'В холодильнике пока ничего нет.',
                $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Холодильник 🧊\nДоступные позиции:\n".$this->fridgeSummaryFormatter->formatActive($items),
            $this->keyboardBuilder->navigation(),
        );

        foreach ($items as $item) {
            $this->botClient->sendMessage(
                $chatId,
                "{$item->title_snapshot}\n".
                "Осталось порций: {$item->quantity_remaining}\n".
                'Годен до: '.($item->expires_at?->format('d.m.Y H:i') ?? 'не указан')."\n".
                "Статус: {$item->status->label()}",
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

        if ($items->isEmpty()) {
            $this->botClient->sendMessage(
                $chatId,
                'Истории заказов пока нет.',
                $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "История заказов 🕓\n".$this->fridgeSummaryFormatter->formatHistory($items),
            $this->keyboardBuilder->navigation(),
        );
    }

    /**
     * @param  array<string, mixed>  $from
     */
    private function findTelegramUser(array $from): ?User
    {
        $telegramId = (string) ($from['id'] ?? '');

        if ($telegramId === '') {
            return null;
        }

        return User::query()
            ->where('telegram_id', $telegramId)
            ->where('is_active', true)
            ->first();
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

    private function extractStartPayload(string $text): ?string
    {
        $parts = preg_split('/\s+/', trim($text));
        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        $payload = trim((string) $parts[1]);

        return $payload === '' ? null : $payload;
    }

    private function extractAliasCommand(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        return match ($normalized) {
            'меню', 'каталог', 'открыть каталог', 'открыть меню', 'menu' => '/menu',
            'мой заказ', 'заказ', 'my order' => '/order',
            'статус', 'status' => '/status',
            'холодильник', 'fridge' => '/fridge',
            'история', 'history' => '/history',
            'помощь', 'help' => '/help',
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

    private function orderStatusLabel(Order $order, OrderCycle $cycle): string
    {
        return match ($cycle->status->value) {
            'closed' => 'Закрыт',
            'sent_to_supplier' => 'Отправлен поставщику',
            'delivered' => 'Доставлен',
            default => $order->status->label(),
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
