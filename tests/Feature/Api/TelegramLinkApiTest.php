<?php

namespace Tests\Feature\Api;

use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramLinkApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_create_a_telegram_link_token(): void
    {
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');

        $user = User::factory()->create(['telegram_id' => null]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/telegram/link-token')
            ->assertCreated()
            ->assertJsonPath('data.bot_link', 'https://t.me/lunch_demo_bot');

        $deepLink = (string) $response->json('data.deep_link');
        $this->assertStringStartsWith('https://t.me/lunch_demo_bot?start=link_', $deepLink);

        parse_str((string) parse_url($deepLink, PHP_URL_QUERY), $query);
        $start = (string) ($query['start'] ?? '');
        $rawToken = substr($start, strlen('link_'));

        $this->assertNotSame('', $rawToken);
        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
        ]);
        $this->assertDatabaseMissing('telegram_link_tokens', [
            'token_hash' => $rawToken,
        ]);
    }

    #[Test]
    public function guest_cannot_create_a_telegram_link_token(): void
    {
        $this->postJson('/api/telegram/link-token')->assertUnauthorized();
    }

    #[Test]
    public function telegram_link_status_reports_linked_state_and_bot_link(): void
    {
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');

        $user = User::factory()->create(['telegram_id' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/telegram/link-status')
            ->assertOk()
            ->assertJsonPath('data.linked', false)
            ->assertJsonPath('data.link_available', true)
            ->assertJsonPath('data.bot_link', 'https://t.me/lunch_demo_bot');

        $user->update(['telegram_id' => '9501']);

        $this->getJson('/api/telegram/link-status')
            ->assertOk()
            ->assertJsonPath('data.linked', true);
    }

    #[Test]
    public function user_with_existing_telegram_link_cannot_create_new_link_token(): void
    {
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');

        $user = User::factory()->create(['telegram_id' => '9511']);
        Sanctum::actingAs($user);

        $this->postJson('/api/telegram/link-token')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Telegram уже привязан к вашему аккаунту.');

        $this->assertDatabaseCount('telegram_link_tokens', 0);
    }

    #[Test]
    public function new_link_token_invalidates_any_previous_unused_token_for_same_user(): void
    {
        config()->set('services.telegram.bot_username', 'lunch_demo_bot');

        $user = User::factory()->create(['telegram_id' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/telegram/link-token')->assertCreated();
        $this->postJson('/api/telegram/link-token')->assertCreated();

        $tokens = TelegramLinkToken::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $tokens);
        $this->assertNotNull($tokens[0]->used_at);
        $this->assertNull($tokens[1]->used_at);
    }
}
