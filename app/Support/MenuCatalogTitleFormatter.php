<?php

namespace App\Support;

class MenuCatalogTitleFormatter
{
    public function __construct(
        private readonly MenuTextNormalizer $normalizer,
    ) {}

    public function supplierName(string $value): string
    {
        return $this->normalizer->clean($value);
    }

    public function catalogTitle(string $supplierName): string
    {
        $source = $this->supplierName($supplierName);

        if ($source === '') {
            return '';
        }

        $isComboKievCutlet = preg_match('/^\s*Комбо\.\s*Котлета\b/ui', $source) === 1;
        $hasBeans = preg_match('/\bфасол(?:ь|и|ью)\b/ui', $source) === 1;

        $value = str_replace(['«', '»', '“', '”', '„', '‟'], '"', $source);
        $value = preg_replace('/"(?=\p{L})/u', '" ', $value) ?? $value;
        $value = preg_replace('/^\s*Комбо\.\s*/ui', '', $value) ?? $value;
        $value = preg_replace('/^\s*С-?т\.?\s*/ui', '', $value) ?? $value;
        $value = preg_replace('/\bпо[\s-]*Киевски\b/ui', 'по-киевски', $value) ?? $value;
        $value = preg_replace('/\bХот\s+дог\b/ui', 'Хот-дог', $value) ?? $value;
        $value = preg_replace('/\bкарт\.\s*пюре\b/ui', 'пюре', $value) ?? $value;
        $value = str_ireplace('колтеты', 'котлеты', $value);
        $value = preg_replace('/\bновинка\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\bГМС\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\bтреугольн(?:ый|ая|ое|ые)\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\(\s*ГМС\s*\)/ui', '', $value) ?? $value;
        $value = preg_replace('/\(\s*треугольн(?:ый|ая|ое|ые)\s*\)/ui', '', $value) ?? $value;
        $value = preg_replace('/\(\s*\d+[.,]?\d*\s*\.?\s*(?:г|гр|мл)\.?\s*\)/ui', '', $value) ?? $value;
        $value = preg_replace('/\b\d+[.,]?\d*\s*\.?\s*(?:г|гр|мл)\.?\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\(\s*\d+\s*шт\.?\s*\)/ui', '', $value) ?? $value;
        $value = preg_replace('/\b\d+\s*шт\.?\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\bшт\.?\b/ui', '', $value) ?? $value;
        $value = preg_replace('/\bс\s+начинк(?:ой|ою)\s*\(\s*сгущ\.?\s*\)/ui', 'со сгущёнкой', $value) ?? $value;
        $value = preg_replace('/\(\s*сгущ\.?\s*\)/ui', 'со сгущёнкой', $value) ?? $value;
        $value = preg_replace('/\bсгущ\.?\b/ui', 'сгущёнкой', $value) ?? $value;
        $value = $this->stripLongCompositionParentheses($value);

        if (preg_match('/^Суп\b/ui', $source) === 1) {
            $value = $this->formatSoup($value);
        }

        $value = preg_replace('/^Блинчики\s+Нас[а-яё]+\s*\(\s*с\s*(.+)\)\s*$/ui', 'Блинчики с $1', $value) ?? $value;
        $value = preg_replace('/^Блинчики\s+Фантазия\s*\(\s*с\s*(.+)\)\s*$/ui', 'Блинчики с $1', $value) ?? $value;
        $value = preg_replace('/^Блинчики\s+[^\s(]+\s*\(\s*с\s*(.+)\)\s*$/ui', 'Блинчики с $1', $value) ?? $value;
        $value = preg_replace('/^Блинчики\s+с\s+творогом\s*\(\s*и\s+зеленым\s+луком\s*\)\s*$/ui', 'Блинчики с творогом и зелёным луком', $value) ?? $value;
        $value = preg_replace('/^Блин-дог\s+с\s+сыром\s*(?:\(\s*[^)]*\s*\))?\s*с\s+соусом\s*$/ui', 'Блин-дог с сыром и соусом', $value) ?? $value;
        $value = preg_replace('/^Котлета\s*\(?по-киевски\)?\s+с\s+картофельным\s+пюре\s+и\s+фасол(?:ь|и|ью)\s*$/ui', 'Котлета по-киевски с пюре и фасолью', $value) ?? $value;
        $value = preg_replace('/^Котлета\s*\(?по-киевски\)?\s+с\s+картофельным\s+пюре\s*$/ui', 'Котлета по-киевски с пюре', $value) ?? $value;
        $value = preg_replace('/^([^()]+)\(\s*(с\s+[^)]+)\)\s*$/ui', '$1 $2', $value) ?? $value;

        $value = preg_replace('/^Каша\s+рисовая\s*\(молочная\)$/ui', 'Рисовая каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Каша\s+геркулесовая\s*\(молочная\)$/ui', 'Геркулесовая каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Каша\s+кукурузная\s*\(молочная\)$/ui', 'Кукурузная каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Котлетка\s+от\s+шефа\s*\(куриная\)\s+с\s+картофельным\s+пюре$/ui', 'Куриная котлетка от шефа с пюре', $value) ?? $value;
        $value = preg_replace('/^Отбивная\s*\(из\s+свинины\s+с\s+зеленой\s+лапшой\)$/ui', 'Свиная отбивная с зелёной лапшой', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+капустой\s*\(печеный\)$/ui', 'Печёный пирожок с капустой', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+картошкой\s*\(печеный\)$/ui', 'Печёный пирожок с картошкой', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+луком\s+и\s+яйцом\s+печ(?:е|ё)ный$/ui', 'Печёный пирожок с луком и яйцом', $value) ?? $value;
        $value = preg_replace('/^Шаурма\s+с\s+сырным\s+соусом\s*\(курица\s+и\s+картофель\)$/ui', 'Шаурма с курицей, картофелем и сырным соусом', $value) ?? $value;
        $value = preg_replace('/^Отбивная\s+из\s+куриной\s+грудки\s+в\s+хруст\.?\s*корочке\s+с\s+запеч\.?\s*картоф\.?$/ui', 'Куриная отбивная с запечённым картофелем', $value) ?? $value;
        $value = preg_replace('/^Горячий\s+бутерброд\s+с\s+ветчиной,\s*сыром$/ui', 'Горячий бутерброд с ветчиной и сыром', $value) ?? $value;
        $value = preg_replace('/^Пельмени\s+По-домашнему$/u', 'Пельмени по-домашнему', $value) ?? $value;
        $value = preg_replace('/^Большая\s+Пицца\s+С\s+(.+)$/u', 'Большая пицца с $1', $value) ?? $value;
        $value = preg_replace('/^Закуска\s+По-щучьему\s+велению$/u', 'Закуска по щучьему велению', $value) ?? $value;
        $value = preg_replace('/\bзеленой\b/ui', 'зелёной', $value) ?? $value;
        $value = preg_replace('/\bпеченый\b/ui', 'печёный', $value) ?? $value;

        if (preg_match('/^"([^"]+)"/u', $value, $quoted) === 1) {
            $tail = trim((string) mb_substr($value, mb_strlen($quoted[0])));
            $value = trim($quoted[1].($tail !== '' ? ' '.$tail : ''));
        }

        $value = str_replace('"', '', $value);
        $value = preg_replace('/\(\s*\)/u', '', $value) ?? $value;
        $value = preg_replace('/\(\s*([^)]+?)\s*\.\s*\)/u', '($1)', $value) ?? $value;
        $value = preg_replace('/\(\s*и\s+/ui', '(и ', $value) ?? $value;
        $value = preg_replace('/\s*\)/u', ')', $value) ?? $value;
        $value = preg_replace('/\s+,/u', ',', $value) ?? $value;
        $value = preg_replace('/,\s+/u', ', ', $value) ?? $value;
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B,.;:-");
        $value = preg_replace('/^Каша\s+рисовая\s*\(молочная\)$/ui', 'Рисовая каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Каша\s+геркулесовая\s*\(молочная\)$/ui', 'Геркулесовая каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Каша\s+кукурузная\s*\(молочная\)$/ui', 'Кукурузная каша на молоке', $value) ?? $value;
        $value = preg_replace('/^Котлетка\s+от\s+шефа\s*\(куриная\)\s+с\s+картофельным\s+пюре$/ui', 'Куриная котлетка от шефа с пюре', $value) ?? $value;
        $value = preg_replace('/^Отбивная\s*\(из\s+свинины\s+с\s+зел(?:е|ё)ной\s+лапшой\)$/ui', 'Свиная отбивная с зелёной лапшой', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+капустой\s*\(печ(?:е|ё)ный\)$/ui', 'Печёный пирожок с капустой', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+картошкой\s*\(печ(?:е|ё)ный\)$/ui', 'Печёный пирожок с картошкой', $value) ?? $value;
        $value = preg_replace('/^Шаурма\s+с\s+сырным\s+соусом\s*\(курица\s+и\s+картофель\)$/ui', 'Шаурма с курицей, картофелем и сырным соусом', $value) ?? $value;
        $value = preg_replace('/^Большая\s+Пицца\s+С\s+(.+)$/u', 'Большая пицца с $1', $value) ?? $value;
        $value = preg_replace('/^Пельмени\s+По-домашнему$/u', 'Пельмени по-домашнему', $value) ?? $value;
        $value = preg_replace('/^Горячий\s+бутерброд\s+с\s+ветчиной,\s*сыром$/u', 'Горячий бутерброд с ветчиной и сыром', $value) ?? $value;
        $value = preg_replace('/^Пирожок\s+с\s+луком\s+и\s+яйцом\s+печ(?:е|ё)ный$/u', 'Печёный пирожок с луком и яйцом', $value) ?? $value;
        $value = preg_replace('/^Закуска\s+По-щучьему\s+велению$/u', 'Закуска по щучьему велению', $value) ?? $value;

        if ($value === '') {
            $value = $source;
        }

        if ($isComboKievCutlet && $hasBeans && mb_stripos($value, 'фасол') !== false) {
            $value = 'Комбо: '.mb_strtolower($value, 'UTF-8');
        }

        return mb_substr($value, 0, 255);
    }

    public function normalizedNameKey(string $value): string
    {
        $normalized = $this->normalizer->normalizeName($this->supplierName($value));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function stripLongCompositionParentheses(string $value): string
    {
        return preg_replace_callback('/\(([^)]*)\)/u', function (array $match): string {
            $inside = trim((string) ($match[1] ?? ''));

            if ($inside === '') {
                return '';
            }

            $hasManyWords = count(array_filter(explode(' ', preg_replace('/\s+/u', ' ', $inside) ?? $inside))) >= 6;
            $looksLikeComposition = str_contains($inside, ',');

            if ($hasManyWords || $looksLikeComposition) {
                return '';
            }

            return ' ('.$inside.')';
        }, $value) ?? $value;
    }

    private function formatSoup(string $value): string
    {
        $value = preg_replace('/^\s*Суп\b/ui', '', $value) ?? $value;
        $value = trim($value);

        if (preg_match('/^"([^"]+)"(.*)$/u', $value, $match) === 1) {
            $base = trim((string) $match[1]);
            $tail = trim((string) ($match[2] ?? ''));
        } else {
            $parts = preg_split('/\s+/u', $value, 2) ?: [];
            $base = trim((string) ($parts[0] ?? ''));
            $tail = trim((string) ($parts[1] ?? ''));
        }

        $normalizedBase = mb_strtolower($base, 'UTF-8');

        if (in_array($normalizedBase, ['гороховый', 'сырный', 'куриный'], true)) {
            return trim($base.' суп');
        }

        if ($normalizedBase === 'том') {
            return trim($value);
        }

        if ($normalizedBase === 'том ям') {
            return trim($base.($tail !== '' ? ' '.$tail : ''));
        }

        if ($tail !== '' && preg_match('/^с\s+/ui', $tail) === 1) {
            return trim($base.' '.$tail);
        }

        return $base;
    }
}
