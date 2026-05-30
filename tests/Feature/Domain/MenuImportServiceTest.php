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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
            'title' => 'Комбо: котлета по-киевски с пюре и фасолью',
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
    public function failed_import_does_not_deactivate_existing_active_items(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ',
            'supplier_name' => 'Борщ',
            'price' => 200,
            'is_active' => true,
        ]);

        $import = $this->importCsv([
            'Категория;Название;Цена',
            'Супы;Борщ;дорого',
        ]);

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertTrue($item->fresh()->is_active);
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
    public function dishes_missing_from_new_file_are_deactivated_but_not_deleted(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $oldItem = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Старое блюдо',
            'supplier_name' => 'Старое блюдо',
            'price' => 200,
            'image_path' => 'menu-items/manual/old-item.png',
            'image_url' => 'https://example.com/old-item.png',
            'is_active' => true,
        ]);
        $orderItem = $this->createOrderItem($oldItem);

        $this->importCsv([
            'Категория;Название;Цена',
            'Салаты;Новый салат;180',
        ]);

        $oldItem->refresh();
        $orderItem->refresh();

        $this->assertDatabaseCount('menu_items', 2);
        $this->assertSame('Старое блюдо', $oldItem->title);
        $this->assertFalse($oldItem->is_active);
        $this->assertSame('menu-items/manual/old-item.png', $oldItem->image_path);
        $this->assertSame('https://example.com/old-item.png', $oldItem->image_url);
        $this->assertSame($oldItem->id, $orderItem->menu_item_id);
    }

    #[Test]
    public function active_item_count_matches_successful_import_rows_count(): void
    {
        $staleCategory = MenuCategory::query()->create([
            'name' => 'Старое меню',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $staleCategory->id,
            'title' => 'Старое блюдо',
            'supplier_name' => 'Старое блюдо',
            'price' => 90,
            'is_active' => true,
        ]);

        $import = $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Супы;Борщ;210',
            'Салаты;Винегрет;170',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertSame(2, $import->rows_valid);
        $this->assertSame(2, MenuItem::query()->where('is_active', true)->count());
        $this->assertSame(1, MenuItem::query()->where('is_active', false)->count());
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
            ->assertJsonPath('data.0.display_weight', '20 шт')
            ->assertJsonMissingPath('data.0.supplier_name');
    }

    #[Test]
    public function catalog_api_restores_display_weight_from_imported_supplier_name_without_exposing_it(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Вторые блюда',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Капуста тушеная с сосиской',
            'supplier_name' => 'Капуста тушеная с сосиской 240гр новинка',
            'price' => 90,
            'is_active' => true,
        ]);

        $this->getJson('/api/menu/items')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Капуста тушеная с сосиской')
            ->assertJsonPath('data.0.weight', null)
            ->assertJsonPath('data.0.display_weight', '240 г')
            ->assertJsonMissingPath('data.0.supplier_name');
    }

    #[Test]
    #[RunInSeparateProcess]
    public function successful_xlsx_import_creates_menu_items(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('menu-imports/menu.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $spreadsheet = new Spreadsheet;

        try {
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
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    #[Test]
    public function supplier_price_list_csv_with_service_rows_and_category_rows_is_imported(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Прайс лист поставщика',
            'Менеджер по продажам',
            '',
            'Наименование продукции;Цена руб.;Срок годности',
            'Вторые блюда;;',
            'Запеканка Чиз;130;5 суток',
            'Баветте с курицей;105;5 суток',
            'Супы;;',
            'Суп Солянка;120;3 суток',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertSame(3, $import->rows_total);
        $this->assertSame(3, $import->rows_valid);
        $this->assertSame(0, $import->rows_failed);
        $this->assertDatabaseHas('menu_categories', ['name' => 'Вторые блюда']);
        $this->assertDatabaseHas('menu_categories', ['name' => 'Супы']);
        $this->assertDatabaseHas('menu_items', ['supplier_name' => 'Запеканка Чиз', 'price' => 130]);
        $this->assertDatabaseHas('menu_items', ['supplier_name' => 'Баветте с курицей', 'price' => 105]);
        $this->assertDatabaseHas('menu_items', ['supplier_name' => 'Суп Солянка', 'price' => 120]);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function supplier_price_list_xlsx_is_imported(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('menu-imports/supplier.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $spreadsheet = new Spreadsheet;

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['Прайс лист поставщика'],
                [''],
                ['Наименование продукции', 'Цена руб.', 'Срок годности'],
                ['Выпечка', '', ''],
                ['Булочка с маком', 90, '3 суток'],
                ['Супы', '', ''],
                ['Суп пюре', 140, '2 суток'],
            ]);
            (new Xlsx($spreadsheet))->save($path);

            $import = app(MenuImportService::class)->importStoredFile(
                storedPath: 'menu-imports/supplier.xlsx',
                originalFilename: 'supplier.xlsx',
                importedBy: User::factory()->create(),
            );

            $this->assertSame(MenuImportStatus::Imported, $import->status);
            $this->assertDatabaseHas('menu_items', [
                'supplier_name' => 'Булочка с маком',
                'price' => 90,
            ]);
            $this->assertDatabaseHas('menu_items', [
                'supplier_name' => 'Суп пюре',
                'price' => 140,
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    #[Test]
    public function utf8_bom_csv_with_cyrillic_is_imported_correctly(): void
    {
        $utf8WithBom = "\xEF\xBB\xBF".implode("\n", [
            'Категория;Название;Цена',
            'Супы;Борщ;210',
        ]);

        $import = $this->importRawCsv($utf8WithBom);

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Борщ',
            'price' => 210,
        ]);
    }

    #[Test]
    public function windows_1251_csv_with_cyrillic_is_imported_correctly(): void
    {
        $utf8Content = implode("\n", [
            'Категория;Название;Цена',
            'Супы;Рассольник;230',
        ]);
        $cp1251Content = mb_convert_encoding($utf8Content, 'Windows-1251', 'UTF-8');

        $import = $this->importRawCsv($cp1251Content);

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Рассольник',
            'price' => 230,
        ]);
    }

    #[Test]
    public function comma_delimiter_csv_is_imported(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'category,name,price',
            'soups,Cream soup,180',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Cream soup',
            'price' => 180,
        ]);
    }

    #[Test]
    public function semicolon_delimiter_csv_is_imported(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'category;name;price',
            'soups;Corn soup;199',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Corn soup',
            'price' => 199,
        ]);
    }

    #[Test]
    public function supplier_item_without_category_row_returns_friendly_error(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Наименование продукции;Цена руб.;Срок годности',
            'Запеканка Чиз;130;5 суток',
        ]));

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertSame(1, $import->rows_total);
        $this->assertSame(1, $import->rows_failed);
        $this->assertStringContainsString(
            'Не удалось определить категорию для строки',
            (string) data_get($import->error_report, 'errors.0.message'),
        );
    }

    #[Test]
    public function unsupported_import_structure_returns_friendly_error(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ',
            'supplier_name' => 'Борщ',
            'price' => 190,
            'is_active' => true,
        ]);

        $import = $this->importRawCsv(implode("\n", [
            'foo;bar;baz',
            'one;two;three',
        ]));

        $this->assertSame(MenuImportStatus::Failed, $import->status);
        $this->assertStringContainsString(
            'Не удалось определить структуру файла',
            (string) data_get($import->error_report, 'errors.0.message'),
        );
        $this->assertTrue($item->fresh()->is_active);
    }

    #[Test]
    public function supplier_category_row_with_weight_suffix_is_normalized_to_base_category(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Наименование продукции;Цена руб.;Срок годности',
            'Салаты (170 г.);;',
            'Салат Овощной;130;2 суток',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_categories', ['name' => 'Салаты']);
        $this->assertDatabaseMissing('menu_categories', ['name' => 'Салаты (170 г.)']);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Салат Овощной',
            'price' => 130,
        ]);
    }

    #[Test]
    public function supplier_import_reuses_existing_normalized_category_and_does_not_create_duplicate(): void
    {
        $existing = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $import = $this->importRawCsv(implode("\n", [
            'Наименование продукции;Цена руб.;Срок годности',
            'Салаты 170гр;;',
            'Салат Цезарь;210;2 суток',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertSame(1, MenuCategory::query()->where('name', 'Салаты')->count());
        $this->assertDatabaseMissing('menu_categories', ['name' => 'Салаты 170гр']);
        $this->assertDatabaseHas('menu_items', [
            'category_id' => $existing->id,
            'supplier_name' => 'Салат Цезарь',
            'price' => 210,
        ]);
    }

    #[Test]
    public function canonical_category_with_weight_suffix_is_normalized_to_base_category(): void
    {
        MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $import = $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Салаты (170 г.);Салат Крабовый;190',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_categories', ['name' => 'Салаты']);
        $this->assertDatabaseMissing('menu_categories', ['name' => 'Салаты (170 г.)']);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Салат Крабовый',
            'price' => 190,
        ]);
    }

    #[Test]
    public function item_title_with_weight_suffix_is_preserved(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Салаты;Салат овощной 170 г;150',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Салат овощной 170 г',
            'price' => 150,
        ]);
    }

    #[Test]
    public function supplier_import_extracts_weight_from_item_title_when_weight_column_is_missing(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Наименование продукции;Цена руб.;Срок годности',
            'Вторые блюда;;',
            'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г);125;2 суток',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)',
            'weight' => '260 г',
            'price' => 125,
        ]);
    }

    #[Test]
    public function canonical_import_extracts_weight_from_item_title_when_weight_column_is_missing(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Супы;Суп Солянка (300г.);210',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertDatabaseHas('menu_items', [
            'supplier_name' => 'Суп Солянка (300г.)',
            'weight' => '300 г',
            'price' => 210,
        ]);
    }

    #[Test]
    public function existing_item_weight_is_not_overwritten_by_empty_import_weight(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $existing = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Борщ',
            'supplier_name' => 'Борщ',
            'price' => 150,
            'weight' => '350 г',
            'supplier_code' => 'SUP-001',
            'is_active' => true,
        ]);

        $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена;Вес;supplier_code',
            'Супы;Борщ;210;;SUP-001',
        ]));

        $existing->refresh();

        $this->assertSame('210.00', (string) $existing->price);
        $this->assertSame('350 г', $existing->weight);
    }

    #[Test]
    public function repeated_canonical_csv_import_is_idempotent(): void
    {
        $content = implode("\n", [
            'Категория;Название;Цена',
            'Салаты;Винегрет;190',
            'Супы;Борщ;210',
        ]);

        $first = $this->importRawCsv($content);
        $second = $this->importRawCsv($content);

        $this->assertSame(MenuImportStatus::Imported, $first->status);
        $this->assertSame(MenuImportStatus::Imported, $second->status);
        $this->assertSame(2, MenuItem::query()->count());
        $this->assertSame(1, MenuItem::query()->where('title', 'Винегрет')->count());
        $this->assertSame(1, MenuItem::query()->where('title', 'Борщ')->count());
    }

    #[Test]
    public function repeated_supplier_csv_import_is_idempotent(): void
    {
        $content = implode("\n", [
            'Наименование продукции;Цена руб.;Срок годности',
            'Салаты (170 г.);;',
            'Винегрет;190;2 суток',
            'Витаминный с болгарским перцем;200;2 суток',
        ]);

        $first = $this->importRawCsv($content);
        $second = $this->importRawCsv($content);

        $this->assertSame(MenuImportStatus::Imported, $first->status);
        $this->assertSame(MenuImportStatus::Imported, $second->status);
        $this->assertSame(2, MenuItem::query()->count());
        $this->assertSame(1, MenuItem::query()->where('title', 'Винегрет')->count());
        $this->assertSame(1, MenuItem::query()->where('title', 'Витаминный с болгарским перцем')->count());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function repeated_supplier_xlsx_import_is_idempotent(): void
    {
        $staleCategory = MenuCategory::query()->create([
            'name' => 'Старое меню',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $staleCategory->id,
            'title' => 'Старое блюдо',
            'supplier_name' => 'Старое блюдо',
            'price' => 99,
            'is_active' => true,
        ]);

        Storage::fake('local');
        $path = Storage::disk('local')->path('menu-imports/supplier-repeat.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $spreadsheet = new Spreadsheet;

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['Прайс лист поставщика'],
                [''],
                ['Наименование продукции', 'Цена руб.', 'Срок годности'],
                ['Салаты 170гр', '', ''],
                ['Винегрет', 190, '2 суток'],
                ['Витаминный с болгарским перцем', 200, '2 суток'],
            ]);
            (new Xlsx($spreadsheet))->save($path);

            $first = app(MenuImportService::class)->importStoredFile(
                storedPath: 'menu-imports/supplier-repeat.xlsx',
                originalFilename: 'supplier-repeat.xlsx',
                importedBy: User::factory()->create(),
            );
            $second = app(MenuImportService::class)->importStoredFile(
                storedPath: 'menu-imports/supplier-repeat.xlsx',
                originalFilename: 'supplier-repeat.xlsx',
                importedBy: User::factory()->create(),
            );

            $this->assertSame(MenuImportStatus::Imported, $first->status);
            $this->assertSame(MenuImportStatus::Imported, $second->status);
            $this->assertSame(3, MenuItem::query()->count());
            $this->assertSame(2, MenuItem::query()->where('is_active', true)->count());
            $this->assertSame(1, MenuItem::query()->where('is_active', false)->count());
            $this->assertSame(1, MenuItem::query()->where('title', 'Винегрет')->where('is_active', true)->count());
            $this->assertSame(1, MenuItem::query()->where('title', 'Витаминный с болгарским перцем')->where('is_active', true)->count());
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    #[Test]
    public function supplier_rows_for_combo_and_regular_kiev_cutlet_keep_distinct_catalog_titles(): void
    {
        $import = $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Вторые блюда;Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г);125',
            'Вторые блюда;Котлета (по-Киевски) с картофельным пюре (260г);110',
        ]));

        $this->assertSame(MenuImportStatus::Imported, $import->status);
        $this->assertSame(2, MenuItem::query()->where('is_active', true)->count());
        $this->assertSame(
            1,
            MenuItem::query()->where('supplier_name', 'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)')->count(),
        );
        $this->assertSame(
            1,
            MenuItem::query()->where('supplier_name', 'Котлета (по-Киевски) с картофельным пюре (260г)')->count(),
        );
        $comboItem = MenuItem::query()
            ->where('supplier_name', 'Комбо.Котлета по-Киевски с картофельным пюре и фасолью (260г)')
            ->firstOrFail();
        $regularItem = MenuItem::query()
            ->where('supplier_name', 'Котлета (по-Киевски) с картофельным пюре (260г)')
            ->firstOrFail();

        $this->assertNotSame($regularItem->title, $comboItem->title);
        $this->assertSame('Котлета по-киевски с пюре', $regularItem->title);
        $this->assertSame('Комбо: котлета по-киевски с пюре и фасолью', $comboItem->title);
    }

    #[Test]
    public function existing_item_price_is_updated_without_creating_duplicate(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $existing = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 150,
            'is_active' => true,
        ]);

        $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена',
            'Салаты;Винегрет;205',
        ]));

        $this->assertSame(1, MenuItem::query()->count());
        $this->assertDatabaseHas('menu_items', [
            'id' => $existing->id,
            'title' => 'Винегрет',
            'price' => 205,
        ]);
    }

    #[Test]
    public function existing_item_image_is_preserved_when_import_provides_empty_image_url(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Салаты',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $existing = MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Винегрет',
            'supplier_name' => 'Винегрет',
            'price' => 150,
            'image_path' => 'menu-items/manual/existing.png',
            'image_url' => 'https://example.com/existing.png',
            'is_active' => true,
        ]);

        $this->importRawCsv(implode("\n", [
            'Категория;Название;Цена;image_url',
            'Салаты;Винегрет;210;',
        ]));

        $existing->refresh();

        $this->assertSame('210.00', (string) $existing->price);
        $this->assertSame('menu-items/manual/existing.png', $existing->image_path);
        $this->assertSame('https://example.com/existing.png', $existing->image_url);
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

    private function importRawCsv(string $content)
    {
        Storage::fake('local');
        Storage::disk('local')->put('menu-imports/menu.csv', $content);

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
