<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_edit_user_full_name(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create([
            'name' => 'Lunch User',
            'full_name' => null,
        ]);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'full_name' => 'Мекшун А.Н.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'Мекшун А.Н.',
        ]);
    }

    #[Test]
    public function users_table_displays_full_name_column_value(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create([
            'name' => 'Lunch User',
            'full_name' => 'Иванов И.И.',
        ]);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->assertSee('Иванов И.И.');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        return $admin;
    }
}
