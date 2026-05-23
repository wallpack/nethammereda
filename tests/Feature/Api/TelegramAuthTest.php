<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:test-telegram-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', self::BOT_TOKEN);
        config()->set('services.telegram.webapp_auth_ttl', 3600);
    }

    #[Test]
    public function valid_webapp_init_data_authenticates_telegram_user(): void
    {
        $initData = $this->signedInitData([
            'auth_date' => (string) now()->timestamp,
            'query_id' => 'query-valid',
            'user' => json_encode([
                'id' => 9001,
                'first_name' => 'Иван',
                'username' => 'ivan',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])
            ->assertOk()
            ->assertJsonPath('data.user.telegram_id', '9001')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => '9001',
            'name' => 'Иван',
        ]);
    }

    #[Test]
    public function tampered_webapp_init_data_is_rejected(): void
    {
        $initData = $this->signedInitData([
            'auth_date' => (string) now()->timestamp,
            'query_id' => 'query-invalid',
            'user' => json_encode(['id' => 9002, 'first_name' => 'Иван'], JSON_THROW_ON_ERROR),
        ]);
        $tampered = str_replace('9002', '9999', $initData);

        $this->postJson('/api/auth/telegram', ['init_data' => $tampered])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'telegram_id' => '9999',
        ]);
    }

    #[Test]
    public function signed_webapp_data_without_auth_date_is_rejected(): void
    {
        $initData = $this->signedInitData([
            'query_id' => 'query-no-date',
            'user' => json_encode(['id' => 9003, 'first_name' => 'Иван'], JSON_THROW_ON_ERROR),
        ]);

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'telegram_id' => '9003',
        ]);
    }

    #[Test]
    public function signed_webapp_data_outside_the_allowed_time_window_is_rejected(): void
    {
        $oldData = $this->signedInitData([
            'auth_date' => (string) now()->subHours(2)->timestamp,
            'user' => json_encode(['id' => 9004], JSON_THROW_ON_ERROR),
        ]);
        $futureData = $this->signedInitData([
            'auth_date' => (string) now()->addMinutes(2)->timestamp,
            'user' => json_encode(['id' => 9005], JSON_THROW_ON_ERROR),
        ]);

        $this->postJson('/api/auth/telegram', ['init_data' => $oldData])
            ->assertUnprocessable();
        $this->postJson('/api/auth/telegram', ['init_data' => $futureData])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['telegram_id' => '9004']);
        $this->assertDatabaseMissing('users', ['telegram_id' => '9005']);
    }

    #[Test]
    public function telegram_auth_does_not_reactivate_a_disabled_linked_user(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '9006',
            'role' => UserRole::User,
            'is_active' => false,
        ]);
        $initData = $this->signedInitData([
            'auth_date' => (string) now()->timestamp,
            'user' => json_encode(['id' => 9006, 'first_name' => 'Иван'], JSON_THROW_ON_ERROR),
        ]);

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])
            ->assertForbidden()
            ->assertJsonPath('message', 'Пользователь деактивирован.');

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function telegram_auth_preserves_the_role_of_an_existing_linked_user(): void
    {
        User::factory()->create([
            'telegram_id' => '9007',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
        $initData = $this->signedInitData([
            'auth_date' => (string) now()->timestamp,
            'user' => json_encode(['id' => 9007, 'first_name' => 'Админ'], JSON_THROW_ON_ERROR),
        ]);

        $this->postJson('/api/auth/telegram', ['init_data' => $initData])
            ->assertOk()
            ->assertJsonPath('data.user.role', UserRole::Admin->value);

        $this->assertDatabaseHas('users', [
            'telegram_id' => '9007',
            'role' => UserRole::Admin->value,
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function signedInitData(array $payload): string
    {
        ksort($payload);

        $checkString = collect($payload)
            ->map(fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");
        $secretKey = hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true);
        $payload['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    }
}
