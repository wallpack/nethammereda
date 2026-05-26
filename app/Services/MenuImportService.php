<?php

namespace App\Services;

use App\Enums\MenuImportFormat;
use App\Enums\MenuImportStatus;
use App\Models\MenuCategory;
use App\Models\MenuImport;
use App\Models\MenuItem;
use App\Support\MenuCatalogTitleFormatter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MenuImportService
{
    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly MenuImportParser $parser,
        private readonly MenuImportRowValidator $validator,
        private readonly MenuCatalogTitleFormatter $titleFormatter,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function importStoredFile(
        string $storedPath,
        string $originalFilename,
        ?User $importedBy = null,
        array $options = [],
    ): MenuImport {
        $format = MenuImportFormat::fromFilename($originalFilename);
        $this->ensureStoredFileIsAllowed($storedPath, $format);

        $import = MenuImport::query()->create([
            'original_filename' => basename($originalFilename),
            'stored_path' => $storedPath,
            'status' => MenuImportStatus::Uploaded,
            'format' => $format,
            'imported_by' => $importedBy?->id,
            'options' => $options,
        ]);

        try {
            $parsed = $this->parser->parse($storedPath, $format);
            $validation = $this->validator->validate($parsed);
        } catch (Throwable $exception) {
            return $this->failImport($import, 'Файл меню не удалось прочитать.', [
                [
                    'row' => null,
                    'field' => null,
                    'message' => $this->safeErrorMessage($exception),
                ],
            ]);
        }

        $import->update([
            'status' => MenuImportStatus::Validated,
            'rows_total' => $validation['rows_total'],
            'rows_valid' => $validation['rows_valid'],
            'rows_failed' => $validation['rows_failed'],
        ]);

        if ($validation['errors'] !== []) {
            return $this->failImport(
                $import,
                'Импорт не применен: исправьте ошибки в файле и загрузите его заново.',
                $validation['errors'],
                [
                    'rows_total' => $validation['rows_total'],
                    'rows_valid' => $validation['rows_valid'],
                    'rows_failed' => $validation['rows_failed'],
                ],
            );
        }

        try {
            DB::transaction(function () use ($validation): void {
                foreach ($validation['rows'] as $row) {
                    $this->applyRow($row);
                }
            });
        } catch (Throwable $exception) {
            return $this->failImport($import, 'Импорт не применен: не удалось обновить каталог.', [
                [
                    'row' => null,
                    'field' => null,
                    'message' => $this->safeErrorMessage($exception),
                ],
            ], [
                'rows_total' => $validation['rows_total'],
                'rows_valid' => $validation['rows_valid'],
                'rows_failed' => $validation['rows_total'],
            ]);
        }

        $import->update([
            'status' => MenuImportStatus::Imported,
            'imported_at' => now(),
            'error_report' => null,
        ]);

        return $import->fresh(['importedBy']);
    }

    private function ensureStoredFileIsAllowed(string $storedPath, MenuImportFormat $format): void
    {
        if (! Storage::disk('local')->exists($storedPath)) {
            throw new RuntimeException('Файл импорта не найден.');
        }

        $extension = mb_strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));

        if ($extension !== $format->value) {
            throw new InvalidArgumentException('Расширение сохраненного файла не совпадает с форматом импорта.');
        }

        if ((int) Storage::disk('local')->size($storedPath) > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('Файл меню не должен быть больше 5 МБ.');
        }
    }

    /**
     * @param  array{
     *     row_number: int,
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }  $row
     */
    private function applyRow(array $row): void
    {
        $category = $this->findOrCreateCategory($row['category']);
        $attributes = $this->attributesForRow($row, $category);
        $menuItem = $this->findMenuItem($row, $category, $attributes);

        if ($menuItem instanceof MenuItem) {
            $menuItem->fill($attributes);
            $menuItem->save();

            return;
        }

        MenuItem::query()->create($attributes);
    }

    private function findOrCreateCategory(string $name): MenuCategory
    {
        $category = MenuCategory::query()->where('name', $name)->first();

        if ($category instanceof MenuCategory) {
            if (! $category->is_active) {
                $category->forceFill(['is_active' => true])->save();
            }

            return $category;
        }

        $sortOrder = ((int) MenuCategory::query()->max('sort_order')) + 10;

        return MenuCategory::query()->create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }  $row
     * @param  array<string, mixed>  $attributes
     */
    private function findMenuItem(array $row, MenuCategory $category, array $attributes): ?MenuItem
    {
        $fields = $row['fields'];

        if (filled($fields['external_id'] ?? null)) {
            return MenuItem::query()
                ->where('external_id', (string) $fields['external_id'])
                ->first();
        }

        if (filled($fields['supplier_code'] ?? null)) {
            return MenuItem::query()
                ->where('supplier_code', (string) $fields['supplier_code'])
                ->first();
        }

        if (filled($attributes['supplier_name'] ?? null)) {
            $bySupplierName = MenuItem::query()
                ->where('category_id', $category->id)
                ->where('supplier_name', (string) $attributes['supplier_name'])
                ->first();

            if ($bySupplierName instanceof MenuItem) {
                return $bySupplierName;
            }
        }

        return MenuItem::query()
            ->where('category_id', $category->id)
            ->where('title', (string) $attributes['title'])
            ->first();
    }

    /**
     * @param  array{
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }  $row
     * @return array<string, mixed>
     */
    private function attributesForRow(array $row, MenuCategory $category): array
    {
        $fields = $row['fields'];
        $supplierName = $this->titleFormatter->supplierName((string) ($fields['supplier_name'] ?? $row['name']));
        $catalogTitle = $this->titleFormatter->catalogTitle($supplierName);
        $catalogTitle = $catalogTitle !== '' ? $catalogTitle : (string) $row['name'];

        $attributes = [
            'category_id' => $category->id,
            'title' => $catalogTitle,
            'supplier_name' => $supplierName,
            'price' => $row['price'],
            'is_active' => array_key_exists('is_active', $fields)
                ? (bool) ($fields['is_active'] ?? false)
                : true,
        ];

        foreach ([
            'weight',
            'calories',
            'proteins',
            'fats',
            'carbs',
            'description',
            'image_url',
            'external_id',
            'supplier_code',
        ] as $field) {
            if (array_key_exists($field, $fields)) {
                $attributes[$field] = $fields[$field];
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     * @param  array{rows_total?: int, rows_valid?: int, rows_failed?: int}  $counts
     */
    private function failImport(MenuImport $import, string $summary, array $errors, array $counts = []): MenuImport
    {
        $import->update(array_merge([
            'status' => MenuImportStatus::Failed,
            'error_report' => [
                'summary' => $summary,
                'errors' => $errors,
            ],
        ], $counts));

        return $import->fresh(['importedBy']);
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'Файл имеет неподдерживаемую структуру или поврежден.';
    }
}
