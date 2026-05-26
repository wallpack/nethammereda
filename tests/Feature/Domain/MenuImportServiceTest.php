<?php

namespace Tests\Feature\Domain;

use App\Enums\MenuImportStatus;
use App\Enums\OrderCycleStatus;
use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\MenuImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuImportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function successful_csv_import_creates_categories_and_menu_items(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/menu.csv', implode("\n", [
            'Категория;Название;Цена;Вес;Калории;Белки;Жиры;Углеводы;Описание;image_url;supplier_code',
            'Супы;Борщ;210.50;350 г;180;8.5;9;14;Сытный обед;https://example.com/borscht.jpg;SUP-001',
        ]));
        $admin = User::factory()->create();

        $import = app(MenuImportService::class)->importStoredFile(
            storedPath: 'menu-imports/menu.csv',
            originalFilename: 'menu.csv',
            importedBy: $admin,
        );

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertSame(1, $import->rows_total);
        $this->assertSame(1, $import->rows_valid);
        $this->assertSame(0, $import->rows_failed);
        $this->assertNotNull($import->imported_at);

        $category = MenuCategory::query()->where('name', 'Супы')->firstOrFail();
        $menuItem = MenuItem::query()->where('supplier_code', 'SUP-001')->firstOrFail();

        $this->assertTrue($category->is($menuItem->category));
        $this->assertSame('Борщ', $menuItem->title);
        $this->assertSame('Борщ', $menuItem->supplier_name);
        $this->assertSame('210.50', $menuItem->price);
        $this->assertSame('350 г', $menuItem->weight);
        $this->assertSame(180, $menuItem->calories);
        $this->assertSame('8.50', $menuItem->proteins);
        $this->assertSame('9.00', $menuItem->fats);
        $this->assertSame('14.00', $menuItem->carbs);
        $this->assertSame('Сытный обед', $menuItem->description);
        $this->assertSame('https://example.com/borscht.jpg', $menuItem->image_url);
        $this->assertTrue($menuItem->is_active);
    }

    #[Test]
    public function repeated_import_updates_existing_menu_item_without_creating_duplicate(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $existing = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ старый',
            'supplier_name' => 'Борщ старый',
            'price' => 190,
            'supplier_code' => 'SUP-001',
            'is_active' => true,
        ]);

        $this->importCsv([
            'category;name;price;supplier_code',
            'Супы;Борщ новый;230;SUP-001',
        ]);

        $this->assertSame(1, MenuItem::query()->count());
        $this->assertDatabaseHas('menu_items', [
            'id' => $existing->id,
            'title' => 'Борщ новый',
            'supplier_name' => 'Борщ новый',
            'supplier_code' => 'SUP-001',
        ]);
    }

    #[Test]
    public function supplier_price_name_is_stored_as_supplier_name_while_title_becomes_laconic(): void
    {
        $this->importCsv([
            'Категория;Наименование продукции;Цена',
            'Супы;Суп "Борщ";210',
            'Супы;Суп "Гороховый" (300г.) новинка;215',
            'Выпечка;Блинчики Наслаждение (с творожным сыром и маком);95',
            'Выпечка;"""Студенческий""с капустой и ветчиной";67',
            'Выпечка;Хот дог "Мексика" (ГМС);90',
            'Пирожное;Вафли "Гранд" с начинкой (сгущ.) (20шт);520',
            'Вторые блюда;Сэндвич с копченой курицей (150 г.) (треугольный);120',
            'Вторые блюда;Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г);125',
        ]);

        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Суп "Борщ"',
            'title' => 'Борщ',
            'price' => 210,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Суп "Гороховый" (300г.) новинка',
            'title' => 'Гороховый суп',
            'price' => 215,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Блинчики Наслаждение (с творожным сыром и маком)',
            'title' => 'Блинчики с творожным сыром и маком',
            'price' => 95,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => '"Студенческий"с капустой и ветчиной',
            'title' => 'Студенческий с капустой и ветчиной',
            'price' => 67,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Хот дог "Мексика" (ГМС)',
            'title' => 'Хот-дог Мексика',
            'price' => 90,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Сэндвич с копченой курицей (150 г.) (треугольный)',
            'title' => 'Сэндвич с копченой курицей',
            'price' => 120,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Вафли "Гранд" с начинкой (сгущ.) (20шт)',
            'title' => 'Вафли Гранд со сгущёнкой',
            'price' => 520,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)',
            'title' => 'Котлета по-киевски с пюре',
            'price' => 125,
        ]);
    }

    #[Test]
    public function import_with_missing_required_column_fails_without_applying_rows(): void
    {
        $import = $this->importCsv([
            'Название;Цена',
            'Борщ;210',
        ]);

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertSame(1, $import->rows_total);
        $this->assertSame(0, $import->rows_valid);
        $this->assertSame(1, $import->rows_failed);
        $this->assertDatabaseCount('menu_items', 0);
        $this->assertStringContainsString('Категория', (string) data_get($import->error_report, 'errors.0.message'));
        $this->assertStringNotContainsString('Exception', json_encode($import->error_report, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function import_with_invalid_price_fails_without_creating_partial_menu_items(): void
    {
        $import = $this->importCsv([
            'Категория;Название;Цена',
            'Супы;Борщ;дорого',
            'Салаты;Оливье;180',
        ]);

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertSame(2, $import->rows_total);
        $this->assertSame(1, $import->rows_valid);
        $this->assertSame(1, $import->rows_failed);
        $this->assertDatabaseCount('menu_items', 0);
        $this->assertStringContainsString('Цена', (string) data_get($import->error_report, 'errors.0.message'));
    }

    #[Test]
    public function existing_order_items_keep_references_and_snapshots_after_menu_item_update(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $menuItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ',
            'price' => 200,
            'supplier_code' => 'SUP-001',
            'is_active' => true,
        ]);
        $orderItem = $this->createOrderItem($menuItem);

        $this->importCsv([
            'Категория;Название;Цена;supplier_code',
            'Супы;Борщ обновленный;250;SUP-001',
        ]);

        $orderItem->refresh();
        $menuItem->refresh();

        $this->assertSame($menuItem->id, $orderItem->menu_item_id);
        $this->assertSame('Борщ', $orderItem->title_snapshot);
        $this->assertSame('200.00', $orderItem->price_snapshot);
        $this->assertSame('Борщ обновленный', $menuItem->title);
        $this->assertSame('250.00', $menuItem->price);
    }

    #[Test]
    public function dishes_missing_from_new_file_are_not_deleted_or_deactivated(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $oldItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Старое блюдо',
            'price' => 200,
            'is_active' => true,
        ]);

        $this->importCsv([
            'Категория;Название;Цена',
            'Салаты;Новый салат;180',
        ]);

        $oldItem->refresh();

        $this->assertDatabaseCount('menu_items', 2);
        $this->assertSame('Старое блюдо', $oldItem->title);
        $this->assertTrue($oldItem->is_active);
    }

    #[Test]
    public function external_id_and_supplier_code_are_used_for_updates_before_name_matching(): void
    {
        $oldCategory = MenuCategory::query()->create([
            'name' => 'Старое меню',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $externalItem = MenuItem::query()->create([
            'category_id' => $oldCategory->id,
            'title' => 'Старое название',
            'price' => 100,
            'external_id' => 'EXT-1',
            'is_active' => true,
        ]);
        $supplierItem = MenuItem::query()->create([
            'category_id' => $oldCategory->id,
            'title' => 'Старый код',
            'price' => 120,
            'supplier_code' => 'CODE-1',
            'is_active' => true,
        ]);

        $this->importCsv([
            'Категория;Название;Цена;external_id;supplier_code',
            'Супы;Новое название;220;EXT-1;',
            'Горячее;Новый код;260;;CODE-1',
        ]);

        $this->assertSame(2, MenuItem::query()->count());
        $this->assertDatabaseHas('menu_items', [
            'id' => $externalItem->id,
            'title' => 'Новое название',
            'external_id' => 'EXT-1',
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $supplierItem->id,
            'title' => 'Новый код',
            'supplier_code' => 'CODE-1',
        ]);
    }

    #[Test]
    public function name_and_category_are_used_as_fallback_matching(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $existing = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Оливье',
            'price' => 160,
            'is_active' => true,
        ]);

        $this->importCsv([
            'Категория;Название;Цена',
            'Салаты;Оливье;190',
        ]);

        $this->assertSame(1, MenuItem::query()->count());
        $this->assertDatabaseHas('menu_items', [
            'id' => $existing->id,
            'price' => 190,
        ]);
    }

    #[Test]
    public function dangerous_image_url_scheme_fails_validation_and_is_not_saved(): void
    {
        $import = $this->importCsv([
            'Категория;Название;Цена;image_url',
            'Супы;Борщ;210;javascript:alert(1)',
        ]);

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertDatabaseCount('menu_items', 0);
        $this->assertStringContainsString('Ссылка на изображение', (string) data_get($import->error_report, 'errors.0.message'));
    }

    #[Test]
    public function php_file_is_rejected_before_import_history_or_catalog_changes_are_created(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/menu.php', '<?php echo "not a menu";');

        try {
            app(MenuImportService::class)->importStoredFile(
                storedPath: 'menu-imports/menu.php',
                originalFilename: 'menu.php',
                importedBy: User::factory()->create(),
            );
            $this->fail('PHP files must not be accepted as menu imports.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('CSV и XLSX', $exception->getMessage());
        }

        $this->assertDatabaseCount('menu_imports', 0);
        $this->assertDatabaseCount('menu_items', 0);
    }

    #[Test]
    public function successful_import_makes_active_items_visible_in_catalog_api(): void
    {
        $this->importCsv([
            'Категория;Название;Цена;image_url',
            'Супы;Борщ;210;https://example.com/borscht.jpg',
        ]);

        $this->getJson('/api/menu/items')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Борщ')
            ->assertJsonPath('data.0.price', '210.00')
            ->assertJsonMissingPath('data.0.supplier_name')
            ->assertJsonPath('data.0.image_url', 'https://example.com/borscht.jpg');
    }

    #[Test]
    public function catalog_api_returns_catalog_title_and_does_not_expose_supplier_name(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Пирожное',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Вафли Гранд со сгущёнкой',
            'supplier_name' => 'Вафли "Гранд" с начинкой (сгущ.) (20шт)',
            'price' => 520,
            'is_active' => true,
        ]);

        $this->getJson('/api/menu/items')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Вафли Гранд со сгущёнкой')
            ->assertJsonMissingPath('data.0.supplier_name');
    }

    #[Test]
    public function successful_xlsx_import_creates_menu_items(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('menu-imports/menu.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['category', 'name', 'price'],
            ['Супы', 'Рассольник', 230],
        ]);
        (new Xlsx($spreadsheet))->save($path);

        $import = app(MenuImportService::class)->importStoredFile(
            storedPath: 'menu-imports/menu.xlsx',
            originalFilename: 'menu.xlsx',
            importedBy: User::factory()->create(),
        );

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'title' => 'Рассольник',
            'price' => 230,
        ]);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function importCsv(array $lines)
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/menu.csv', implode("\n", $lines));

        return app(MenuImportService::class)->importStoredFile(
            storedPath: 'menu-imports/menu.csv',
            originalFilename: 'menu.csv',
            importedBy: User::factory()->create(),
        );
    }

    private function createOrderItem(MenuItem $menuItem): OrderItem
    {
        $cycle = OrderCycle::query()->create([
            'title' => 'Тестовая неделя',
            'starts_at' => now()->startOfWeek(),
            'closes_at' => now()->addDay(),
            'status' => OrderCycleStatus::Open,
        ]);
        $order = Order::query()->create([
            'user_id' => User::factory()->create()->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'total_price' => 200,
            'submitted_at' => now(),
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'title_snapshot' => $menuItem->title,
            'price_snapshot' => $menuItem->price,
            'quantity' => 1,
        ]);
    }
}
