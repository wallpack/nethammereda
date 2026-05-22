<?php

namespace Database\Seeders;

use App\Enums\OrderCycleStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use JsonException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{name: string, sort_order: int, start: int, end: int, price_min: int, price_max: int}>
     */
    private const CATEGORY_RANGES = [
        ['name' => 'Вторые блюда', 'sort_order' => 10, 'start' => 0, 'end' => 22, 'price_min' => 240, 'price_max' => 380],
        ['name' => 'Выпечка', 'sort_order' => 20, 'start' => 23, 'end' => 52, 'price_min' => 120, 'price_max' => 280],
        ['name' => 'Салаты', 'sort_order' => 30, 'start' => 53, 'end' => 67, 'price_min' => 140, 'price_max' => 290],
        ['name' => 'Супы', 'sort_order' => 40, 'start' => 68, 'end' => 71, 'price_min' => 170, 'price_max' => 260],
    ];

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@lunch.local'],
            [
                'name' => 'Администратор',
                'password' => 'password',
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => 'user@lunch.local'],
            [
                'name' => 'Тестовый пользователь',
                'password' => 'password',
                'role' => UserRole::User,
                'is_active' => true,
            ],
        );

        $this->wipeDemoData();
        $categories = $this->seedMenu();

        $weekStart = CarbonImmutable::now()->startOfWeek();
        $cycle = OrderCycle::query()->create([
            'title' => 'Неделя '.$weekStart->format('d.m.Y'),
            'starts_at' => $weekStart->setTime(0, 0),
            'closes_at' => $weekStart->addDays(4)->setTime(12, 0),
            'status' => OrderCycleStatus::Open,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_cycle_id' => $cycle->id,
            'status' => OrderStatus::Submitted,
            'submitted_at' => now()->subDay(),
            'total_price' => 0,
        ]);

        $sampleItems = MenuItem::query()
            ->whereIn('category_id', $categories->pluck('id')->all())
            ->orderBy('category_id')
            ->orderBy('id')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($items) => $items->first())
            ->values();

        foreach ($sampleItems as $sampleItem) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $sampleItem->id,
                'title_snapshot' => $sampleItem->title,
                'price_snapshot' => $sampleItem->price,
                'quantity' => 1,
                'status' => $sampleItem->category?->name === 'Супы'
                    ? OrderItemStatus::Arrived
                    : OrderItemStatus::Ordered,
            ]);
        }

        $order->recalculateTotal();

        $admin->is_active = true;
        $admin->save();
    }

    private function wipeDemoData(): void
    {
        FridgeItem::query()->delete();
        OrderItem::query()->delete();
        Order::query()->delete();
        OrderCycle::query()->delete();
        MenuItem::query()->delete();
        MenuCategory::query()->delete();
    }

    /**
     * @throws JsonException
     */
    private function seedMenu()
    {
        $rawItems = json_decode(
            file_get_contents(database_path('seeders/data/belyeruchki-dishes.json')) ?: '[]',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $categories = collect();

        foreach (self::CATEGORY_RANGES as $config) {
            $category = MenuCategory::query()->create([
                'name' => $config['name'],
                'sort_order' => $config['sort_order'],
                'is_active' => true,
            ]);

            $categories->push($category);

            for ($index = $config['start']; $index <= $config['end']; $index++) {
                $row = $rawItems[$index] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $title = $this->cleanText((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }

                $parsed = $this->parseRawText((string) ($row['raw_text'] ?? ''));
                $composition = $this->cleanText((string) ($row['composition'] ?? '')) ?: $parsed['composition'];
                $macros = $this->extractMacros($parsed['nutrition']);

                MenuItem::query()->create([
                    'category_id' => $category->id,
                    'title' => $title,
                    'description' => $parsed['shelf_life']
                        ? "Срок годности: {$parsed['shelf_life']}"
                        : 'Блюдо из меню «Белые ручки».',
                    'composition' => $composition ?: ($parsed['nutrition'] ? "КБЖУ: {$parsed['nutrition']}" : null),
                    'weight' => $parsed['weight'],
                    'calories' => $macros['calories'],
                    'proteins' => $macros['proteins'],
                    'fats' => $macros['fats'],
                    'carbs' => $macros['carbs'],
                    'price' => $this->generatePrice($title, $config['price_min'], $config['price_max']),
                    'image_url' => $this->cleanText((string) ($row['image_url'] ?? '')) ?: null,
                    'is_active' => true,
                ]);
            }
        }

        return $categories;
    }

    private function cleanText(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        return $normalized === null ? trim($text) : trim($normalized);
    }

    /**
     * @return array{shelf_life: ?string, weight: ?string, nutrition: ?string, composition: ?string}
     */
    private function parseRawText(string $rawText): array
    {
        $text = $this->cleanText($rawText);

        if ($text === '') {
            return [
                'shelf_life' => null,
                'weight' => null,
                'nutrition' => null,
                'composition' => null,
            ];
        }

        $shelfLife = null;
        $weight = null;
        $nutrition = null;
        $composition = null;

        if (preg_match('/Состав:\s*(.+?)(?=\d+\s*суток|$)/ui', $text, $compositionMatch) === 1) {
            $composition = $this->cleanText((string) ($compositionMatch[1] ?? ''));
        }

        if (preg_match('/(\d+\s*суток)/ui', $text, $shelfMatch) === 1) {
            $shelfLife = $this->cleanText((string) ($shelfMatch[1] ?? ''));
        }

        if (preg_match('/(\d+\s*(?:г|гр|мл)\.?)/ui', $text, $weightMatch) === 1) {
            $weight = $this->cleanText((string) ($weightMatch[1] ?? ''));
        }

        if (preg_match('/(\d+(?:[.,][0-9]+)?\s*\/\s*\d+(?:[.,][0-9]+)?\s*\/\s*\d+(?:[.,][0-9]+)?\s*\/\s*\d+(?:[.,][0-9]+)?)/u', $text, $nutritionMatch) === 1) {
            $nutrition = $this->cleanText((string) ($nutritionMatch[1] ?? ''));
        }

        return [
            'shelf_life' => $shelfLife,
            'weight' => $weight,
            'nutrition' => $nutrition,
            'composition' => $composition,
        ];
    }

    /**
     * @return array{calories: ?int, proteins: ?float, fats: ?float, carbs: ?float}
     */
    private function extractMacros(?string $nutrition): array
    {
        if ($nutrition === null) {
            return [
                'calories' => null,
                'proteins' => null,
                'fats' => null,
                'carbs' => null,
            ];
        }

        if (preg_match_all('/([0-9]+(?:[.,][0-9]+)?)/u', $nutrition, $matches) < 1) {
            return [
                'calories' => null,
                'proteins' => null,
                'fats' => null,
                'carbs' => null,
            ];
        }

        $values = array_map(
            static fn (string $value): float => (float) str_replace(',', '.', $value),
            $matches[1] ?? [],
        );

        return [
            'calories' => isset($values[0]) ? (int) round($values[0]) : null,
            'proteins' => $values[1] ?? null,
            'fats' => $values[2] ?? null,
            'carbs' => $values[3] ?? null,
        ];
    }

    private function generatePrice(string $title, int $min, int $max): float
    {
        $step = 10;
        $bucketMin = intdiv($min, $step);
        $bucketMax = intdiv($max, $step);
        $bucketRange = max(1, ($bucketMax - $bucketMin) + 1);

        $bucket = $bucketMin + (abs(crc32($title)) % $bucketRange);

        return (float) ($bucket * $step);
    }
}

