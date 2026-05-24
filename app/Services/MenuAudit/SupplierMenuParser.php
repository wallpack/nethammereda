<?php

namespace App\Services\MenuAudit;

use App\Support\MenuTextNormalizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class SupplierMenuParser
{
    public function __construct(
        private readonly MenuTextNormalizer $normalizer,
    ) {}

    /**
     * @return array<int, array{
     *     category: string,
     *     name: string,
     *     shelf_life: ?string,
     *     weight: ?string,
     *     calories: ?int,
     *     proteins: ?float,
     *     fats: ?float,
     *     carbs: ?float,
     *     composition: ?string,
     *     image_url: ?string
     * }>
     */
    public function parse(string $html, string $baseUrl = 'https://belyeruchki.ru'): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $categoryNodes = $xpath->query($this->classQuery('m-spoilers__spoiler'));
        $items = [];
        $seen = [];

        foreach ($categoryNodes ?: [] as $categoryNode) {
            if (! $categoryNode instanceof DOMElement) {
                continue;
            }

            $category = $this->categoryName($xpath, $categoryNode);

            if ($category === null) {
                continue;
            }

            $dishNodes = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " b-spoiler ")]', $categoryNode);

            foreach ($dishNodes ?: [] as $dishNode) {
                if (! $dishNode instanceof DOMElement) {
                    continue;
                }

                $name = $this->dishName($xpath, $dishNode);

                if ($name === null || $this->looksLikeNavigationLabel($name)) {
                    continue;
                }

                $details = $this->details($xpath, $dishNode);
                $row = array_merge([
                    'category' => $category,
                    'name' => $name,
                    'image_url' => $this->normalizeImageUrl($this->previousImageUrl($xpath, $dishNode), $baseUrl),
                ], $details);

                $key = implode('|', [
                    $this->normalizer->normalizeName($row['category']),
                    $this->normalizer->normalizeName($row['name']),
                    $row['weight'] ?? '',
                    $row['shelf_life'] ?? '',
                ]);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $items[] = $row;
            }
        }

        return $items;
    }

    private function categoryName(DOMXPath $xpath, DOMElement $categoryNode): ?string
    {
        $label = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " spoilerLabel ")]//*[contains(concat(" ", normalize-space(@class), " "), " ms-active-string ")]', $categoryNode)?->item(0);

        if (! $label instanceof DOMNode) {
            return null;
        }

        $category = $this->normalizer->normalizeCategory($label->textContent);

        return $category === '' ? null : $category;
    }

    private function dishName(DOMXPath $xpath, DOMElement $dishNode): ?string
    {
        $title = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " b-spoiler__title ")]//*[contains(concat(" ", normalize-space(@class), " "), " ms-active-string ")]', $dishNode)?->item(0)
            ?? $xpath->query('.//h4//*[contains(concat(" ", normalize-space(@class), " "), " ms-active-string ")]', $dishNode)?->item(0);

        if (! $title instanceof DOMNode) {
            return null;
        }

        $name = $this->normalizer->clean($title->textContent);

        return $name === '' ? null : $name;
    }

    /**
     * @return array{shelf_life: ?string, weight: ?string, calories: ?int, proteins: ?float, fats: ?float, carbs: ?float, composition: ?string}
     */
    private function details(DOMXPath $xpath, DOMElement $dishNode): array
    {
        $content = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " b-spoiler__text ")]//*[contains(concat(" ", normalize-space(@class), " "), " ms-active-string ")]', $dishNode)?->item(0);
        $text = $content instanceof DOMNode ? $this->nodeTextWithBreaks($content) : '';
        $lines = array_values(array_filter(array_map(
            fn (string $line): string => $this->normalizer->clean($line),
            preg_split('/\n+/u', $text) ?: [],
        )));
        $flatText = $this->normalizer->clean(implode(' ', $lines));
        $composition = null;

        foreach ($lines as $line) {
            if (preg_match('/^Состав:\s*(.+)$/ui', $line, $match) === 1) {
                $composition = $this->normalizer->clean((string) $match[1]);
                break;
            }
        }

        $shelfLife = preg_match('/\d+\s*сут(?:ок|ки|ка)?/ui', $flatText, $shelfMatch) === 1
            ? $this->normalizer->clean((string) $shelfMatch[0])
            : null;
        $weight = preg_match('/\d+\s*(?:г|гр|мл)\.?/ui', $flatText, $weightMatch) === 1
            ? $this->normalizer->clean((string) $weightMatch[0])
            : null;
        $nutrition = preg_match('/(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)/u', $flatText, $nutritionMatch) === 1
            ? array_slice($nutritionMatch, 1, 4)
            : [];

        return [
            'shelf_life' => $shelfLife,
            'weight' => $weight,
            'calories' => isset($nutrition[0]) ? (int) round($this->number($nutrition[0])) : null,
            'proteins' => isset($nutrition[1]) ? $this->number($nutrition[1]) : null,
            'fats' => isset($nutrition[2]) ? $this->number($nutrition[2]) : null,
            'carbs' => isset($nutrition[3]) ? $this->number($nutrition[3]) : null,
            'composition' => $composition,
        ];
    }

    private function nodeTextWithBreaks(DOMNode $node): string
    {
        $document = $node->ownerDocument;

        if (! $document instanceof DOMDocument) {
            return $node->textContent;
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function previousImageUrl(DOMXPath $xpath, DOMElement $dishNode): ?string
    {
        $block = $this->ancestorWithClass($dishNode, 'blk');

        for ($sibling = $block?->previousSibling; $sibling instanceof DOMNode; $sibling = $sibling->previousSibling) {
            if (! $sibling instanceof DOMElement) {
                continue;
            }

            $images = $xpath->query('.//img[@src]', $sibling);
            $image = $images?->item(max(0, ($images?->length ?? 1) - 1));

            if ($image instanceof DOMElement) {
                return $image->getAttribute('src');
            }
        }

        $image = $xpath->query('preceding::img[@src][1]', $dishNode)?->item(0);

        return $image instanceof DOMElement ? $image->getAttribute('src') : null;
    }

    private function ancestorWithClass(DOMElement $node, string $class): ?DOMElement
    {
        for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if ($this->hasClass($parent, $class)) {
                return $parent;
            }
        }

        return null;
    }

    private function normalizeImageUrl(?string $url, string $baseUrl): ?string
    {
        $url = $url === null ? null : trim($url);

        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return rtrim($baseUrl, '/').$url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (is_string($scheme) && in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            return $url;
        }

        return null;
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    private function classQuery(string $class): string
    {
        return '//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]';
    }

    private function hasClass(DOMElement $node, string $class): bool
    {
        return str_contains(' '.$node->getAttribute('class').' ', ' '.$class.' ');
    }

    private function looksLikeNavigationLabel(string $name): bool
    {
        return in_array($this->normalizer->normalizeName($name), [
            'вторые блюда',
            'выпечка',
            'салаты',
            'супы',
        ], true);
    }
}
