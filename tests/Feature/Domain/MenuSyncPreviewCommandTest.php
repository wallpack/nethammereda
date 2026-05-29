<?php

namespace Tests\Feature\Domain;

use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use App\Models\MenuCategory;
use App\Models\MenuImport;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuSyncPreviewCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preview_shows_active_items_that_would_be_deactivated(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/preview.csv', implode("\n", [
            'Категория;Название;Цена',
            'Супы;Борщ;210',
        ]));

        $soups = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $salads = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'category_id' => $soups->id,
            'title' => 'Борщ',
            'supplier_name' => 'Борщ',
            'price' => 210,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $salads->id,
            'title' => 'Лишний салат',
            'supplier_name' => 'Лишний салат',
            'price' => 180,
            'is_active' => true,
        ]);

        $import = MenuImport::query()->create([
            'original_filename' => 'preview.csv',
            'stored_path' => 'menu-imports/preview.csv',
            'status' => MenuImportStatus::Imported,
            'format' => MenuImportFormat::Csv,
            'rows_total' => 1,
            'rows_valid' => 1,
            'rows_failed' => 0,
            'imported_at' => now(),
        ]);

        $this->artisan('menu:sync-preview', ['--import-id' => $import->id])
            ->assertSuccessful()
            ->expectsOutputToContain("import_id={$import->id}")
            ->expectsOutputToContain('to_deactivate_count=1')
            ->expectsOutputToContain('Лишний салат');
    }

    #[Test]
    public function preview_does_not_change_catalog_when_import_file_is_invalid(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/invalid.csv', implode("\n", [
            'foo;bar;baz',
            'one;two;three',
        ]));

        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ',
            'supplier_name' => 'Борщ',
            'price' => 210,
            'is_active' => true,
        ]);

        $import = MenuImport::query()->create([
            'original_filename' => 'invalid.csv',
            'stored_path' => 'menu-imports/invalid.csv',
            'status' => MenuImportStatus::Imported,
            'format' => MenuImportFormat::Csv,
            'rows_total' => 0,
            'rows_valid' => 0,
            'rows_failed' => 0,
            'imported_at' => now(),
        ]);

        $this->artisan('menu:sync-preview', ['--import-id' => $import->id])
            ->assertFailed()
            ->expectsOutputToContain('Импорт-файл не прошел валидацию');

        $this->assertTrue($item->fresh()->is_active);
    }
}
