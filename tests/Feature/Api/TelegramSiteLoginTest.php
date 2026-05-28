<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramSiteLoginTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:test-telegram-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', self::BOT_TOKEN);
        config()->set('services.telegram.login_auth_ttl', 86400);
    }

    #[Test]
    public function valid_widget_payload_logs_in_existing_user_by_telegram_id(): void
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

        $this->postJson('/api/auth/telegram-login', $payload)
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.telegram_id', '91001')
            ->assertJsonStructure(['data' => ['token']])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.hash');
    }

    #[Test]
    public function invalid_widget_hash_is_rejected(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91002',
            'first_name' => 'Иван',
            'auth_date' => (string) now()->timestamp,
        ]);
        $payload['hash'] = str_repeat('a', 64);

        $this->postJson('/api/auth/telegram-login', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'telegram_id' => '91002',
        ]);
    }

    #[Test]
    public function stale_widget_auth_date_is_rejected(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91003',
            'first_name' => 'Иван',
            'auth_date' => (string) now()->subDays(2)->timestamp,
        ]);

        $this->postJson('/api/auth/telegram-login', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'telegram_id' => '91003',
        ]);
    }

    #[Test]
    public function missing_required_widget_fields_are_rejected(): void
    {
        $this->postJson('/api/auth/telegram-login', [
            'id' => '91004',
        ])->assertUnprocessable();
    }

    #[Test]
    public function unknown_telegram_user_is_created_and_duplicate_is_not_created(): void
    {
        $payload = $this->signedWidgetPayload([
            'id' => '91005',
            'first_name' => 'Новый',
            'last_name' => 'Пользователь',
            'auth_date' => (string) now()->timestamp,
        ]);

        $this->postJson('/api/auth/telegram-login', $payload)
            ->assertOk()
            ->assertJsonPath('data.user.telegram_id', '91005');

        $this->postJson('/api/auth/telegram-login', $payload)
            ->assertOk()
            ->assertJsonPath('data.user.telegram_id', '91005');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'telegram_id' => '91005',
            'full_name' => null,
        ]);
    }

    #[Test]
    public function ordinary_email_password_login_still_works(): void
    {
        User::factory()->create([
            'email' => 'test-login@example.com',
            'password' => Hash::make('secret-123'),
            'telegram_id' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'test-login@example.com',
            'password' => 'secret-123',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']])
            ->assertJsonPath('data.user.email', 'test-login@example.com');
    }

    #[Test]
    public function me_endpoint_works_after_site_telegram_login_and_hides_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '91006',
            'full_name' => null,
            'is_active' => true,
            'role' => UserRole::User,
        ]);

        $payload = $this->signedWidgetPayload([
            'id' => '91006',
            'first_name' => 'Иван',
            'auth_date' => (string) now()->timestamp,
        ]);

        $loginResponse = $this->postJson('/api/auth/telegram-login', $payload)
            ->assertOk();

        $token = (string) $loginResponse->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.telegram_id', '91006')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    #[Test]
    public function telegram_login_config_endpoint_returns_bot_username_and_availability(): void
    {
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');

        $this->getJson('/api/auth/telegram-login/config')
            ->assertOk()
            ->assertJsonPath('data.bot_username', 'lunch_demo_bot')
            ->assertJsonPath('data.login_available', true);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function signedWidgetPayload(array $payload): array
    {
        ksort($payload);

        $checkString = collect($payload)
            ->map(fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash('sha256', self::BOT_TOKEN, true);
        $payload['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return $payload;
    }
}
