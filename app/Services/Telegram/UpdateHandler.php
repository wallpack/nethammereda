<?php

namespace App\Services\Telegram;

use App\Enums\FridgeItemStatus;
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

        if ($command === '/start') {
            $this->handleStartCommand($chatId, $this->findTelegramUser($from));

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

        if ($command !== '') {
            Log::info('Telegram command received', [
                'telegram_id' => $user->telegram_id,
                'chat_id' => $chatId,
                'command' => $command,
            ]);
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
                'Неизвестная команда. Используйте /menu, /order (/my_order), /status, /fridge, /history или /help.',
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
        $cycleSummary = $this->menuCycleSummary();
        $webappUrl = trim((string) config('services.telegram.webapp_url'));
        $secureWebAppUrl = $this->keyboardBuilder->secureWebAppUrl();

        if ($secureWebAppUrl !== null) {
            $this->botClient->sendMessage(
                $chatId,
                "{$cycleSummary}\n\nОткройте каталог кнопкой ниже.\n".
                "Если кнопка не открылась, используйте ссылку: {$secureWebAppUrl}",
                [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Открыть каталог',
                                'web_app' => ['url' => $secureWebAppUrl],
                            ],
                        ],
                        [
                            [
                                'text' => 'Открыть ссылкой',
                                'url' => $secureWebAppUrl,
                            ],
                        ],
                    ],
                ],
            );

            return;
        }

        if (filter_var($webappUrl, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($webappUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->botClient->sendMessage(
                $chatId,
                "{$cycleSummary}\n\nКаталог: {$webappUrl}\n\n".
                'Откройте ссылку напрямую. Кнопка внутри Telegram доступна только для защищенного HTTPS-адреса.',
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "{$cycleSummary}\n\nКаталог пока нельзя открыть из Telegram. Попробуйте позже.",
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

    private function handleStartCommand(int|string $chatId, ?User $user): void
    {
        $text = "NethammerEda: бот корпоративных обедов.\n\n".
            "Здесь можно открыть каталог, посмотреть заказ и проверить холодильник.";

        if ($user === null) {
            $text .= "\n\nЧтобы увидеть личные данные, откройте каталог и войдите через Telegram. ".
                'Если у вас уже есть рабочий аккаунт, попросите администратора привязать Telegram.';
        }

        $this->botClient->sendMessage($chatId, $text, $this->keyboardBuilder->navigation());
    }

    private function handleHelpCommand(int|string $chatId): void
    {
        $this->botClient->sendMessage(
            $chatId,
            "Как пользоваться NethammerEda:\n\n".
            "/menu: открыть каталог и выбрать блюда.\n".
            "/order: посмотреть текущий заказ. Команда /my_order тоже работает.\n".
            "/fridge: открыть холодильник с доставленной едой и отметить, что вы съели или выбросили.\n".
            "/history: посмотреть съеденные, выброшенные и просроченные позиции.\n\n".
            "Открывайте каталог кнопкой ниже: внутри можно войти через Telegram и оформить заказ.\n\n".
            "Если заказ не отображается, проверьте, что вы вошли через Telegram в каталоге, ".
            'или попросите администратора привязать ваш аккаунт.',
            $this->keyboardBuilder->navigation(),
        );
    }

    private function sendLoginRequired(int|string $chatId): void
    {
        $this->botClient->sendMessage(
            $chatId,
            "Чтобы увидеть свой заказ и холодильник, откройте каталог и войдите через Telegram.\n".
            'Если у вас уже есть рабочий аккаунт, попросите администратора привязать Telegram.',
            $this->keyboardBuilder->navigation(),
        );
    }

    private function handleMyOrderCommand(int|string $chatId, User $user): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage(
                $chatId,
                'Сейчас нет активной недели заказа.',
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
                "У вас пока нет заказа на неделю «{$cycle->title}».\n".
                'Откройте каталог, чтобы выбрать блюда.',
                $this->keyboardBuilder->webAppAction('Открыть каталог')
                    ?? $this->keyboardBuilder->navigation(),
            );

            return;
        }

        $this->botClient->sendMessage(
            $chatId,
            "Неделя: {$cycle->title}\n".
            "Статус: {$this->orderStatusLabel($order, $cycle)}\n\n".
            $this->summaryFormatter->format($order),
            $this->keyboardBuilder->webAppAction('Открыть мой заказ')
                ?? $this->keyboardBuilder->navigation(),
        );
    }

    private function handleStatusCommand(int|string $chatId): void
    {
        $cycle = $this->resolver->resolve();
        if ($cycle === null) {
            $this->botClient->sendMessage(
                $chatId,
                'Сейчас нет активной недели заказа.',
                $this->keyboardBuilder->navigation(),
            );

            return;
        }

        [$status, $availability] = match ($cycle->status->value) {
            'open' => $cycle->isOpenForOrdering()
                ? ['Заказ открыт', 'Заказывать еще можно.']
                : ['Прием заказов завершен', 'Дедлайн прошел, заказывать уже нельзя.'],
            'closed' => ['Прием заказов завершен', 'Цикл закрыт, заказывать уже нельзя.'],
            'sent_to_supplier' => ['Заказ отправлен поставщику', 'Заказывать уже нельзя.'],
            'delivered' => ['Доставка отмечена, проверьте холодильник', 'Заказывать уже нельзя.'],
            default => [$this->cycleStatusLabel($cycle), 'Заказывать сейчас нельзя.'],
        };

        $this->botClient->sendMessage(
            $chatId,
            "Текущая неделя: {$cycle->title}\n".
            "Статус: {$status}\n".
            "Дедлайн: {$cycle->closes_at->format('d.m.Y H:i')}\n".
            $availability,
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

        $this->botClient->sendMessage(
            $chatId,
            "Ваш холодильник:\n".$this->fridgeSummaryFormatter->formatActive($items),
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

        $this->botClient->sendMessage(
            $chatId,
            "История холодильника:\n".$this->fridgeSummaryFormatter->formatHistory($items),
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

    private function extractAliasCommand(string $text): ?string
    {
        $normalized = mb_strtolower(trim($text));

        return match ($normalized) {
            'меню', 'каталог', 'открыть каталог', 'menu' => '/menu',
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
