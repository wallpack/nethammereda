<?php

namespace Tests\Unit\Telegram;

use App\Services\CurrentOrderCycleResolver;
use App\Services\FridgeItemService;
use App\Services\FridgeSummaryFormatter;
use App\Services\OrderSummaryFormatter;
use App\Services\Telegram\BotClient;
use App\Services\Telegram\UpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function menu_command_returns_webapp_button_and_fallback_link_for_https_url(): void
    {
        config()->set('services.telegram.webapp_url', 'https://example.localhost.run');

        $bot = new class extends BotClient
        {
            /** @var array<int, array{chat_id: int|string, text: string, reply_markup: array<string, mixed>|null}> */
            public array $messages = [];

            public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
            {
                $this->messages[] = [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'reply_markup' => $replyMarkup,
                ];
            }
        };

        $resolver = $this->createMock(CurrentOrderCycleResolver::class);
        $formatter = $this->createMock(OrderSummaryFormatter::class);
        $fridgeSummaryFormatter = $this->createMock(FridgeSummaryFormatter::class);
        $fridgeItemService = $this->createMock(FridgeItemService::class);

        $handler = new UpdateHandler(
            $bot,
            $resolver,
            $formatter,
            $fridgeSummaryFormatter,
            $fridgeItemService,
        );

        $handler->handle([
            'message' => [
                'chat' => ['id' => 101],
                'from' => ['id' => 202, 'first_name' => 'Ivan'],
                'text' => '/menu',
            ],
        ]);

        $this->assertCount(1, $bot->messages);
        $message = $bot->messages[0];

        $this->assertStringContainsString('https://example.localhost.run', $message['text']);
        $this->assertSame(
            'https://example.localhost.run',
            $message['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null,
        );
        $this->assertSame(
            'https://example.localhost.run',
            $message['reply_markup']['inline_keyboard'][1][0]['url'] ?? null,
        );
    }

    #[Test]
    public function menu_command_returns_plain_link_for_non_https_url(): void
    {
        config()->set('services.telegram.webapp_url', 'http://127.0.0.1:8000');

        $bot = new class extends BotClient
        {
            /** @var array<int, array{chat_id: int|string, text: string, reply_markup: array<string, mixed>|null}> */
            public array $messages = [];

            public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
            {
                $this->messages[] = [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'reply_markup' => $replyMarkup,
                ];
            }
        };

        $resolver = $this->createMock(CurrentOrderCycleResolver::class);
        $formatter = $this->createMock(OrderSummaryFormatter::class);
        $fridgeSummaryFormatter = $this->createMock(FridgeSummaryFormatter::class);
        $fridgeItemService = $this->createMock(FridgeItemService::class);

        $handler = new UpdateHandler(
            $bot,
            $resolver,
            $formatter,
            $fridgeSummaryFormatter,
            $fridgeItemService,
        );

        $handler->handle([
            'message' => [
                'chat' => ['id' => 303],
                'from' => ['id' => 404, 'first_name' => 'Petr'],
                'text' => 'menu',
            ],
        ]);

        $this->assertCount(1, $bot->messages);
        $message = $bot->messages[0];

        $this->assertStringContainsString('http://127.0.0.1:8000', $message['text']);
        $this->assertStringContainsString('HTTPS', $message['text']);
        $this->assertNull($message['reply_markup']);
    }
}

