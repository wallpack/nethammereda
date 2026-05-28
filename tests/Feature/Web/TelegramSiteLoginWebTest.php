<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramSiteLoginWebTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:test-telegram-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', self::BOT_TOKEN);
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');
        config()->set('services.telegram.login_auth_ttl', 86400);
    }

    #[Test]
    public function telegram_auth_page_returns_widget_with_bot_username_and_callback_url(): void
    {
        $this->get('/auth/telegram')
            ->assertOk()
            ->assertSee('<title>Nethammereda — вход через Telegram</title>', false)
            ->assertSee('Вход через Telegram')
            ->assertSee('Быстрый вход в Nethammereda')
            ->assertDontSee('Используйте официальный Telegram Login Widget.')
            ->assertSee('Вернуться на сайт')
            ->assertSee('data-testid="telegram-login-logo-mark"', false)
            ->assertSee('telegram-widget.js', false)
            ->assertSee('data-telegram-login="lunch_demo_bot"', false)
            ->assertSee('/auth/telegram/callback', false);
    }

    #[Test]
    public function main_app_page_uses_branded_title_and_does_not_load_telegram_script_on_boot(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Nethammereda — корпоративное питание</title>', false)
            ->assertDontSee('https://telegram.org/js/telegram-web-app.js');
    }

    #[Test]
    public function valid_callback_payload_logs_in_existing_user_and_returns_one_time_api_token(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '91001',
            'full_name' => null,
            'is_active' => true,
        ]);

        $payload = $this->signedWidgetPayload([
            'id' => '91001',
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
            'auth_date' => (string) now()->timestamp,
        ]);

        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=success');

        $this->assertAuthenticatedAs($user);

        $tokenResponse = $this->getJson('/auth/telegram/token')
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->assertNotSame('', (string) $tokenResponse->json('data.token'));

        $this->getJson('/auth/telegram/token')->assertNotFound();
    }

    #[Test]
    public function callback_with_unknown_user_creates_single_user_without_duplicates(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91005',
            'first_name' => 'Новый',
            'last_name' => 'Пользователь',
            'auth_date' => (string) now()->timestamp,
        ]);

        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=success');
        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=success');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'telegram_id' => '91005',
            'full_name' => null,
        ]);
    }

    #[Test]
    public function invalid_hash_redirects_with_error(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91002',
            'first_name' => 'Иван',
            'auth_date' => (string) now()->timestamp,
        ]);
        $payload['hash'] = str_repeat('a', 64);

        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '91002',
        ]);
    }

    #[Test]
    public function stale_auth_date_redirects_with_error(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91003',
            'first_name' => 'Иван',
            'auth_date' => (string) now()->subDays(2)->timestamp,
        ]);

        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'telegram_id' => '91003',
        ]);
    }

    /**
     * @param  array<string, scalar>  $payload
     * @return array<string, scalar>
     */
    private function signedWidgetPayload(array $payload): array
    {
        ksort($payload);

        $checkString = collect($payload)
            ->map(function (int|string|float|bool $value, string $key): string {
                if (is_bool($value)) {
                    return $value ? "{$key}=true" : "{$key}=false";
                }

                return "{$key}={$value}";
            })
            ->implode("\n");

        $secretKey = hash('sha256', self::BOT_TOKEN, true);
        $payload['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return $payload;
    }
}
