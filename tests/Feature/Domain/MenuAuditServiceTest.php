<?php

namespace Tests\Feature\Domain;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\MenuAudit\ImageAuditService;
use App\Services\MenuAudit\MenuAuditService;
use App\Services\MenuAudit\SupplierMenuParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $auditDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditDirectory = storage_path('app/menu-audit');
        File::deleteDirectory($this->auditDirectory);
    }

    #[Test]
    public function supplier_menu_parser_extracts_dishes_from_saved_html_fixture(): void
    {
        $items = app(SupplierMenuParser::class)->parse($this->supplierHtmlFixture());

        $this->assertCount(2, $items);
        $this->assertSame('Супы', $items[0]['category']);
        $this->assertSame('Суп “Борщ”', $items[0]['name']);
        $this->assertSame('7 суток', $items[0]['shelf_life']);
        $this->assertSame('80 г.', $items[0]['weight']);
        $this->assertSame(67, $items[0]['calories']);
        $this->assertSame(2.4, $items[0]['proteins']);
        $this->assertSame(4.5, $items[0]['fats']);
        $this->assertSame(4.1, $items[0]['carbs']);
        $this->assertSame('Свекла, капуста, картофель', $items[0]['composition']);
        $this->assertSame('https://cdn.example.test/borscht.png', $items[0]['image_url']);
    }

    #[Test]
    public function image_audit_detects_exact_duplicate_images(): void
    {
        $source = storage_path('app/testing-images');
        File::deleteDirectory($source);
        File::ensureDirectoryExists($source);

        file_put_contents($source.'/borscht.png', $this->pngBytes());
        file_put_contents($source.'/borscht-copy.png', $this->pngBytes());

        $summary = app(ImageAuditService::class)->audit($source);

        $this->assertSame(2, $summary['valid_files']);
        $this->assertSame(1, $summary['exact_duplicate_groups']);
        $this->assertFileExists($this->auditDirectory.'/image-duplicates.csv');
        $this->assertStringContainsString('borscht.png', file_get_contents($this->auditDirectory.'/image-duplicates.csv'));
        $this->assertStringContainsString('borscht-copy.png', file_get_contents($this->auditDirectory.'/image-duplicates.csv'));
    }

    #[Test]
    public function menu_audit_creates_missing_in_local_report(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        MenuItem::query()->create([
            'category_id' => $category->id,
            'title' => 'Суп “Борщ”',
            'price' => 210,
            'is_active' => true,
        ]);

        $summary = app(MenuAuditService::class)->auditHtml($this->supplierHtmlFixture());

        $this->assertSame(1, $summary['missing_in_local']);
        $this->assertFileExists($this->auditDirectory.'/missing-in-local.csv');
        $this->assertStringContainsString('Суп “Рассольник”', file_get_contents($this->auditDirectory.'/missing-in-local.csv'));
    }

    #[Test]
    public function menu_audit_detects_exact_duplicate_local_names(): void
    {
        $soups = MenuCategory::query()->create([
            'name' => 'Супы',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $bakery = MenuCategory::query()->create([
            'name' => 'Выпечка',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        foreach ([$soups, $bakery] as $category) {
            MenuItem::query()->create([
                'category_id' => $category->id,
                'title' => 'Сэндвич с копченой курицей',
                'price' => 200,
                'is_active' => true,
            ]);
        }

        $summary = app(MenuAuditService::class)->auditHtml($this->supplierHtmlFixture());

        $this->assertSame(1, $summary['duplicate_local_groups']);
        $this->assertFileExists($this->auditDirectory.'/duplicates-local.csv');
        $this->assertStringContainsString('Сэндвич с копченой курицей', file_get_contents($this->auditDirectory.'/duplicates-local.csv'));
    }

    private function supplierHtmlFixture(): string
    {
        return <<<'HTML'
<!doctype html>
<html>
<body>
    <div class="m-spoilers__spoiler">
        <div class="m-spoilers__header">
            <div class="spoilerLabel"><span class="ms-active-string">Супы</span></div>
        </div>
        <div class="m-spoilers__content">
            <div class="ms-slot__cell">
                <div class="blk"><img src="//cdn.example.test/borscht.png"></div>
                <div class="blk">
                    <div class="b-spoiler">
                        <h4 class="b-spoiler__title"><span class="ms-active-string">Суп “Борщ”</span></h4>
                        <div class="b-spoiler__text"><span class="ms-active-string">Состав: Свекла, капуста, картофель<br>7 суток<br>80 г.<br>67 / 2,4 / 4,5 / 4,1</span></div>
                    </div>
                </div>
            </div>
            <div class="ms-slot__cell">
                <div class="blk"><img src="https://cdn.example.test/rassolnik.png"></div>
                <div class="blk">
                    <div class="b-spoiler">
                        <h4 class="b-spoiler__title"><span class="ms-active-string">Суп “Рассольник”</span></h4>
                        <div class="b-spoiler__text"><span class="ms-active-string">7 суток<br>250 г.<br>100 / 3 / 4 / 5</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/axMnykAAAAASUVORK5CYII=',
            true,
        );
    }
}
