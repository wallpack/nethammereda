<?php

namespace Tests\Feature\Telegram;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_rejects_requests_when_secret_is_not_configured(): void
    {
        config()->set('services.telegram.webhook_secret', null);

        $this->postJson('/api/telegram/webhook', [])
            ->assertServiceUnavailable();
    }

    #[Test]
    public function webhook_rejects_an_invalid_secret_without_handling_payload(): void
    {
        config()->set('services.telegram.webhook_secret', 'expected-secret');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson('/api/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => 101],
                    'from' => ['id' => 9010, 'first_name' => 'Иван'],
                    'text' => '/order',
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['telegram_id' => '9010']);
    }

    #[Test]
    public function webhook_accepts_a_matching_telegram_secret(): void
    {
        config()->set('services.telegram.webhook_secret', 'expected-secret');

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'expected-secret')
            ->postJson('/api/telegram/webhook', [])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    #[Test]
    public function webhook_setup_refuses_to_register_without_a_secret(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');
        config()->set('services.telegram.webhook_secret', null);
        Http::fake();

        $this->artisan('telegram:webhook:set', ['url' => 'https://example.test/api/telegram/webhook'])
            ->expectsOutput('TELEGRAM_WEBHOOK_SECRET не задан. Webhook не будет установлен.')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    #[Test]
    public function menu_button_info_returns_current_button_payload(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');

        Http::fake([
            'https://api.telegram.org/bottest-bot-token/getChatMenuButton*' => Http::response([
                'ok' => true,
                'result' => [
                    'type' => 'commands',
                ],
            ]),
        ]);

        $this->artisan('telegram:menu-button:info')
            ->expectsOutput('{"type":"commands"}')
            ->assertExitCode(0);
    }

    #[Test]
    public function menu_button_set_uses_webapp_payload_with_https_url(): void
    {
        config()->set('services.telegram.bot_token', 'test-bot-token');
        config()->set('services.telegram.webapp_url', 'https://nethammereda.ru');

        Http::fake([
            'https://api.telegram.org/bottest-bot-token/setChatMenuButton' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->artisan('telegram:menu-button:set')
            ->expectsOutput('Menu button по умолчанию обновлена.')
            ->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.telegram.org/bottest-bot-token/setChatMenuButton') {
                return false;
            }

            $menuButton = $request['menu_button'] ?? null;
            if (! is_array($menuButton)) {
                return false;
            }

            return ($menuButton['type'] ?? null) === 'web_app'
                && ($menuButton['text'] ?? null) === 'Открыть меню'
                && ($menuButton['web_app']['url'] ?? null) === 'https://nethammereda.ru';
        });
    }
}
