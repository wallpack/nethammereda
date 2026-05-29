<?php

namespace App\Support;

class MenuTextNormalizer
{
    /**
     * @var array<string, string>
     */
    private const TRANSLITERATION = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'shch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];

    public function clean(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        $value = str_replace("\0", '', $value);
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = str_replace(["\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], ' ', $value);
        $value = preg_replace('/\p{Cf}+/u', '', $value) ?? $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function normalizeName(string $value): string
    {
        $value = mb_strtolower($this->clean($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = str_replace('й', 'й', $value);
        $value = str_replace(['«', '»', '“', '”', '„', '‟'], '"', $value);
        $value = str_replace(['’', '‘', '′', '`'], "'", $value);
        $value = str_replace(['"', "'"], '', $value);
        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
        $value = preg_replace('/\s*\(\s*/u', ' (', $value) ?? $value;
        $value = preg_replace('/\s*\)\s*/u', ') ', $value) ?? $value;
        $value = str_replace(['–', '—', '−'], '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function normalizeCategory(string $value): string
    {
        $clean = $this->clean($value);
        $normalized = $this->normalizeName($clean);

        return match ($normalized) {
            'вторые блюд' => 'Вторые блюда',
            'вторые блюда' => 'Вторые блюда',
            'выпечка' => 'Выпечка',
            'салаты' => 'Салаты',
            'супы' => 'Супы',
            default => $clean,
        };
    }

    public function normalizeImportedCategoryName(string $value): string
    {
        $category = $this->clean($value);

        // Remove trailing weight suffix like "(170 г.)", "170 г", "170гр", "300 мл".
        $category = preg_replace(
            '/\s*(?:\(\s*\d+(?:[.,]\d+)?\s*(?:г|гр|грамм(?:а|ов)?|kg|кг|ml|мл|л)\.?\s*\)|\d+(?:[.,]\d+)?\s*(?:г|гр|грамм(?:а|ов)?|kg|кг|ml|мл|л)\.?)\s*$/ui',
            '',
            $category,
        ) ?? $category;

        $category = $this->clean($category);

        return $category !== '' ? $category : $this->clean($value);
    }

    public function normalizeImportItemTitleKey(string $value): string
    {
        $normalized = $this->clean($value);
        $normalized = preg_replace('/\p{Cf}+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s*([,.;:])\s*/u', '$1 ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        return mb_strtolower($normalized, 'UTF-8');
    }

    public function menuItemMatchKey(string $categoryName, string $itemTitle): string
    {
        $categoryKey = $this->normalizeName($this->normalizeImportedCategoryName($categoryName));
        $titleKey = $this->normalizeImportItemTitleKey($itemTitle);

        return $categoryKey.'|'.$titleKey;
    }

    public function slug(string $value, int $maxLength = 80): string
    {
        $normalized = $this->normalizeName($value);
        $slug = '';

        foreach (mb_str_split($normalized) as $char) {
            if (isset(self::TRANSLITERATION[$char])) {
                $slug .= self::TRANSLITERATION[$char];

                continue;
            }

            $slug .= $char;
        }

        $slug = preg_replace('/[^a-z0-9]+/u', '-', mb_strtolower($slug, 'UTF-8')) ?? $slug;
        $slug = trim($slug, '-');
        $slug = $slug === '' ? 'menu-item' : $slug;

        return mb_substr($slug, 0, $maxLength);
    }

    public function suggestedFilename(int $id, string $name, string $extension = 'png'): string
    {
        $extension = mb_strtolower(ltrim($extension, '.'));
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'png';

        return "menu-item-{$id}-{$this->slug($name)}.{$extension}";
    }

    public function similarity(string $left, string $right): float
    {
        $left = $this->normalizeName($left);
        $right = $this->normalizeName($right);

        if ($left === $right) {
            return 1.0;
        }

        $leftChars = mb_str_split($left);
        $rightChars = mb_str_split($right);
        $leftCount = count($leftChars);
        $rightCount = count($rightChars);

        if ($leftCount === 0 || $rightCount === 0) {
            return 0.0;
        }

        $previous = range(0, $rightCount);

        for ($i = 1; $i <= $leftCount; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $rightCount; $j++) {
                $cost = $leftChars[$i - 1] === $rightChars[$j - 1] ? 0 : 1;
                $current[$j] = min(
                    $current[$j - 1] + 1,
                    $previous[$j] + 1,
                    $previous[$j - 1] + $cost,
                );
            }

            $previous = $current;
        }

        $distance = $previous[$rightCount];
        $maxLength = max($leftCount, $rightCount);

        return max(0.0, 1.0 - ($distance / $maxLength));
    }
}
