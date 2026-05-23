<?php

namespace Tests\Feature\Admin;

use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use App\Enums\UserRole;
use App\Filament\Resources\MenuImports\MenuImportResource;
use App\Filament\Resources\MenuImports\Pages\ListMenuImports;
use App\Filament\Resources\MenuImports\Pages\ViewMenuImport;
use App\Models\MenuImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuImportResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resource_index_is_available_to_admins_and_renders_imports(): void
    {
        $admin = $this->actingAsAdmin();
        $import = $this->createMenuImport($admin);

        $this->get(MenuImportResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Импорт меню')
            ->assertSee('Загрузить меню');

        Livewire::test(ListMenuImports::class)
            ->assertCanSeeTableRecords([$import])
            ->assertSee('menu.csv')
            ->assertSee('Импортирован')
            ->assertSee('Admin User')
            ->assertSee('1')
            ->assertSee('CSV');
    }

    #[Test]
    public function view_page_renders_readable_error_report_without_raw_json(): void
    {
        $this->actingAsAdmin();
        $import = MenuImport::query()->create([
            'original_filename' => 'broken.csv',
            'stored_path' => 'menu-imports/broken.csv',
            'status' => MenuImportStatus::Failed,
            'format' => MenuImportFormat::Csv,
            'rows_total' => 1,
            'rows_valid' => 0,
            'rows_failed' => 1,
            'error_report' => [
                'summary' => 'Импорт не применен: исправьте ошибки в файле и загрузите его заново.',
                'errors' => [
                    [
                        'row' => 2,
                        'field' => 'price',
                        'message' => 'Поле «Цена» должно быть неотрицательным числом.',
                        'value' => 'дорого',
                    ],
                ],
            ],
        ]);

        Livewire::test(ViewMenuImport::class, ['record' => $import->id])
            ->assertSee('Импорт меню')
            ->assertSee('Отчет об ошибках')
            ->assertSee('Импорт не применен')
            ->assertSee('Поле «Цена»')
            ->assertSee('дорого')
            ->assertDontSee('error_report')
            ->assertDontSee('stack');
    }

    #[Test]
    public function menu_import_labels_are_russian_and_history_is_read_only(): void
    {
        $this->actingAsAdmin();
        $import = $this->createMenuImport();

        $this->assertSame('Импорт меню', MenuImportResource::getNavigationLabel());
        $this->assertSame('импорт меню', MenuImportResource::getModelLabel());
        $this->assertSame('импорт меню', MenuImportResource::getPluralModelLabel());
        $this->assertFalse(MenuImportResource::canCreate());
        $this->assertFalse(MenuImportResource::canEdit($import));
        $this->assertFalse(MenuImportResource::canDelete($import));
        $this->assertFalse(MenuImportResource::canDeleteAny());

        Livewire::test(ListMenuImports::class)
            ->assertSee('Имя файла')
            ->assertSee('Строки')
            ->assertSee('Проверен')
            ->assertSee('Ошибка');
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        return $admin;
    }

    private function createMenuImport(?User $admin = null): MenuImport
    {
        $admin ??= User::factory()->create([
            'name' => 'Admin User',
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        return MenuImport::query()->create([
            'original_filename' => 'menu.csv',
            'stored_path' => 'menu-imports/menu.csv',
            'status' => MenuImportStatus::Imported,
            'format' => MenuImportFormat::Csv,
            'rows_total' => 1,
            'rows_valid' => 1,
            'rows_failed' => 0,
            'imported_by' => $admin->id,
            'imported_at' => now(),
        ]);
    }
}
