<?php

namespace App\Services;

class MenuImportRowValidator
{
    private const REQUIRED_FIELDS = ['category', 'name', 'price'];

    /**
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'category' => ['category', 'категория'],
        'name' => ['name', 'title', 'название', 'блюдо'],
        'price' => ['price', 'цена'],
        'weight' => ['weight', 'вес'],
        'calories' => ['calories', 'calorie', 'ккал', 'калории'],
        'proteins' => ['proteins', 'protein', 'белки'],
        'fats' => ['fats', 'fat', 'жиры'],
        'carbs' => ['carbs', 'carbohydrates', 'углеводы'],
        'description' => ['description', 'описание'],
        'image_url' => ['image_url', 'image url', 'ссылка на изображение', 'изображение', 'картинка'],
        'external_id' => ['external_id', 'external id', 'внешний id', 'внешний идентификатор'],
        'supplier_code' => ['supplier_code', 'supplier code', 'код поставщика', 'артикул'],
        'is_active' => ['is_active', 'active', 'активно', 'доступно'],
    ];

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
        $headerMap = $this->mapHeaders($parsed['headers'] ?? []);
        $nonBlankRows = array_values(array_filter(
            $parsed['rows'] ?? [],
            fn (array $row): bool => ! $this->isBlankRow($row['cells'] ?? []),
        ));
        $rowsTotal = count($nonBlankRows);
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (! array_key_exists($field, $headerMap)) {
                $errors[] = [
                    'row' => null,
                    'field' => $field,
                    'message' => 'В файле нет обязательной колонки «'.$this->fieldLabel($field).'».',
                ];
            }
        }

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
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, array_map($this->normalizeHeader(...), $aliases), true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
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

        foreach (['weight', 'description', 'external_id', 'supplier_code'] as $field) {
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
        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
        $normalized = preg_replace('/(?:₽|руб\.?|р\.?)$/ui', '', $normalized) ?? $normalized;
        $normalized = str_replace(',', '.', $normalized);

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
        $value = str_replace("\0", '', $value);
        $value = strip_tags($value);
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_substr($value, 0, 1000);
    }

    private function normalizeHeader(string $value): string
    {
        $value = $this->cleanText($value);
        $value = str_replace('ё', 'е', mb_strtolower($value));
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
