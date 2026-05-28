<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\AdminLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_page_renders_required_elements(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('nh-admin-login-shell', false)
            ->assertSee('data-testid="admin-login-logo"', false)
            ->assertSee('Вход в панель управления')
            ->assertSee('data-testid="admin-login-email"', false)
            ->assertSee('data-testid="admin-login-password"', false)
            ->assertSee('data-testid="admin-login-remember"', false)
            ->assertSee('data-testid="admin-login-forgot-password"', false)
            ->assertSee('href="https://t.me/spicyweb"', false)
            ->assertSee('Запомнить меня')
            ->assertSee('Забыли пароль?')
            ->assertSee('Войти в систему');
    }

    #[Test]
    public function removed_promo_logo_description_and_footer_do_not_render(): void
    {
        $html = $this->get('/admin/login')->getContent();

        $this->assertStringNotContainsString('class="nh-admin-login-brand"', $html);
        $this->assertStringNotContainsString('images/brand/nethammer-icon.svg', $html);
        $this->assertStringContainsString('Nethammer<span class="nh-brand-logo__accent">eda</span>', $html);
        $this->assertStringNotContainsString('NethammerEda · Admin Console · Версия 1.0', $html);
        $this->assertStringNotContainsString('Управляйте меню, заказами сотрудников и остатками блюд в едином интерфейсе.', $html);
    }

    #[Test]
    public function forgot_password_link_is_rendered_in_options_row_after_password(): void
    {
        $html = $this->get('/admin/login')->getContent();

        $optionsPosition = strpos($html, 'nh-admin-login-options');
        $rememberPosition = strpos($html, 'data-testid="admin-login-remember"');
        $forgotPosition = strpos($html, 'data-testid="admin-login-forgot-password"');

        $this->assertNotFalse($optionsPosition);
        $this->assertNotFalse($rememberPosition);
        $this->assertNotFalse($forgotPosition);
        $this->assertGreaterThan($optionsPosition, $rememberPosition);
        $this->assertGreaterThan($rememberPosition, $forgotPosition);
    }

    #[Test]
    public function remember_checkbox_toggles_livewire_state(): void
    {
        Livewire::test(AdminLogin::class)
            ->assertSet('data.remember', false)
            ->set('data.remember', true)
            ->assertSet('data.remember', true)
            ->set('data.remember', false)
            ->assertSet('data.remember', false);
    }

    #[Test]
    public function password_visibility_controls_remain_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('aria-label="Показать пароль"', false)
            ->assertSee('aria-label="Скрыть пароль"', false)
            ->assertSee('x-bind:type="isPasswordRevealed ? \'text\' : \'password\'"', false)
            ->assertSee("input.type = reveal ? 'text' : 'password';", false);
    }

    #[Test]
    public function empty_login_submission_shows_validation_errors_without_breaking_page(): void
    {
        Livewire::test(AdminLogin::class)
            ->set('data.email', '')
            ->set('data.password', '')
            ->call('authenticate')
            ->assertHasErrors([
                'data.email' => 'required',
                'data.password' => 'required',
            ])
            ->assertSee('Вход в панель управления');
    }

    #[Test]
    public function valid_admin_credentials_keep_existing_submit_flow(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        Livewire::test(AdminLogin::class)
            ->set('data.email', $admin->email)
            ->set('data.password', 'password')
            ->set('data.remember', true)
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }
}
