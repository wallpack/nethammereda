<?php

namespace Tests\Feature\Domain;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->sourceDirectory = storage_path('app/testing-menu-images');
        File::deleteDirectory($this->sourceDirectory);
        File::ensureDirectoryExists($this->sourceDirectory);
        File::deleteDirectory(storage_path('app/menu-audit'));
        File::deleteDirectory(storage_path('app/menu-images'));
    }

    #[Test]
    public function attach_local_dry_run_does_not_change_menu_items(): void
    {
        $item = $this->createMenuItem('Котлета домашняя');
        $this->putImage('Котлета домашняя.png');

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertNull($item->image_path);
        $this->assertNull($item->image_source);
        $this->assertNull($item->image_assigned_at);
        $this->assertFileExists(storage_path('app/menu-audit/image-attach-dry-run.csv'));
    }

    #[Test]
    public function attach_local_matches_russian_filename_to_menu_item(): void
    {
        $item = $this->createMenuItem('Котлета домашняя');
        $this->putImage('Котлета домашняя.png');

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertSame('manual', $item->image_source);
        $this->assertNotNull($item->image_assigned_at);
        $this->assertStringStartsWith("menu-items/manual/{$item->id}/", $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    #[Test]
    public function attach_local_matches_menu_item_id_filename(): void
    {
        $item = $this->createMenuItem('Суп Борщ');
        $this->putImage("menu-item-{$item->id}.png");

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertSame('manual', $item->image_source);
        Storage::disk('public')->assertExists($item->image_path);
    }

    #[Test]
    public function attach_local_ignores_unsupported_file_types(): void
    {
        $item = $this->createMenuItem('Суп Борщ');
        file_put_contents($this->sourceDirectory.'/menu-item-'.$item->id.'.svg', '<svg></svg>');

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertNull($item->image_path);
    }

    #[Test]
    public function attach_local_does_not_overwrite_existing_image_path_without_force(): void
    {
        $item = $this->createMenuItem('Суп Борщ', [
            'image_path' => 'menu-items/manual/existing.png',
            'image_source' => 'manual',
            'image_assigned_at' => now(),
        ]);
        $this->putImage("menu-item-{$item->id}.png");

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertSame('menu-items/manual/existing.png', $item->image_path);
    }

    #[Test]
    public function attach_local_force_overwrites_existing_image_path(): void
    {
        $item = $this->createMenuItem('Суп Борщ', [
            'image_path' => 'menu-items/manual/existing.png',
            'image_source' => 'manual',
            'image_assigned_at' => now()->subDay(),
        ]);
        $this->putImage("menu-item-{$item->id}.png");

        $this->artisan('menu-images:attach-local', [
            '--path' => $this->sourceDirectory,
            '--force' => true,
        ])->assertExitCode(0);

        $item->refresh();
        $this->assertNotSame('menu-items/manual/existing.png', $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    #[Test]
    public function export_list_creates_menu_items_csv_for_future_image_generation(): void
    {
        $item = $this->createMenuItem('Суп Борщ', [
            'description' => 'Горячий суп',
        ]);

        $this->artisan('menu-images:export-list')->assertExitCode(0);

        $path = storage_path('app/menu-images/menu-items-for-images.csv');
        $this->assertFileExists($path);
        $csv = file_get_contents($path);

        $this->assertStringContainsString((string) $item->id, $csv);
        $this->assertStringContainsString('Суп Борщ', $csv);
        $this->assertStringContainsString('menu-item-'.$item->id.'-sup-borshch.png', $csv);
        $this->assertStringContainsString('Use the attached screenshot as a style reference', $csv);
    }

    private function createMenuItem(string $title, array $attributes = []): MenuItem
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['name' => 'Тестовая категория'],
            ['sort_order' => 10, 'is_active' => true],
        );

        return MenuItem::query()->create(array_merge([
            'category_id' => $category->id,
            'title' => $title,
            'price' => 250,
            'is_active' => true,
        ], $attributes));
    }

    private function putImage(string $filename): void
    {
        file_put_contents($this->sourceDirectory.'/'.$filename, $this->pngBytes());
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/axMnykAAAAASUVORK5CYII=',
            true,
        );
    }
}
