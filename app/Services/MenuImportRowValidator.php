<?php

namespace App\Services;

class MenuImportRowValidator
{
    private const REQUIRED_FIELDS = ['category', 'name', 'price'];

    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'category' => ['category', 'категория', 'раздел', 'группа'],
        'name' => ['name', 'title', 'название', 'блюдо', 'наименование', 'наименование продукции', 'товар'],
        'price' => ['price', 'цена', 'цена руб.', 'цена руб', 'цена ₽', 'стоимость'],
        'weight' => ['weight', 'вес'],
        'calories' => ['calories', 'calorie', 'ккал', 'калории'],
        'proteins' => ['proteins', 'protein', 'белки'],
        'fats' => ['fats', 'fat', 'жиры'],
        'carbs' => ['carbs', 'carbohydrates', 'углеводы'],
        'description' => ['description', 'описание'],
        'image_url' => ['image_url', 'image url', 'ссылка на изображение', 'изображение', 'картинка'],
        'external_id' => ['external_id', 'external id', 'внешний id', 'внешний идентификатор'],
        'supplier_code' => ['supplier_code', 'supplier code', 'код поставщика', 'артикул'],
        'supplier_name' => ['supplier_name', 'название для поставщика', 'наименование для поставщика'],
        'is_active' => ['is_active', 'active', 'активно', 'доступно'],
    ];

    /**
     * Заголовки, которые однозначно указывают на прайс-лист поставщика.
     *
     * @var array<int, string>
     */
    private const SUPPLIER_HEADER_HINTS = [
        'наименование',
        'наименование продукции',
    ];

    private const STRUCTURE_ERROR_MESSAGE = 'Не удалось определить структуру файла. Поддерживаются форматы: «Категория, Название, Цена» или прайс-лист «Наименование продукции, Цена руб., Срок годности».';

    /**
     * @return array{
     *     rows_total: int,
     *     rows_valid: int,
     *     rows_failed: int,
     *     rows: array<int, array{
     *         row_number: int,
     *         category: string,
     *         name: string,
     *         price: float,
     *         fields: array<string, mixed>
     *     }>,
     *     errors: array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     * }
     */
    public function validate(array $parsed): array
    {
        $allRows = $this->prepareRows($parsed);
        $header = $this->detectHeader($allRows);

        if ($header === null) {
            return $this->unsupportedStructureResult($allRows);
        }

        $rowsAfterHeader = array_values(array_filter(
            $allRows,
            fn (array $row): bool => (int) ($row['number'] ?? 0) > $header['header_row'],
        ));

        return match ($header['format']) {
            'canonical' => $this->validateCanonical($header['header_map'], $rowsAfterHeader),
            'supplier' => $this->validateSupplier($header['header_map'], $rowsAfterHeader),
            'missing_category' => $this->missingCategoryResult($rowsAfterHeader),
            default => $this->unsupportedStructureResult($allRows),
        };
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  array<int, array{number: int, cells: array<int, string>}>  $rows
     * @return array{
     *     rows_total: int,
     *     rows_valid: int,
     *     rows_failed: int,
     *     rows: array<int, array{
     *         row_number: int,
     *         category: string,
     *         name: string,
     *         price: float,
     *         fields: array<string, mixed>
     *     }>,
     *     errors: array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     * }
     */
    private function validateCanonical(array $headerMap, array $rows): array
    {
        $nonBlankRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => ! $this->isBlankRow($row['cells'] ?? []),
        ));
        $rowsTotal = count($nonBlankRows);
        $errors = [];

        if ($rowsTotal === 0) {
            $errors[] = [
                'row' => null,
                'field' => null,
                'message' => 'Файл не содержит строк меню.',
            ];
        }

        if ($errors !== []) {
            return [
                'rows_total' => $rowsTotal,
                'rows_valid' => 0,
                'rows_failed' => $rowsTotal,
                'rows' => [],
                'errors' => $errors,
            ];
        }

        $validRows = [];
        $failedRows = [];
        $seenKeys = [];

        foreach ($nonBlankRows as $row) {
            $rowNumber = (int) $row['number'];
            $cells = $row['cells'] ?? [];
            $rowErrors = [];

            $category = $this->requiredString($cells, $headerMap, 'category', $rowNumber, $rowErrors);
            $name = $this->requiredString($cells, $headerMap, 'name', $rowNumber, $rowErrors);
            $price = $this->requiredDecimal($cells, $headerMap, 'price', $rowNumber, $rowErrors);
            $fields = $this->optionalFields($cells, $headerMap, $rowNumber, $rowErrors);

            if ($rowErrors === []) {
                $matchKey = $this->matchKey($category, $name, $fields);

                if (isset($seenKeys[$matchKey])) {
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => null,
                        'message' => 'В файле есть повторяющаяся строка меню.',
                    ];
                }

                $seenKeys[$matchKey] = true;
            }

            if ($rowErrors !== []) {
                $failedRows[$rowNumber] = true;
                array_push($errors, ...$rowErrors);

                continue;
            }

            $validRows[] = [
                'row_number' => $rowNumber,
                'category' => $category,
                'name' => $name,
                'price' => $price,
                'fields' => $fields,
            ];
        }

        return [
            'rows_total' => $rowsTotal,
            'rows_valid' => count($validRows),
            'rows_failed' => count($failedRows),
            'rows' => $validRows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  array<int, array{number: int, cells: array<int, string>}>  $rows
     * @return array{
     *     rows_total: int,
     *     rows_valid: int,
     *     rows_failed: int,
     *     rows: array<int, array{
     *         row_number: int,
     *         category: string,
     *         name: string,
     *         price: float,
     *         fields: array<string, mixed>
     *     }>,
     *     errors: array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     * }
     */
    private function validateSupplier(array $headerMap, array $rows): array
    {
        $validRows = [];
        $errors = [];
        $failedRows = [];
        $seenKeys = [];
        $rowsTotal = 0;
        $currentCategory = '';

        foreach ($rows as $row) {
            $rowNumber = (int) $row['number'];
            $cells = $row['cells'] ?? [];
            $name = $this->cleanText($cells[$headerMap['name']] ?? '');
            $priceRaw = $this->cleanText($cells[$headerMap['price']] ?? '');

            if ($name === '' && $priceRaw === '') {
                continue;
            }

            if ($priceRaw === '') {
                if ($name !== '') {
                    $currentCategory = $name;
                }

                continue;
            }

            $rowsTotal++;
            $rowErrors = [];

            if ($name === '') {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'name',
                    'message' => 'Поле «'.$this->fieldLabel('name').'» обязательно.',
                ];
            }

            if ($currentCategory === '') {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'category',
                    'message' => 'Не удалось определить категорию для строки.',
                ];
            } elseif (mb_strlen($currentCategory) > 255) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'category',
                    'message' => 'Поле «'.$this->fieldLabel('category').'» не должно быть длиннее 255 символов.',
                ];
            }

            $price = $this->parseDecimal($priceRaw);

            if ($priceRaw === '' || $price === null) {
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => 'price',
                    'message' => 'Поле «'.$this->fieldLabel('price').'» должно быть неотрицательным числом.',
                    'value' => $priceRaw,
                ];
                $price = 0.0;
            }

            $fields = $this->optionalFields($cells, $headerMap, $rowNumber, $rowErrors);

            if ($rowErrors === []) {
                $matchKey = $this->matchKey($currentCategory, $name, $fields);

                if (isset($seenKeys[$matchKey])) {
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => null,
                        'message' => 'В файле есть повторяющаяся строка меню.',
                    ];
                }

                $seenKeys[$matchKey] = true;
            }

            if ($rowErrors !== []) {
                $failedRows[$rowNumber] = true;
                array_push($errors, ...$rowErrors);

                continue;
            }

            $validRows[] = [
                'row_number' => $rowNumber,
                'category' => $currentCategory,
                'name' => $name,
                'price' => $price,
                'fields' => $fields,
            ];
        }

        if ($rowsTotal === 0 && $errors === []) {
            $errors[] = [
                'row' => null,
                'field' => null,
                'message' => 'Файл не содержит строк меню.',
            ];
        }

        return [
            'rows_total' => $rowsTotal,
            'rows_valid' => count($validRows),
            'rows_failed' => count($failedRows),
            'rows' => $validRows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array{number: int, cells: array<int, string>}>  $rows
     * @return array{
     *     rows_total: int,
     *     rows_valid: int,
     *     rows_failed: int,
     *     rows: array<int, array{
     *         row_number: int,
     *         category: string,
     *         name: string,
     *         price: float,
     *         fields: array<string, mixed>
     *     }>,
     *     errors: array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     * }
     */
    private function missingCategoryResult(array $rows): array
    {
        $rowsTotal = count(array_filter(
            $rows,
            fn (array $row): bool => ! $this->isBlankRow($row['cells'] ?? []),
        ));

        return [
            'rows_total' => $rowsTotal,
            'rows_valid' => 0,
            'rows_failed' => $rowsTotal,
            'rows' => [],
            'errors' => [
                [
                    'row' => null,
                    'field' => 'category',
                    'message' => 'В файле нет обязательной колонки «'.$this->fieldLabel('category').'».',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{number: int, cells: array<int, string>}>  $allRows
     * @return array{
     *     rows_total: int,
     *     rows_valid: int,
     *     rows_failed: int,
     *     rows: array<int, array{
     *         row_number: int,
     *         category: string,
     *         name: string,
     *         price: float,
     *         fields: array<string, mixed>
     *     }>,
     *     errors: array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>
     * }
     */
    private function unsupportedStructureResult(array $allRows): array
    {
        $rowsTotal = count(array_filter(
            $allRows,
            fn (array $row): bool => ! $this->isBlankRow($row['cells'] ?? []),
        ));

        return [
            'rows_total' => $rowsTotal,
            'rows_valid' => 0,
            'rows_failed' => $rowsTotal,
            'rows' => [],
            'errors' => [
                [
                    'row' => null,
                    'field' => null,
                    'message' => self::STRUCTURE_ERROR_MESSAGE,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<int, array{number: int, cells: array<int, string>}>
     */
    private function prepareRows(array $parsed): array
    {
        $rows = [];
        $headers = $parsed['headers'] ?? [];

        if (is_array($headers) && $headers !== []) {
            $rows[] = [
                'number' => 1,
                'cells' => array_values(array_map(
                    fn (mixed $value): string => (string) $value,
                    $headers,
                )),
            ];
        }

        foreach ($parsed['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'number' => (int) ($row['number'] ?? 0),
                'cells' => array_values(array_map(
                    fn (mixed $value): string => (string) $value,
                    (array) ($row['cells'] ?? []),
                )),
            ];
        }

        usort($rows, fn (array $left, array $right): int => $left['number'] <=> $right['number']);

        return $rows;
    }

    /**
     * @param  array<int, array{number: int, cells: array<int, string>}>  $allRows
     * @return array{
     *     format: 'canonical'|'supplier'|'missing_category',
     *     header_row: int,
     *     header_map: array<string, int>
     * }|null
     */
    private function detectHeader(array $allRows): ?array
    {
        $supplierCandidate = null;

        foreach ($allRows as $row) {
            $cells = $row['cells'] ?? [];

            if ($this->isBlankRow($cells)) {
                continue;
            }

            $headerMap = $this->mapHeaders($cells);
            $hasName = array_key_exists('name', $headerMap);
            $hasPrice = array_key_exists('price', $headerMap);
            $hasCategory = array_key_exists('category', $headerMap);

            if ($hasCategory && $hasName && $hasPrice) {
                return [
                    'format' => 'canonical',
                    'header_row' => (int) $row['number'],
                    'header_map' => $headerMap,
                ];
            }

            if (! $hasName || ! $hasPrice) {
                continue;
            }

            if (
                $this->looksLikeSupplierHeader($cells)
                || $this->hasSupplierCategoryRowsAfter($allRows, (int) $row['number'], $headerMap)
            ) {
                return [
                    'format' => 'supplier',
                    'header_row' => (int) $row['number'],
                    'header_map' => $headerMap,
                ];
            }

            if ($supplierCandidate === null) {
                $supplierCandidate = [
                    'format' => 'missing_category',
                    'header_row' => (int) $row['number'],
                    'header_map' => $headerMap,
                ];
            }
        }

        return $supplierCandidate;
    }

    /**
     * @param  array<int, string>  $headerCells
     */
    private function looksLikeSupplierHeader(array $headerCells): bool
    {
        $normalizedHeaders = array_map(
            fn (string $value): string => $this->normalizeHeader($value),
            $headerCells,
        );

        foreach ($normalizedHeaders as $header) {
            if (in_array($header, self::SUPPLIER_HEADER_HINTS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{number: int, cells: array<int, string>}>  $allRows
     * @param  array<string, int>  $headerMap
     */
    private function hasSupplierCategoryRowsAfter(array $allRows, int $headerRowNumber, array $headerMap): bool
    {
        $nameIndex = $headerMap['name'] ?? null;
        $priceIndex = $headerMap['price'] ?? null;

        if (! is_int($nameIndex) || ! is_int($priceIndex)) {
            return false;
        }

        foreach ($allRows as $row) {
            if ((int) ($row['number'] ?? 0) <= $headerRowNumber) {
                continue;
            }

            $cells = $row['cells'] ?? [];
            $name = $this->cleanText($cells[$nameIndex] ?? '');
            $price = $this->cleanText($cells[$priceIndex] ?? '');

            if ($name === '' && $price === '') {
                continue;
            }

            if ($name !== '' && $price === '') {
                return true;
            }

            if ($name !== '' && $price !== '') {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        $aliases = $this->headerAliasLookup();

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            if ($normalized === '' || ! array_key_exists($normalized, $aliases)) {
                continue;
            }

            $field = $aliases[$normalized];

            if (! array_key_exists($field, $map)) {
                $map[$field] = $index;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function headerAliasLookup(): array
    {
        static $lookup = null;

        if (is_array($lookup)) {
            return $lookup;
        }

        $lookup = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$this->normalizeHeader($alias)] = $field;
            }
        }

        return $lookup;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->cleanText($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $headerMap
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     */
    private function requiredString(array $cells, array $headerMap, string $field, int $rowNumber, array &$errors): string
    {
        $value = $this->cleanText($cells[$headerMap[$field]] ?? '');

        if ($value === '') {
            $errors[] = [
                'row' => $rowNumber,
                'field' => $field,
                'message' => 'Поле «'.$this->fieldLabel($field).'» обязательно.',
            ];
        }

        if (mb_strlen($value) > 255) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => $field,
                'message' => 'Поле «'.$this->fieldLabel($field).'» не должно быть длиннее 255 символов.',
            ];
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $headerMap
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     */
    private function requiredDecimal(array $cells, array $headerMap, string $field, int $rowNumber, array &$errors): float
    {
        $value = $this->cleanText($cells[$headerMap[$field]] ?? '');
        $number = $this->parseDecimal($value);

        if ($value === '' || $number === null) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => $field,
                'message' => 'Поле «'.$this->fieldLabel($field).'» должно быть неотрицательным числом.',
                'value' => $value,
            ];

            return 0.0;
        }

        return $number;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $headerMap
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     * @return array<string, mixed>
     */
    private function optionalFields(array $cells, array $headerMap, int $rowNumber, array &$errors): array
    {
        $fields = [];

        foreach (['weight', 'description', 'external_id', 'supplier_code', 'supplier_name'] as $field) {
            if (array_key_exists($field, $headerMap)) {
                $fields[$field] = $this->nullableText($cells[$headerMap[$field]] ?? '');
            }
        }

        foreach (['proteins', 'fats', 'carbs'] as $field) {
            if (! array_key_exists($field, $headerMap)) {
                continue;
            }

            $value = $this->cleanText($cells[$headerMap[$field]] ?? '');
            $number = $value === '' ? null : $this->parseDecimal($value);

            if ($value !== '' && $number === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => $field,
                    'message' => 'Поле «'.$this->fieldLabel($field).'» должно быть неотрицательным числом.',
                    'value' => $value,
                ];
            }

            $fields[$field] = $number;
        }

        if (array_key_exists('calories', $headerMap)) {
            $value = $this->cleanText($cells[$headerMap['calories']] ?? '');
            $number = $value === '' ? null : $this->parseInteger($value);

            if ($value !== '' && $number === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'calories',
                    'message' => 'Поле «Калории» должно быть целым неотрицательным числом.',
                    'value' => $value,
                ];
            }

            $fields['calories'] = $number;
        }

        if (array_key_exists('image_url', $headerMap)) {
            $fields['image_url'] = $this->imageUrl($cells[$headerMap['image_url']] ?? '', $rowNumber, $errors);
        }

        if (array_key_exists('is_active', $headerMap)) {
            $fields['is_active'] = $this->boolean($cells[$headerMap['is_active']] ?? '', $rowNumber, $errors);
        }

        return $fields;
    }

    /**
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     */
    private function imageUrl(string $value, int $rowNumber, array &$errors): ?string
    {
        $url = $this->nullableText($value);

        if ($url === null) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $isRootRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isHttpUrl = is_string($scheme)
            && in_array(mb_strtolower($scheme), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;

        if (! $isRootRelative && ! $isHttpUrl) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'image_url',
                'message' => 'Поле «Ссылка на изображение» должно быть HTTP(S)-ссылкой или путем внутри сайта.',
                'value' => $url,
            ];
        }

        return $url;
    }

    /**
     * @param  array<int, array{row: ?int, field: ?string, message: string, value?: mixed}>  $errors
     */
    private function boolean(string $value, int $rowNumber, array &$errors): ?bool
    {
        $normalized = $this->normalizeHeader($value);

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'да', 'активно', 'доступно'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'нет', 'неактивно', 'недоступно'], true)) {
            return false;
        }

        $errors[] = [
            'row' => $rowNumber,
            'field' => 'is_active',
            'message' => 'Поле «Активно» должно быть да/нет или true/false.',
            'value' => $value,
        ];

        return null;
    }

    private function parseDecimal(string $value): ?float
    {
        $normalized = str_replace(["\u{00A0}", ' '], '', $value);
        $normalized = preg_replace('/(?:\x{20BD}|руб\.?|р\.?)$/ui', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\d,.\-]/u', '', $normalized) ?? $normalized;

        if ($normalized === '' || str_starts_with($normalized, '-')) {
            return null;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma !== false && $lastDot !== false && $lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $normalized) !== 1) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function parseInteger(string $value): ?int
    {
        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;

        if (preg_match('/^\d+$/', $normalized) !== 1) {
            return null;
        }

        return (int) $normalized;
    }

    private function nullableText(string $value): ?string
    {
        $value = $this->cleanText($value);

        return $value === '' ? null : $value;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\0", '', $value);
        $value = strip_tags($value);
        $value = str_replace(
            ["\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"],
            ' ',
            $value,
        );
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_substr($value, 0, 1000);
    }

    private function normalizeHeader(string $value): string
    {
        $value = $this->cleanText($value);
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = str_replace(['_', '-', '.'], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function matchKey(string $category, string $name, array $fields): string
    {
        if (filled($fields['external_id'] ?? null)) {
            return 'external_id:'.$this->normalizeHeader((string) $fields['external_id']);
        }

        if (filled($fields['supplier_code'] ?? null)) {
            return 'supplier_code:'.$this->normalizeHeader((string) $fields['supplier_code']);
        }

        return 'name_category:'.$this->normalizeHeader($category).'|'.$this->normalizeHeader($name);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'category' => 'Категория',
            'name' => 'Название',
            'price' => 'Цена',
            'weight' => 'Вес',
            'calories' => 'Калории',
            'proteins' => 'Белки',
            'fats' => 'Жиры',
            'carbs' => 'Углеводы',
            'description' => 'Описание',
            'image_url' => 'Ссылка на изображение',
            'external_id' => 'Внешний ID',
            'supplier_code' => 'Код поставщика',
            'is_active' => 'Активно',
            default => $field,
        };
    }
}
