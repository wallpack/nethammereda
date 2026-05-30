<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessSessionTest extends TestCase
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
    public function guest_is_redirected_to_admin_login_when_opening_admin_dashboard(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    #[Test]
    public function ordinary_user_sees_friendly_forbidden_page_on_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/admin')
            ->assertForbidden()
            ->assertSee('У вас нет доступа к админ-панели.')
            ->assertSee('Войдите под администратором или выйдите из текущего аккаунта.')
            ->assertSee('На сайт')
            ->assertSee('Выйти и войти как администратор')
            ->assertSee('action="'.route('admin.logout-and-login').'"', false);
    }

    #[Test]
    public function ordinary_user_can_logout_and_go_to_admin_login_from_forbidden_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'web');

        $this->post('/admin/logout-and-login')
            ->assertRedirect('/admin/login');

        $this->assertGuest('web');
    }

    #[Test]
    public function admin_user_can_open_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Панель управления');
    }

    #[Test]
    public function admin_user_menu_keeps_logout_without_theme_switcher(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        $panel = Filament::getPanel('admin');
        $userMenuItems = $panel->getUserMenuItems();

        $this->assertTrue($panel->hasDarkMode());
        $this->assertTrue($panel->hasDarkModeForced());
        $this->assertArrayHasKey('logout', $userMenuItems);
        $this->assertSame('Выйти', $userMenuItems['logout']->getLabel());

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('fi-theme-switcher', false)
            ->assertDontSee('Enable light theme')
            ->assertDontSee('Enable dark theme')
            ->assertDontSee('Enable system theme');
    }

    #[Test]
    public function telegram_site_login_can_override_admin_web_session_and_then_admin_route_is_denied_with_friendly_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $ordinaryUser = User::factory()->create([
            'telegram_id' => '91042',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        $payload = $this->signedWidgetPayload([
            'id' => '91042',
            'first_name' => 'Ivan',
            'auth_date' => (string) now()->timestamp,
        ]);

        $this->get('/auth/telegram/callback?'.http_build_query($payload))
            ->assertRedirect('/?telegram_login=success');

        $this->assertAuthenticatedAs($ordinaryUser, 'web');

        $this->get('/admin')
            ->assertForbidden()
            ->assertSee('У вас нет доступа к админ-панели.')
            ->assertSee('Выйти и войти как администратор');
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
