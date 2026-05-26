<?php

namespace Tests\Feature\Api;

use App\Enums\OrderCycleStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_user_response_includes_id_name_email_and_full_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Катя Nethammer',
            'full_name' => 'Катя Н. Е.',
            'email' => 'katya@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Катя Nethammer')
            ->assertJsonPath('data.full_name', 'Катя Н. Е.')
            ->assertJsonPath('data.email', 'katya@example.com');
    }

    #[Test]
    public function authenticated_user_can_update_full_name(): void
    {
        $user = User::factory()->create([
            'full_name' => null,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/profile', [
            'full_name' => '  Чертова Е.Н.  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.full_name', 'Чертова Е.Н.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'Чертова Е.Н.',
        ]);
    }

    #[Test]
    public function empty_full_name_is_stored_as_null(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Старое ФИО',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/me/profile', [
            'full_name' => '   ',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.full_name', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => null,
        ]);
    }

    #[Test]
    public function guest_cannot_update_full_name(): void
    {
        $this->patchJson('/api/me/profile', [
            'full_name' => 'Мекшун А.Н.',
        ])->assertUnauthorized();
    }

    #[Test]
    public function guest_cannot_access_protected_catalog_endpoints(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => 1,
            'quantity' => 1,
        ])->assertUnauthorized();

        $this->postJson('/api/my-order/submit')->assertUnauthorized();

        $this->getJson('/api/my-fridge')->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_can_add_menu_item_to_current_order(): void
    {
        $user = User::factory()->create();
        $menuItem = $this->createOrderableMenuItem();

        Sanctum::actingAs($user);

        $this->postJson('/api/my-order/items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.items.0.menu_item_id', $menuItem->id)
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    #[Test]
    public function catalog_api_returns_safe_image_display_url(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Суп Борщ',
            'price' => 210,
            'image_path' => 'menu-items/manual/1/sup-borshch.png',
            'image_url' => 'https://example.test/supplier-borscht.png',
            'is_active' => true,
        ]);

        $this->getJson('/api/menu/items')
            ->assertOk()
            ->assertJsonPath('data.0.image_display_url', url('/storage/menu-items/manual/1/sup-borshch.png'));
    }

    #[Test]
    public function catalog_api_does_not_expose_javascript_image_url_as_display_url(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Суп Борщ',
            'price' => 210,
            'image_url' => 'javascript:alert(1)',
            'is_active' => true,
        ]);

        $this->getJson('/api/menu/items')
            ->assertOk()
            ->assertJsonPath('data.0.image_url', 'javascript:alert(1)')
            ->assertJsonPath('data.0.image_display_url', null);
    }

    #[Test]
    public function logout_revokes_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web-login')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    private function createOrderableMenuItem(): MenuItem
    {
        $category = MenuCategory::query()->create([
            'name' => 'Тестовая категория',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        OrderCycle::query()->create([
            'title' => 'Тестовая неделя',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);

        return MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Тестовое блюдо',
            'price' => 250,
            'is_active' => true,
        ]);
    }
}
